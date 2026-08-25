<?php
/**
 * Participant RSVP Response Handler
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid RSVP request.');
    redirect('meetings/index.php');
}

$meetingId = !empty($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
$status = $_POST['status'] ?? 'accepted';
$userId = get_current_user_id();

if (!in_array($status, ['accepted', 'declined', 'tentative'])) {
    $status = 'accepted';
}

$pdo = Database::getConnection();

try {
    $stmt = $pdo->prepare("
        UPDATE meeting_participants 
        SET invitation_status = ?, response_time = NOW()
        WHERE meeting_id = ? AND user_id = ?
    ");
    $stmt->execute([$status, $meetingId, $userId]);

    log_audit('participant.rsvp', 'meeting_participant', $meetingId, null, ['status' => $status], $userId);

    set_flash('success', "Your RSVP response has been updated to '" . ucfirst($status) . "'.");
} catch (Exception $e) {
    set_flash('danger', 'Error updating RSVP: ' . $e->getMessage());
}

redirect("meetings/view.php?id={$meetingId}");
