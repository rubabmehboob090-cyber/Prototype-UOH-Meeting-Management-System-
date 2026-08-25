<?php
/**
 * Server-Side Conflict Detection Engine
 * University Meeting Management System
 * 
 * Performs 4 levels of validation:
 * 1. Room double-booking (Approved & Pending meetings)
 * 2. Room Block periods (Maintenance / Official reservation)
 * 3. Participant availability (Overlapping accepted/approved meetings)
 * 4. Official University / Department / Office Calendar Events
 */

require_once dirname(__DIR__) . '/config/config.php';

class ConflictChecker {

    /**
     * Comprehensive Conflict Check
     * 
     * @param array $params [
     *   'room_id' => int|null,
     *   'meeting_date' => string (YYYY-MM-DD),
     *   'start_time' => string (HH:MM:SS or HH:MM),
     *   'end_time' => string (HH:MM:SS or HH:MM),
     *   'participant_ids' => array of int,
     *   'department_id' => int|null,
     *   'office_id' => int|null,
     *   'ignore_meeting_id' => int|null
     * ]
     * @return array [
     *   'has_conflict' => bool,
     *   'room_conflicts' => array,
     *   'block_conflicts' => array,
     *   'participant_conflicts' => array,
     *   'event_conflicts' => array,
     *   'messages' => array
     * ]
     */
    public static function check(array $params): array {
        $pdo = Database::getConnection();

        $roomId = !empty($params['room_id']) ? (int)$params['room_id'] : null;
        $meetingDate = $params['meeting_date'] ?? '';
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        $participantIds = array_filter(array_map('intval', (array)($params['participant_ids'] ?? [])));
        $departmentId = !empty($params['department_id']) ? (int)$params['department_id'] : null;
        $officeId = !empty($params['office_id']) ? (int)$params['office_id'] : null;
        $ignoreMeetingId = !empty($params['ignore_meeting_id']) ? (int)$params['ignore_meeting_id'] : null;

        $result = [
            'has_conflict' => false,
            'room_conflicts' => [],
            'block_conflicts' => [],
            'participant_conflicts' => [],
            'event_conflicts' => [],
            'messages' => []
        ];

        if (empty($meetingDate) || empty($startTime) || empty($endTime)) {
            return $result;
        }

        // 1. CHECK ROOM CONFLICTS (Existing Meetings)
        if ($roomId) {
            // Check room status
            $stmtRoom = $pdo->prepare("SELECT name, status, capacity FROM rooms WHERE id = ?");
            $stmtRoom->execute([$roomId]);
            $roomInfo = $stmtRoom->fetch();

            if ($roomInfo && $roomInfo['status'] !== 'available') {
                $result['has_conflict'] = true;
                $msg = "Selected Room '{$roomInfo['name']}' is currently marked as '{$roomInfo['status']}'.";
                $result['room_conflicts'][] = ['type' => 'status', 'message' => $msg];
                $result['messages'][] = $msg;
            }

            // Check overlapping meetings in this room
            $sqlRoom = "
                SELECT m.id, m.title, m.meeting_type, m.start_time, m.end_time, m.status, u.full_name AS requester_name
                FROM meetings m
                JOIN users u ON m.requester_id = u.id
                WHERE m.room_id = ?
                  AND m.meeting_date = ?
                  AND m.status IN ('approved', 'pending_approval')
                  AND (
                      (m.start_time < ? AND m.end_time > ?) OR
                      (m.start_time >= ? AND m.start_time < ?) OR
                      (m.end_time > ? AND m.end_time <= ?)
                  )
            ";
            $roomParams = [
                $roomId, 
                $meetingDate, 
                $endTime, $startTime, 
                $startTime, $endTime, 
                $startTime, $endTime
            ];

            if ($ignoreMeetingId) {
                $sqlRoom .= " AND m.id != ?";
                $roomParams[] = $ignoreMeetingId;
            }

            $stmtMeetingOverlap = $pdo->prepare($sqlRoom);
            $stmtMeetingOverlap->execute($roomParams);
            $overlappingMeetings = $stmtMeetingOverlap->fetchAll();

            foreach ($overlappingMeetings as $mtg) {
                $result['has_conflict'] = true;
                $formattedTime = date('h:i A', strtotime($mtg['start_time'])) . ' - ' . date('h:i A', strtotime($mtg['end_time']));
                $msg = "Room Conflict: '{$roomInfo['name']}' is booked for '{$mtg['title']}' ({$formattedTime}) by {$mtg['requester_name']} [Status: {$mtg['status']}].";
                $result['room_conflicts'][] = [
                    'meeting_id' => $mtg['id'],
                    'title' => $mtg['title'],
                    'start_time' => $mtg['start_time'],
                    'end_time' => $mtg['end_time'],
                    'status' => $mtg['status'],
                    'message' => $msg
                ];
                $result['messages'][] = $msg;
            }

            // 2. CHECK ROOM BLOCKS (Maintenance / Admin Reservation)
            $startDateTime = $meetingDate . ' ' . $startTime;
            $endDateTime = $meetingDate . ' ' . $endTime;

            $sqlBlock = "
                SELECT rb.id, rb.title, rb.reason, rb.start_time, rb.end_time, u.full_name AS blocked_by
                FROM room_blocks rb
                JOIN users u ON rb.created_by = u.id
                WHERE rb.room_id = ?
                  AND rb.status = 'active'
                  AND (
                      (rb.start_time < ? AND rb.end_time > ?) OR
                      (rb.start_time >= ? AND rb.start_time < ?) OR
                      (rb.end_time > ? AND rb.end_time <= ?)
                  )
            ";
            $stmtBlock = $pdo->prepare($sqlBlock);
            $stmtBlock->execute([
                $roomId,
                $endDateTime, $startDateTime,
                $startDateTime, $endDateTime,
                $startDateTime, $endDateTime
            ]);
            $blocks = $stmtBlock->fetchAll();

            foreach ($blocks as $blk) {
                $result['has_conflict'] = true;
                $msg = "Room Blocked: '{$roomInfo['name']}' has an active block: '{$blk['title']}' (" . date('h:i A', strtotime($blk['start_time'])) . ' - ' . date('h:i A', strtotime($blk['end_time'])) . ") - Reason: {$blk['reason']}.";
                $result['block_conflicts'][] = [
                    'block_id' => $blk['id'],
                    'title' => $blk['title'],
                    'reason' => $blk['reason'],
                    'message' => $msg
                ];
                $result['messages'][] = $msg;
            }
        }

        // 3. CHECK PARTICIPANT CONFLICTS
        if (!empty($participantIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($participantIds), '?'));
            
            $sqlPart = "
                SELECT mp.user_id, u.full_name AS participant_name, u.email, m.id AS meeting_id, m.title, m.start_time, m.end_time, m.status
                FROM meeting_participants mp
                JOIN users u ON mp.user_id = u.id
                JOIN meetings m ON mp.meeting_id = m.id
                WHERE mp.user_id IN ($inPlaceholders)
                  AND m.meeting_date = ?
                  AND m.status IN ('approved', 'pending_approval')
                  AND (
                      (m.start_time < ? AND m.end_time > ?) OR
                      (m.start_time >= ? AND m.start_time < ?) OR
                      (m.end_time > ? AND m.end_time <= ?)
                  )
            ";
            
            $partParams = array_merge($participantIds, [
                $meetingDate,
                $endTime, $startTime,
                $startTime, $endTime,
                $startTime, $endTime
            ]);

            if ($ignoreMeetingId) {
                $sqlPart .= " AND m.id != ?";
                $partParams[] = $ignoreMeetingId;
            }

            $stmtPart = $pdo->prepare($sqlPart);
            $stmtPart->execute($partParams);
            $partConflicts = $stmtPart->fetchAll();

            foreach ($partConflicts as $pc) {
                $result['has_conflict'] = true;
                $formattedTime = date('h:i A', strtotime($pc['start_time'])) . ' - ' . date('h:i A', strtotime($pc['end_time']));
                $msg = "Participant Schedule Conflict: {$pc['participant_name']} is already attending/scheduled for '{$pc['title']}' ({$formattedTime}).";
                $result['participant_conflicts'][] = [
                    'user_id' => $pc['user_id'],
                    'participant_name' => $pc['participant_name'],
                    'meeting_id' => $pc['meeting_id'],
                    'title' => $pc['title'],
                    'start_time' => $pc['start_time'],
                    'end_time' => $pc['end_time'],
                    'message' => $msg
                ];
                $result['messages'][] = $msg;
            }
        }

