<?php
/**
 * Submit Official Meeting Change Request
 * University Meeting Management System
 * 
 * Supports:
 * - participant_change (CRITICAL RULE: Post-submission participant additions/removals)
 * - reschedule (New date / time with conflict validation)
 * - room_change (New venue with availability check)
 * - cancellation (Official cancellation with justification)
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/conflict_checker.php';
require_once APP_ROOT . '/includes/mailer.php';
require_once APP_ROOT . '/includes/audit.php';

$userId = get_current_user_id();
$meetingId = !empty($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
$pdo = Database::getConnection();

$stmt = $pdo->prepare("
    SELECT m.*, r.name AS room_name, u.full_name AS requester_name 
    FROM meetings m
    JOIN users u ON m.requester_id = u.id
    LEFT JOIN rooms r ON m.room_id = r.id
    WHERE m.id = ?
");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    set_flash('danger', 'Meeting not found.');
    redirect('meetings/index.php');
}

if ($meeting['status'] === 'draft') {
    set_flash('info', 'This meeting is currently in Draft. You can modify participants and schedule directly without submitting a change request.');
    redirect("meetings/edit.php?id={$meetingId}");
}

// Fetch all participants
$stmtParts = $pdo->prepare("
    SELECT mp.*, u.full_name, u.email 
    FROM meeting_participants mp
    JOIN users u ON mp.user_id = u.id
    WHERE mp.meeting_id = ?
");
$stmtParts->execute([$meetingId]);
$currentParticipants = $stmtParts->fetchAll();
$currentPartUserIds = array_column($currentParticipants, 'user_id');

// Fetch all registered users for add participant options
$allUsers = $pdo->query("SELECT id, full_name, email, designation FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
$allRooms = $pdo->query("SELECT id, name, building, capacity FROM rooms WHERE status = 'available' ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $requestType = $_POST['request_type'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $errors[] = "Please provide an official justification / reason for this change request.";
        }

        $requestedData = [];

        if ($requestType === 'participant_change') {
            $addUsers = array_filter(array_map('intval', (array)($_POST['add_users'] ?? [])));
            $removeUsers = array_filter(array_map('intval', (array)($_POST['remove_users'] ?? [])));

            if (empty($addUsers) && empty($removeUsers)) {
                $errors[] = "Please select at least one participant to add or remove.";
            }

            $requestedData = [
                'add_users' => $addUsers,
                'remove_users' => $removeUsers
            ];

        } elseif ($requestType === 'reschedule') {
            $newDate = $_POST['new_date'] ?? '';
            $newStart = $_POST['new_start_time'] ?? '';
            $newEnd = $_POST['new_end_time'] ?? '';

            if (empty($newDate) || empty($newStart) || empty($newEnd)) {
                $errors[] = "New meeting date, start time, and end time are required.";
            } elseif ($newStart >= $newEnd) {
                $errors[] = "New end time must be after new start time.";
            }

            // Conflict check for rescheduled slot
            $conflictCheck = ConflictChecker::check([
                'room_id' => $meeting['room_id'],
                'meeting_date' => $newDate,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'participant_ids' => $currentPartUserIds,
                'department_id' => $meeting['department_id'],
                'ignore_meeting_id' => $meetingId
            ]);

            if ($conflictCheck['has_conflict']) {
                foreach ($conflictCheck['messages'] as $m) $errors[] = $m;
            }

            $requestedData = [
                'new_date' => $newDate,
                'new_start_time' => $newStart,
                'new_end_time' => $newEnd
            ];

        } elseif ($requestType === 'room_change') {
            $newRoomId = !empty($_POST['new_room_id']) ? (int)$_POST['new_room_id'] : 0;
            if (!$newRoomId) {
                $errors[] = "Please select the requested new room.";
            }

            // Check availability of new room
            $conflictCheck = ConflictChecker::check([
                'room_id' => $newRoomId,
                'meeting_date' => $meeting['meeting_date'],
                'start_time' => $meeting['start_time'],
                'end_time' => $meeting['end_time'],
                'ignore_meeting_id' => $meetingId
            ]);

            if ($conflictCheck['has_conflict']) {
                foreach ($conflictCheck['messages'] as $m) $errors[] = $m;
            }

            $requestedData = [
                'new_room_id' => $newRoomId
            ];

        } elseif ($requestType === 'cancellation') {
            $requestedData = [
                'cancel_request' => true
            ];
        } else {
            $errors[] = "Invalid change request type.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmtInsReq = $pdo->prepare("
                    INSERT INTO meeting_change_requests (meeting_id, requester_id, request_type, requested_data, reason, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmtInsReq->execute([
                    $meetingId,
                    $userId,
                    $requestType,
                    json_encode($requestedData),
                    $reason
                ]);

                $changeRequestId = (int)$pdo->lastInsertId();

                // If participant change, store itemized rows
                if ($requestType === 'participant_change') {
                    $stmtInsPartRow = $pdo->prepare("
                        INSERT INTO change_request_participants (change_request_id, user_id, action_type, participant_type, meeting_role)
                        VALUES (?, ?, ?, 'required', 'member')
                    ");
                    foreach ($addUsers as $auId) {
                        $stmtInsPartRow->execute([$changeRequestId, $auId, 'add']);
                    }
                    foreach ($removeUsers as $ruId) {
                        $stmtInsPartRow->execute([$changeRequestId, $ruId, 'remove']);
                    }
                }

                // Audit log
                log_audit("change_request.create", 'meeting_change_request', $changeRequestId, null, [
                    'meeting_id' => $meetingId,
                    'type' => $requestType,
                    'reason' => $reason
                ], $userId);

                // Notify Reviewing Authorities
                $stmtAuth = $pdo->prepare("
                    SELECT u.id, u.full_name, u.email 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE r.name IN ('Registrar', 'Dean', 'HOD', 'Super Admin') AND u.status = 'active'
                ");
                $stmtAuth->execute();
                foreach ($stmtAuth->fetchAll() as $auth) {
                    Mailer::notify(
                        $auth['id'],
                        $meetingId,
                        'change_request_pending',
                        "Meeting Change Request Submitted: {$meeting['title']}",
                        "A post-submission change request (" . ucfirst(str_replace('_', ' ', $requestType)) . ") has been submitted by {$currentUser['full_name']} for meeting '{$meeting['title']}' and awaits administrative review."
                    );
                }

                $pdo->commit();
                set_flash('success', 'Your change request has been submitted and dispatched to the approval authority for official review.');
                redirect("meetings/view.php?id={$meetingId}");

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to submit change request: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Request Meeting Change: " . e($meeting['title']);
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-arrow-left-right me-2"></i> Request Meeting Modification</h4>
        <p class="text-muted mb-0 fs-7">Submit an official change request for submitted/approved meeting: <strong><?= e($meeting['title']) ?></strong></p>
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

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="mb-4">
                <label class="form-label fw-bold">Select Change Category <span class="text-danger">*</span></label>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded-3 bg-light">
                            <input class="form-check-input ms-0 me-2" type="radio" name="request_type" id="type_part" value="participant_change" checked onchange="toggleChangeSections()">
                            <label class="form-check-label fw-semibold" for="type_part">
                                <i class="bi bi-people me-1 text-primary"></i> Participant Changes
                            </label>
                            <small class="d-block text-muted fs-8 mt-1">Add or remove official members</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded-3 bg-light">
                            <input class="form-check-input ms-0 me-2" type="radio" name="request_type" id="type_resched" value="reschedule" onchange="toggleChangeSections()">
                            <label class="form-check-label fw-semibold" for="type_resched">
                                <i class="bi bi-calendar-range me-1 text-warning-emphasis"></i> Reschedule Meeting
                            </label>
                            <small class="d-block text-muted fs-8 mt-1">Change date or time window</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded-3 bg-light">
                            <input class="form-check-input ms-0 me-2" type="radio" name="request_type" id="type_room" value="room_change" onchange="toggleChangeSections()">
                            <label class="form-check-label fw-semibold" for="type_room">
                                <i class="bi bi-building me-1 text-info"></i> Venue / Room Change
                            </label>
                            <small class="d-block text-muted fs-8 mt-1">Relocate to another hall/room</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded-3 bg-light">
                            <input class="form-check-input ms-0 me-2" type="radio" name="request_type" id="type_cancel" value="cancellation" onchange="toggleChangeSections()">
                            <label class="form-check-label fw-semibold text-danger" for="type_cancel">
                                <i class="bi bi-x-octagon me-1"></i> Meeting Cancellation
                            </label>
                            <small class="d-block text-muted fs-8 mt-1">Cancel meeting officially</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1. Participant Changes Section -->
            <div id="section_participant_change" class="mb-4">
                <div class="card border mb-3">
                    <div class="card-header bg-light fw-bold fs-7">
                        <i class="bi bi-person-dash me-1 text-danger"></i> Remove Existing Participant(s)
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <?php foreach ($currentParticipants as $cp): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_users[]" value="<?= $cp['user_id'] ?>" id="rem_<?= $cp['user_id'] ?>">
                                        <label class="form-check-label fs-7" for="rem_<?= $cp['user_id'] ?>">
                                            <?= e($cp['full_name']) ?> <small class="text-muted">(<?= e($cp['meeting_role']) ?>)</small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card border">
                    <div class="card-header bg-light fw-bold fs-7">
                        <i class="bi bi-person-plus me-1 text-success"></i> Add New Participant(s)
                    </div>
                    <div class="card-body p-3" style="max-height: 220px; overflow-y: auto;">
                        <div class="row g-2">
                            <?php foreach ($allUsers as $au): 
                                if (in_array($au['id'], $currentPartUserIds)) continue;
                            ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="add_users[]" value="<?= $au['id'] ?>" id="add_<?= $au['id'] ?>">
                                        <label class="form-check-label fs-7" for="add_<?= $au['id'] ?>">
                                            <?= e($au['full_name']) ?> <small class="text-muted">(<?= e($au['designation'] ?: 'Member') ?>)</small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Reschedule Section -->
            <div id="section_reschedule" class="mb-4 d-none">
                <div class="card border p-3">
                    <h6 class="fw-bold text-primary mb-3">Proposed New Schedule</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7">New Date</label>
                            <input type="date" name="new_date" class="form-control" value="<?= e($meeting['meeting_date']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7">New Start Time</label>
                            <input type="time" name="new_start_time" class="form-control" value="<?= substr($meeting['start_time'], 0, 5) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7">New End Time</label>
                            <input type="time" name="new_end_time" class="form-control" value="<?= substr($meeting['end_time'], 0, 5) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Room Change Section -->
            <div id="section_room_change" class="mb-4 d-none">
                <div class="card border p-3">
                    <h6 class="fw-bold text-primary mb-2">Select Requested New Room</h6>
                    <select name="new_room_id" class="form-select">
                        <option value="">-- Select New Venue --</option>
                        <?php foreach ($allRooms as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ((int)$meeting['room_id'] === (int)$r['id']) ? 'disabled' : '' ?>>
                                <?= e($r['name']) ?> (<?= e($r['building']) ?> - Capacity: <?= $r['capacity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 4. Cancellation Section -->
            <div id="section_cancellation" class="mb-4 d-none">
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Submitting this request will propose official cancellation of this meeting across all participant schedules once approved.
                </div>
            </div>

            <!-- Justification / Reason -->
            <div class="mb-4">
                <label class="form-label fw-bold">Official Justification / Reason <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Explain why this post-submission change is required for administrative audit records..."></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $meetingId ?>" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <i class="bi bi-send-check me-1"></i> Submit Change Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleChangeSections() {
    const selected = document.querySelector('input[name="request_type"]:checked').value;
    document.getElementById('section_participant_change').classList.add('d-none');
    document.getElementById('section_reschedule').classList.add('d-none');
    document.getElementById('section_room_change').classList.add('d-none');
    document.getElementById('section_cancellation').classList.add('d-none');

    const targetSection = document.getElementById('section_' + selected);
    if (targetSection) {
        targetSection.classList.remove('d-none');
    }
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
