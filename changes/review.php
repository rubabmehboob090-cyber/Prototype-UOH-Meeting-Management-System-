<?php
/**
 * Review & Decision Processing for Meeting Change Requests
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/mailer.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('approvals.review_changes');

$userId = get_current_user_id();
$changeRequestId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = Database::getConnection();

// Fetch Change Request
$stmt = $pdo->prepare("
    SELECT cr.*, m.title AS meeting_title, m.meeting_date, m.start_time, m.end_time, m.room_id, m.status AS meeting_status,
           u.full_name AS requester_name, u.email AS requester_email,
           r.name AS current_room_name
    FROM meeting_change_requests cr
    JOIN meetings m ON cr.meeting_id = m.id
    JOIN users u ON cr.requester_id = u.id
    LEFT JOIN rooms r ON m.room_id = r.id
    WHERE cr.id = ?
");
$stmt->execute([$changeRequestId]);
$changeRequest = $stmt->fetch();

if (!$changeRequest) {
    set_flash('danger', 'Change request not found.');
    redirect('changes/index.php');
}

$requestedData = json_decode($changeRequest['requested_data'] ?? '{}', true);

// Fetch itemized participant changes if applicable
$partChanges = [];
if ($changeRequest['request_type'] === 'participant_change') {
    $stmtPartChg = $pdo->prepare("
        SELECT crp.*, u.full_name, u.email, u.designation 
        FROM change_request_participants crp
        JOIN users u ON crp.user_id = u.id
        WHERE crp.change_request_id = ?
    ");
    $stmtPartChg->execute([$changeRequestId]);
    $partChanges = $stmtPartChg->fetchAll();
}

$errors = [];

// Handle Decision Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $decision = $_POST['decision'] ?? '';
        $reviewComments = trim($_POST['review_comments'] ?? '');

        if (!in_array($decision, ['approved', 'rejected'])) {
            $errors[] = "Invalid decision selection.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $now = date('Y-m-d H:i:s');
                $meetingId = $changeRequest['meeting_id'];

                // 1. Update Change Request Record
                $stmtUpdCR = $pdo->prepare("
                    UPDATE meeting_change_requests 
                    SET status = ?, reviewed_by = ?, review_comments = ?, reviewed_at = ?
                    WHERE id = ?
                ");
                $stmtUpdCR->execute([$decision, $userId, $reviewComments, $now, $changeRequestId]);

                // 2. If Approved, Apply the changes to the real database tables!
                if ($decision === 'approved') {
                    if ($changeRequest['request_type'] === 'participant_change') {
                        // Apply participant additions and removals
                        foreach ($partChanges as $pc) {
                            if ($pc['action_type'] === 'add') {
                                // Insert participant if not already present
                                $stmtAdd = $pdo->prepare("
                                    INSERT IGNORE INTO meeting_participants (meeting_id, user_id, participant_type, meeting_role, invitation_status, created_at)
                                    VALUES (?, ?, ?, ?, 'pending', NOW())
                                ");
                                $stmtAdd->execute([$meetingId, $pc['user_id'], $pc['participant_type'], $pc['meeting_role']]);

                                // Notify newly added participant
                                Mailer::notify(
                                    $pc['user_id'],
                                    $meetingId,
                                    'meeting_invitation',
                                    "Added to Meeting: {$changeRequest['meeting_title']}",
                                    "You have been officially added as a participant to the meeting '{$changeRequest['meeting_title']}'."
                                );
                            } elseif ($pc['action_type'] === 'remove') {
                                // Remove participant
                                $stmtRem = $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ? AND user_id = ?");
                                $stmtRem->execute([$meetingId, $pc['user_id']]);

                                // Notify removed participant
                                Mailer::notify(
                                    $pc['user_id'],
                                    $meetingId,
                                    'meeting_participant_removed',
                                    "Meeting Update: Removed from {$changeRequest['meeting_title']}",
                                    "You have been officially removed from the participant list of '{$changeRequest['meeting_title']}'."
                                );
                            }
                        }
                    } elseif ($changeRequest['request_type'] === 'reschedule') {
                        // Update meeting date and time
                        $stmtResched = $pdo->prepare("
                            UPDATE meetings 
                            SET meeting_date = ?, start_time = ?, end_time = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmtResched->execute([
                            $requestedData['new_date'],
                            $requestedData['new_start_time'],
                            $requestedData['new_end_time'],
                            $meetingId
                        ]);

                        // Reset participants invitation status to pending to re-confirm
                        $pdo->prepare("UPDATE meeting_participants SET invitation_status = 'pending' WHERE meeting_id = ?")->execute([$meetingId]);

                    } elseif ($changeRequest['request_type'] === 'room_change') {
                        // Update meeting room
                        $stmtRoom = $pdo->prepare("UPDATE meetings SET room_id = ?, updated_at = NOW() WHERE id = ?");
                        $stmtRoom->execute([$requestedData['new_room_id'], $meetingId]);

                    } elseif ($changeRequest['request_type'] === 'cancellation') {
                        // Mark meeting cancelled
                        $stmtCancel = $pdo->prepare("UPDATE meetings SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW() WHERE id = ?");
                        $stmtCancel->execute([$changeRequest['reason'], $meetingId]);

                        // Status history
                        $stmtHist = $pdo->prepare("
                            INSERT INTO meeting_status_history (meeting_id, old_status, new_status, changed_by, reason, created_at)
                            VALUES (?, ?, 'cancelled', ?, ?, NOW())
                        ");
                        $stmtHist->execute([$meetingId, $changeRequest['meeting_status'], $userId, "Change request approved: " . $changeRequest['reason']]);
                    }
                }

                // 3. Log Audit
                log_audit("change_request.{$decision}", 'meeting_change_request', $changeRequestId, null, [
                    'decision' => $decision,
                    'reviewer_comments' => $reviewComments
                ], $userId);

                // 4. Notify Requester
                Mailer::notify(
                    $changeRequest['requester_id'],
                    $meetingId,
                    "change_request_{$decision}",
                    "Meeting Change Request " . ucfirst($decision) . ": {$changeRequest['meeting_title']}",
                    "Your meeting change request (" . ucfirst(str_replace('_', ' ', $changeRequest['request_type'])) . ") has been officially " . strtoupper($decision) . ".\n\nReviewer Comments: " . ($reviewComments ?: 'None.')
                );

                $pdo->commit();
                set_flash('success', "Change request has been successfully {$decision}.");
                redirect("meetings/view.php?id={$meetingId}");

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to process decision: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Review Change Request #" . $changeRequest['id'];
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-shield-check me-2"></i> Review Meeting Change Request</h4>
        <p class="text-muted mb-0 fs-7">Administrative adjudication of post-submission meeting amendments.</p>
    </div>
    <a href="<?= BASE_URL ?>/changes/index.php" class="btn btn-light border">Back to List</a>
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

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0">Meeting & Request Context</h6>
            </div>
            <div class="card-body p-4 fs-7">
                <table class="table table-bordered mb-3">
                    <tbody>
                        <tr>
                            <th width="30%" class="bg-light">Meeting Title</th>
                            <td class="fw-bold"><a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $changeRequest['meeting_id'] ?>" target="_blank"><?= e($changeRequest['meeting_title']) ?></a></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Current Date & Time</th>
                            <td><?= format_date($changeRequest['meeting_date']) ?> (<?= format_time($changeRequest['start_time']) ?> - <?= format_time($changeRequest['end_time']) ?>)</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Current Room</th>
                            <td><?= e($changeRequest['current_room_name'] ?: 'None / Virtual') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Requested By</th>
                            <td><?= e($changeRequest['requester_name']) ?> (<?= e($changeRequest['requester_email']) ?>)</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Change Category</th>
                            <td><span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', e($changeRequest['request_type']))) ?></span></td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="fw-bold text-secondary text-uppercase fs-8 mb-2">Requester's Official Justification</h6>
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <?= e($changeRequest['reason']) ?>
                </div>

                <!-- Proposed Changes Breakdown -->
                <h6 class="fw-bold text-secondary text-uppercase fs-8 mb-2">Proposed Amendments Breakdown</h6>
                <?php if ($changeRequest['request_type'] === 'participant_change'): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 fs-7">
                            <thead class="table-light">
                                <tr>
                                    <th>Action</th>
                                    <th>Faculty / Staff Member</th>
                                    <th>Designation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partChanges as $pc): ?>
                                    <tr>
                                        <td>
                                            <?php if ($pc['action_type'] === 'add'): ?>
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-plus-circle"></i> Add</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger"><i class="bi bi-dash-circle"></i> Remove</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-semibold"><?= e($pc['full_name']) ?></td>
                                        <td class="text-muted"><?= e($pc['designation'] ?: 'Staff') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($changeRequest['request_type'] === 'reschedule'): ?>
                    <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-3 border border-warning-subtle">
                        <strong>New Proposed Date:</strong> <?= format_date($requestedData['new_date'] ?? '') ?><br>
                        <strong>New Time Window:</strong> <?= format_time($requestedData['new_start_time'] ?? '') ?> &mdash; <?= format_time($requestedData['new_end_time'] ?? '') ?>
                    </div>
                <?php elseif ($changeRequest['request_type'] === 'room_change'): ?>
                    <?php 
                        $stmtNewRoom = $pdo->prepare("SELECT name, building FROM rooms WHERE id = ?");
                        $stmtNewRoom->execute([$requestedData['new_room_id'] ?? 0]);
                        $newRoom = $stmtNewRoom->fetch();
                    ?>
                    <div class="p-3 bg-info-subtle text-info-emphasis rounded-3 border border-info-subtle">
                        <strong>Requested New Venue:</strong> <?= e($newRoom['name'] ?? 'Unknown') ?> (<?= e($newRoom['building'] ?? '') ?>)
                    </div>
                <?php elseif ($changeRequest['request_type'] === 'cancellation'): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i> Meeting will be officially cancelled upon approval.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Decision Form -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0">Authority Adjudication</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Decision <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec_approve" value="approved" checked>
                                <label class="form-check-label fw-semibold text-success" for="dec_approve">
                                    <i class="bi bi-check-circle-fill"></i> Approve & Apply
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec_reject" value="rejected">
                                <label class="form-check-label fw-semibold text-danger" for="dec_reject">
                                    <i class="bi bi-x-circle-fill"></i> Reject Request
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Reviewer Official Comments</label>
                        <textarea name="review_comments" class="form-control" rows="3" placeholder="Enter comments or justification for this decision..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-send-check me-1"></i> Confirm & Save Decision
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
