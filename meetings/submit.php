<?php
/**
 * Submit Draft Meeting to Approval Workflow
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/conflict_checker.php';
require_once APP_ROOT . '/includes/approval_engine.php';
require_once APP_ROOT . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid submission request.');
    redirect('meetings/index.php');
}

$meetingId = !empty($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
$userId = get_current_user_id();
$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    set_flash('danger', 'Meeting not found.');
    redirect('meetings/index.php');
}

if ($meeting['status'] !== 'draft') {
    set_flash('warning', 'Meeting is already submitted or processed.');
    redirect("meetings/view.php?id={$meetingId}");
}

// Fetch participants
$stmtParts = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ?");
$stmtParts->execute([$meetingId]);
$participantIds = $stmtParts->fetchAll(PDO::FETCH_COLUMN);

// Validate server-side conflicts prior to submission
$conflictCheck = ConflictChecker::check([
    'room_id' => $meeting['room_id'],
    'meeting_date' => $meeting['meeting_date'],
    'start_time' => $meeting['start_time'],
    'end_time' => $meeting['end_time'],
    'participant_ids' => $participantIds,
    'department_id' => $meeting['department_id'],
    'ignore_meeting_id' => $meetingId
]);

if ($conflictCheck['has_conflict']) {
    $errorMsg = "Cannot submit: " . implode(" ", $conflictCheck['messages']);
    set_flash('danger', $errorMsg);
    redirect("meetings/view.php?id={$meetingId}");
}

try {
    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');
    $stmtUpd = $pdo->prepare("
        UPDATE meetings 
        SET status = 'pending_approval', submission_time = ?
        WHERE id = ?
    ");
    $stmtUpd->execute([$now, $meetingId]);

    // Status history
    $stmtHist = $pdo->prepare("
        INSERT INTO meeting_status_history (meeting_id, old_status, new_status, changed_by, reason, created_at)
        VALUES (?, 'draft', 'pending_approval', ?, 'Submitted for approval', ?)
    ");
    $stmtHist->execute([$meetingId, $userId, $now]);

    // Initialize approval chain
    ApprovalEngine::initializeApprovalChain($meetingId);

    // Audit log
    log_audit('meeting.submit', 'meeting', $meetingId, ['status' => 'draft'], ['status' => 'pending_approval'], $userId);

    $pdo->commit();
    set_flash('success', 'Meeting submitted successfully! Notification sent to approval authority.');

} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('danger', 'Submission failed: ' . $e->getMessage());
}

redirect("meetings/view.php?id={$meetingId}");
