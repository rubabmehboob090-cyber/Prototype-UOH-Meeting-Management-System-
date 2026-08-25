<?php
/**
 * Edit Draft Meeting
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/conflict_checker.php';
require_once APP_ROOT . '/includes/audit.php';

$userId = get_current_user_id();
$meetingId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = Database::getConnection();

// Fetch Meeting
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    set_flash('danger', 'Meeting not found.');
    redirect('meetings/index.php');
}

// Ensure meeting is in draft state and requester or admin is editing
if ($meeting['status'] !== 'draft') {
    set_flash('warning', 'Only meetings in Draft state can be edited directly. For submitted or approved meetings, submit a Change Request.');
    redirect("meetings/view.php?id={$meetingId}");
}

if ($meeting['requester_id'] != $userId && !is_super_admin()) {
    require_permission('meetings.edit_draft');
}

$errors = [];

// Fetch dropdown data
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$rooms = $pdo->query("SELECT id, name, building, floor, capacity FROM rooms WHERE status != 'unavailable' ORDER BY name ASC")->fetchAll();
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.designation, r.name AS role_name, d.name AS dept_name 
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

// Existing Participants
$stmtCurrentParts = $pdo->prepare("SELECT user_id, participant_type, meeting_role FROM meeting_participants WHERE meeting_id = ?");
$stmtCurrentParts->execute([$meetingId]);
$currentParticipants = [];
foreach ($stmtCurrentParts->fetchAll() as $cp) {
    $currentParticipants[$cp['user_id']] = $cp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
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
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $agenda = trim($_POST['agenda'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $participantIds = array_filter(array_map('intval', (array)($_POST['participants'] ?? [])));
        $participantRoles = $_POST['participant_roles'] ?? [];
        $participantTypes = $_POST['participant_types'] ?? [];

        if (empty($title)) $errors[] = "Title is required.";
        if (empty($meetingDate)) $errors[] = "Date is required.";
        if (empty($startTime) || empty($endTime)) $errors[] = "Time is required.";
        if ($startTime >= $endTime) $errors[] = "End time must be after start time.";

        // Conflict check ignoring current meeting ID
        $conflictCheck = ConflictChecker::check([
            'room_id' => $roomId,
            'meeting_date' => $meetingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'participant_ids' => $participantIds,
            'department_id' => $departmentId,
            'ignore_meeting_id' => $meetingId
        ]);

        if ($conflictCheck['has_conflict']) {
            foreach ($conflictCheck['messages'] as $m) $errors[] = $m;
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmtUpd = $pdo->prepare("
                    UPDATE meetings SET
                        title = ?, meeting_type = ?, priority = ?, mode = ?, online_link = ?,
                        meeting_date = ?, start_time = ?, end_time = ?, room_id = ?, department_id = ?,
                        agenda = ?, description = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $title, $meetingType, $priority, $mode, $onlineLink,
                    $meetingDate, $startTime, $endTime, $roomId, $departmentId,
                    $agenda, $description, $meetingId
                ]);

                // Update participants
                $stmtDelParts = $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ?");
                $stmtDelParts->execute([$meetingId]);

                if (!in_array($userId, $participantIds)) {
                    $participantIds[] = $userId;
                }

                $stmtInsPart = $pdo->prepare("
                    INSERT INTO meeting_participants (meeting_id, user_id, participant_type, meeting_role, invitation_status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");

                foreach ($participantIds as $pId) {
                    $pType = $participantTypes[$pId] ?? 'required';
                    $pRole = $participantRoles[$pId] ?? 'member';
                    $stmtInsPart->execute([$meetingId, $pId, $pType, $pRole]);
                }

                log_audit('meeting.edit_draft', 'meeting', $meetingId, $meeting, ['title' => $title], $userId);

                $pdo->commit();
                set_flash('success', 'Draft meeting updated successfully.');
                redirect("meetings/view.php?id={$meetingId}");

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to update draft: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Edit Draft: " . e($meeting['title']);
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-pencil-square me-2"></i> Edit Draft Meeting</h4>
        <p class="text-muted mb-0 fs-7">Modify parameters before submitting for official approval.</p>
    </div>
    <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $meetingId ?>" class="btn btn-light border">Cancel</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" id="meetingForm">
    <?= csrf_field() ?>
    <input type="hidden" name="meeting_id" value="<?= $meetingId ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meeting Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= e($meeting['title']) ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Type</label>
                            <select name="meeting_type" class="form-select">
                                <option value="departmental" <?= $meeting['meeting_type'] === 'departmental' ? 'selected' : '' ?>>Departmental</option>
                                <option value="office" <?= $meeting['meeting_type'] === 'office' ? 'selected' : '' ?>>Administrative Office</option>
                                <option value="committee" <?= $meeting['meeting_type'] === 'committee' ? 'selected' : '' ?>>Statutory Committee</option>
                                <option value="university" <?= $meeting['meeting_type'] === 'university' ? 'selected' : '' ?>>University Statutory</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal" <?= $meeting['priority'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="high" <?= $meeting['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                <option value="urgent" <?= $meeting['priority'] === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mode</label>
                            <select name="mode" class="form-select">
                                <option value="in_person" <?= $meeting['mode'] === 'in_person' ? 'selected' : '' ?>>In-Person</option>
                                <option value="online" <?= $meeting['mode'] === 'online' ? 'selected' : '' ?>>Online</option>
                                <option value="hybrid" <?= $meeting['mode'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Online Meeting Link</label>
                        <input type="url" name="online_link" class="form-control" value="<?= e($meeting['online_link']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Agenda</label>
                        <textarea name="agenda" class="form-control" rows="4"><?= e($meeting['agenda']) ?></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= e($meeting['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Participants Selector (BEFORE SUBMISSION: Free Modification) -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title fw-bold text-primary mb-0">Modify Participants (Draft Phase)</h6>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 fs-7">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="40">Invite</th>
                                    <th>Faculty / Staff</th>
                                    <th>Role</th>
                                    <th>Requirement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): 
                                    $isInvited = isset($currentParticipants[$u['id']]);
                                    $partRole = $isInvited ? $currentParticipants[$u['id']]['meeting_role'] : 'member';
                                    $partType = $isInvited ? $currentParticipants[$u['id']]['participant_type'] : 'required';
                                ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="participants[]" value="<?= $u['id'] ?>" id="edit_u_<?= $u['id'] ?>" <?= $isInvited ? 'checked' : '' ?>>
                                        </td>
                                        <td>
                                            <label class="form-check-label fw-semibold text-dark d-block" for="edit_u_<?= $u['id'] ?>">
                                                <?= e($u['full_name']) ?>
                                            </label>
                                            <small class="text-muted"><?= e($u['designation'] ?: $u['role_name']) ?></small>
                                        </td>
                                        <td>
                                            <select name="participant_roles[<?= $u['id'] ?>]" class="form-select form-select-sm">
                                                <option value="member" <?= $partRole === 'member' ? 'selected' : '' ?>>Member</option>
                                                <option value="chair" <?= $partRole === 'chair' ? 'selected' : '' ?>>Chairperson</option>
                                                <option value="secretary" <?= $partRole === 'secretary' ? 'selected' : '' ?>>Secretary</option>
                                                <option value="attendee" <?= $partRole === 'attendee' ? 'selected' : '' ?>>Attendee</option>
                                                <option value="guest" <?= $partRole === 'guest' ? 'selected' : '' ?>>Guest</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="participant_types[<?= $u['id'] ?>]" class="form-select form-select-sm">
                                                <option value="required" <?= $partType === 'required' ? 'selected' : '' ?>>Required</option>
                                                <option value="optional" <?= $partType === 'optional' ? 'selected' : '' ?>>Optional</option>
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

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title fw-bold text-primary mb-0">Schedule & Venue</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meeting Date</label>
                        <input type="date" name="meeting_date" class="form-control" required value="<?= e($meeting['meeting_date']) ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required value="<?= substr($meeting['start_time'], 0, 5) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="end_time" class="form-control" required value="<?= substr($meeting['end_time'], 0, 5) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Room / Venue</label>
                        <select name="room_id" class="form-select">
                            <option value="">-- Select Room --</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= ((int)$meeting['room_id'] === (int)$r['id']) ? 'selected' : '' ?>>
                                    <?= e($r['name']) ?> (<?= e($r['building']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include APP_ROOT . '/includes/footer.php'; ?>
