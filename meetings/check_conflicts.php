<?php
/**
 * AJAX Endpoint for Real-Time Meeting Conflict Checking
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/conflict_checker.php';

// Accept JSON payload or POST
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$roomId = !empty($data['room_id']) ? (int)$data['room_id'] : null;
$meetingDate = $data['meeting_date'] ?? '';
$startTime = $data['start_time'] ?? '';
$endTime = $data['end_time'] ?? '';
$participantIds = array_filter(array_map('intval', (array)($data['participant_ids'] ?? [])));
$ignoreMeetingId = !empty($data['ignore_meeting_id']) ? (int)$data['ignore_meeting_id'] : null;

$result = ConflictChecker::check([
    'room_id' => $roomId,
    'meeting_date' => $meetingDate,
    'start_time' => $startTime,
    'end_time' => $endTime,
    'participant_ids' => $participantIds,
    'ignore_meeting_id' => $ignoreMeetingId
]);

json_response($result);
