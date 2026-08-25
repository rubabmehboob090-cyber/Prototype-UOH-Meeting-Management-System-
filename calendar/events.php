<?php
/**
 * JSON Feed for Calendar Events
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';

$pdo = Database::getConnection();

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');
$roomId = !empty($_GET['room_id']) ? (int)$_GET['room_id'] : null;
$deptId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

$events = [];

// 1. Fetch Approved and Pending Meetings
$sql = "
    SELECT m.id, m.title, m.meeting_date, m.start_time, m.end_time, m.status, m.priority, m.mode,
           r.name AS room_name, d.name AS dept_name
    FROM meetings m
    LEFT JOIN rooms r ON m.room_id = r.id
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE m.status IN ('approved', 'pending_approval', 'completed')
      AND m.meeting_date >= ? AND m.meeting_date <= ?
";
$params = [$start, $end];

if ($roomId) {
    $sql .= " AND m.room_id = ?";
    $params[] = $roomId;
}
if ($deptId) {
    $sql .= " AND m.department_id = ?";
    $params[] = $deptId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    $color = '#0d6efd'; // blue
    if ($row['status'] === 'pending_approval') $color = '#fd7e14'; // orange
    elseif ($row['status'] === 'completed') $color = '#198754'; // green
    if ($row['priority'] === 'urgent') $color = '#dc3545'; // red

    $events[] = [
        'id' => 'meeting_' . $row['id'],
        'title' => $row['title'] . ($row['room_name'] ? ' (' . $row['room_name'] . ')' : ''),
        'start' => $row['meeting_date'] . 'T' . $row['start_time'],
        'end' => $row['meeting_date'] . 'T' . $row['end_time'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'url' => BASE_URL . '/meetings/view.php?id=' . $row['id'],
        'extendedProps' => [
            'status' => $row['status'],
            'room' => $row['room_name'] ?: 'Online / Virtual',
            'department' => $row['dept_name'] ?: 'General'
        ]
    ];
}

// 2. Fetch Room Blocks
$sqlBlocks = "
    SELECT rb.id, rb.room_id, rb.block_date, rb.start_time, rb.end_time, rb.reason,
           r.name AS room_name
    FROM room_blocks rb
    JOIN rooms r ON rb.room_id = r.id
    WHERE rb.block_date >= ? AND rb.block_date <= ?
";
$paramsBlocks = [$start, $end];
if ($roomId) {
    $sqlBlocks .= " AND rb.room_id = ?";
    $paramsBlocks[] = $roomId;
}

$stmtB = $pdo->prepare($sqlBlocks);
$stmtB->execute($paramsBlocks);
foreach ($stmtB->fetchAll() as $b) {
    $events[] = [
        'id' => 'block_' . $b['id'],
        'title' => '[BLOCKED] ' . $b['room_name'] . ': ' . $b['reason'],
        'start' => $b['block_date'] . 'T' . $b['start_time'],
        'end' => $b['block_date'] . 'T' . $b['end_time'],
        'backgroundColor' => '#6c757d',
        'borderColor' => '#495057',
        'allDay' => false
    ];
}

json_response($events);