        // 4. CHECK OFFICIAL CALENDAR EVENTS
        $startDateTime = $meetingDate . ' ' . $startTime;
        $endDateTime = $meetingDate . ' ' . $endTime;

        $sqlEvents = "
            SELECT id, title, description, event_scope, start_time, end_time
            FROM calendar_events
            WHERE status = 'active'
              AND (
                  event_scope = 'university'
                  " . ($departmentId ? "OR (event_scope = 'department' AND department_id = {$departmentId})" : "") . "
                  " . ($officeId ? "OR (event_scope = 'office' AND office_id = {$officeId})" : "") . "
              )
              AND (
                  (start_time < ? AND end_time > ?) OR
                  (start_time >= ? AND start_time < ?) OR
                  (end_time > ? AND end_time <= ?)
              )
        ";
        $stmtEvents = $pdo->prepare($sqlEvents);
        $stmtEvents->execute([
            $endDateTime, $startDateTime,
            $startDateTime, $endDateTime,
            $startDateTime, $endDateTime
        ]);
        $events = $stmtEvents->fetchAll();

        foreach ($events as $ev) {
            $result['has_conflict'] = true;
            $msg = "Official University Event Conflict: '{$ev['title']}' is scheduled during this time [Scope: " . ucfirst($ev['event_scope']) . "].";
            $result['event_conflicts'][] = [
                'event_id' => $ev['id'],
                'title' => $ev['title'],
                'scope' => $ev['event_scope'],
                'message' => $msg
            ];
            $result['messages'][] = $msg;
        }

        return $result;
    }
}
