<?php
/**
 * Create New Meeting Request Wizard
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/conflict_checker.php';
require_once APP_ROOT . '/includes/approval_engine.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('meetings.create');

$userId = get_current_user_id();
$pdo = Database::getConnection();
$errors = [];

// Fetch dropdown dependencies from real database tables
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$offices = $pdo->query("SELECT id, name FROM offices WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$rooms = $pdo->query("SELECT id, name, building, floor, capacity, status FROM rooms WHERE status != 'unavailable' ORDER BY name ASC")->fetchAll();
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.designation, r.name AS role_name, d.name AS dept_name 
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security validation token expired. Please try again.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $meetingType = $_POST['meeting_type'] ?? 'departmental';
        $priority = $_POST['priority'] ?? 'normal';
        $mode = $_POST['mode'] ?? 'in_person';
        $onlineLink = trim($_POST['online_link'] ?? '');
        $meetingDate = $_POST['meeting_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $roomId = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : $currentUser['department_id'];
        $officeId = !empty($_POST['office_id']) ? (int)$_POST['office_id'] : $currentUser['office_id'];
        $chairId = !empty($_POST['chair_id']) ? (int)$_POST['chair_id'] : $userId;
        $agenda = trim($_POST['agenda'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $submitAction = $_POST['submit_action'] ?? 'submit'; // 'draft' or 'submit'

        // Participants payload
        $participantIds = array_filter(array_map('intval', (array)($_POST['participants'] ?? [])));
        $participantRoles = $_POST['participant_roles'] ?? [];
        $participantTypes = $_POST['participant_types'] ?? [];

        // Validation
        if (empty($title)) $errors[] = "Meeting title is required.";
        if (empty($meetingDate)) $errors[] = "Meeting date is required.";
        if (empty($startTime) || empty($endTime)) $errors[] = "Start and End times are required.";
        if ($startTime >= $endTime) $errors[] = "Meeting End Time must be after the Start Time.";
        if ($mode === 'in_person' && empty($roomId)) $errors[] = "Please select a room for in-person meetings.";

        // Server-Side Conflict Check
        $conflictCheck = ConflictChecker::check([
            'room_id' => $roomId,
            'meeting_date' => $meetingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'participant_ids' => $participantIds,
            'department_id' => $departmentId,
            'office_id' => $officeId
        ]);

        if ($conflictCheck['has_conflict']) {
            foreach ($conflictCheck['messages'] as $cMsg) {
                $errors[] = $cMsg;
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $status = ($submitAction === 'draft') ? 'draft' : 'pending_approval';
                $submissionTime = ($status === 'pending_approval') ? date('Y-m-d H:i:s') : null;

                // 1. Insert Meeting
                $stmtMtg = $pdo->prepare("
                    INSERT INTO meetings (
                        requester_id, chair_id, department_id, office_id, room_id,
                        title, description, agenda, meeting_type, priority, mode, online_link,
                        meeting_date, start_time, end_time, status, submission_time, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtMtg->execute([
                    $userId,
                    $chairId,
                    $departmentId,
                    $officeId,
                    $roomId,
                    $title,
                    $description,
                    $agenda,
                    $meetingType,
                    $priority,
                    $mode,
                    $onlineLink,
                    $meetingDate,
                    $startTime,
                    $endTime,
                    $status,
                    $submissionTime
                ]);

                $meetingId = (int)$pdo->lastInsertId();

                // 2. Insert Participants
                // Always ensure requester/chair is included
                if (!in_array($userId, $participantIds)) {
                    $participantIds[] = $userId;
                }

                $stmtPart = $pdo->prepare("
                    INSERT INTO meeting_participants (meeting_id, user_id, participant_type, meeting_role, invitation_status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");

                foreach ($participantIds as $pId) {
                    $pType = $participantTypes[$pId] ?? 'required';
                    $pRole = $participantRoles[$pId] ?? ($pId == $chairId ? 'chair' : 'member');
                    $stmtPart->execute([$meetingId, $pId, $pType, $pRole]);
                }

                // 3. Status History
                $stmtHist = $pdo->prepare("
                    INSERT INTO meeting_status_history (meeting_id, old_status, new_status, changed_by, reason, created_at)
                    VALUES (?, NULL, ?, ?, ?, NOW())
                ");
                $stmtHist->execute([
                    $meetingId,
                    $status,
                    $userId,
                    ($status === 'draft') ? 'Meeting created in draft state' : 'Meeting submitted for official approval'
                ]);

                // 4. Audit Log
                log_audit('meeting.create', 'meeting', $meetingId, null, [
                    'title' => $title,
                    'status' => $status,
                    'meeting_date' => $meetingDate,
                    'room_id' => $roomId
                ], $userId);

                // 5. If submitted, initialize approval chain
                if ($status === 'pending_approval') {
                    ApprovalEngine::initializeApprovalChain($meetingId);
                }

                $pdo->commit();

                set_flash('success', ($status === 'draft') 
                    ? "Meeting draft saved successfully." 
                    : "Meeting request submitted for approval successfully.");

                redirect("meetings/view.php?id={$meetingId}");

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Meeting creation error: " . $e->getMessage());
                $errors[] = "Failed to create meeting: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "New Meeting Request";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1">
            <i class="bi bi-calendar-plus me-2"></i> Request Official Meeting
        </h4>
        <p class="text-muted mb-0 fs-7">Submit an official meeting proposal with participant invitations and venue reservation.</p>
    </div>
    <a href="<?= BASE_URL ?>/meetings/index.php" class="btn btn-light border">
        <i class="bi bi-arrow-left me-1"></i> Back to Meetings
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Please resolve the following issues:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Dynamic Real-Time Conflict Warning Box -->
<div id="conflictAlertContainer" class="conflict-alert-box mb-4 d-none">
    <h6 class="fw-bold text-danger d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-exclamation-octagon-fill"></i> Schedule & Resource Conflicts Detected
    </h6>
    <ul id="conflictMessagesList" class="mb-0 ps-3 fs-7 text-danger"></ul>
</div>

<form method="POST" action="" id="meetingForm">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left Column: Core Meeting Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title fw-bold text-primary mb-0">1. Basic Information & Agenda</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meeting Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Departmental Academic Committee Meeting" required value="<?= e($_POST['title'] ?? '') ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Type</label>
                            <select name="meeting_type" class="form-select">
                                <option value="departmental">Departmental Meeting</option>
                                <option value="office">Administrative Office</option>
                                <option value="committee">Statutory Committee</option>
                                <option value="university">University-Wide Statutory</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority Level</label>
                            <select name="priority" class="form-select">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mode</label>
                            <select name="mode" class="form-select" id="meetingModeSelect">
                                <option value="in_person">In-Person</option>
                                <option value="online">Online (Virtual)</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="onlineLinkContainer">
                        <label class="form-label fw-semibold">Online Video Link (If Virtual / Hybrid)</label>
                        <input type="url" name="online_link" class="form-control" placeholder="e.g. https://meet.google.com/xyz-abcd-efg" value="<?= e($_POST['online_link'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meeting Agenda</label>
                        <textarea name="agenda" class="form-control" rows="4" placeholder="1. Review of previous minutes&#10;2. Course curriculum revisions&#10;3. Any other item with permission of the Chair"><?= e($_POST['agenda'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Additional Description / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief contextual background or special instructions for participants..."><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Participants Selector (BEFORE SUBMISSION RULE: Requester can add/remove freely) -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="card-title fw-bold text-primary mb-0">2. Participants & Meeting Roles</h6>
                    <span class="badge bg-light text-muted border">Real Registered Users</span>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted fs-7 mb-3">
                        <i class="bi bi-info-circle me-1"></i> Select university members to invite. (Per university rule, participants can be adjusted freely now in draft, but after submission any participant change requires an authorized change request).
                    </p>

                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 fs-7">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="40">Invite</th>
                                    <th>Faculty / Staff Member</th>
                                    <th>Role in Meeting</th>
                                    <th>Requirement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="participants[]" value="<?= $u['id'] ?>" id="user_<?= $u['id'] ?>" <?= ($u['id'] == $userId) ? 'checked' : '' ?>>
                                        </td>
                                        <td>
                                            <label class="form-check-label fw-semibold text-dark d-block" for="user_<?= $u['id'] ?>">
                                                <?= e($u['full_name']) ?>
                                            </label>
                                            <small class="text-muted"><?= e($u['designation'] ?: $u['role_name']) ?> &bull; <?= e($u['dept_name'] ?: 'General') ?></small>
                                        </td>
                                        <td>
                                            <select name="participant_roles[<?= $u['id'] ?>]" class="form-select form-select-sm">
                                                <option value="member">Member</option>
                                                <option value="chair" <?= ($u['id'] == $userId) ? 'selected' : '' ?>>Chairperson</option>
                                                <option value="secretary">Secretary / Recorder</option>
                                                <option value="attendee">Attendee</option>
                                                <option value="guest">Special Invitee / Guest</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="participant_types[<?= $u['id'] ?>]" class="form-select form-select-sm">
                                                <option value="required">Required</option>
                                                <option value="optional">Optional</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Date, Time, Room & Actions -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4 sticky-lg-top" style="top: 80px; z-index: 10;">
                <div class="card-header bg-white">
                    <h6 class="card-title fw-bold text-primary mb-0">3. Schedule & Venue</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meeting Date <span class="text-danger">*</span></label>
                        <input type="date" name="meeting_date" class="form-control" min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['meeting_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required value="<?= e($_POST['start_time'] ?? '10:00') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required value="<?= e($_POST['end_time'] ?? '11:30') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Room / Venue</label>
                        <select name="room_id" class="form-select">
                            <option value="">-- Select Room (Optional for Online) --</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= ((int)($_POST['room_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                                    <?= e($r['name']) ?> (<?= e($r['building']) ?> - Cap: <?= $r['capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Host Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- University Wide / None --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($currentUser['department_id'] == $d['id']) ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="my-4">

                    <!-- Submission Action Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_action" value="submit" class="btn btn-primary fw-semibold py-2 shadow-sm">
                            <i class="bi bi-send-check me-1"></i> Submit for Official Approval
                        </button>
                        <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary py-2">
                            <i class="bi bi-save me-1"></i> Save as Draft
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include APP_ROOT . '/includes/footer.php'; ?>
