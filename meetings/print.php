<?php
/**
 * Printable Official University Meeting Notice
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$meetingId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = Database::getConnection();

$stmt = $pdo->prepare("
    SELECT m.*, u.full_name AS requester_name, u.designation AS requester_designation,
           chair.full_name AS chair_name,
           d.name AS department_name, o.name AS office_name,
           r.name AS room_name, r.building AS room_building, r.floor AS room_floor
    FROM meetings m
    JOIN users u ON m.requester_id = u.id
    LEFT JOIN users chair ON m.chair_id = chair.id
    LEFT JOIN departments d ON m.department_id = d.id
    LEFT JOIN offices o ON m.office_id = o.id
    LEFT JOIN rooms r ON m.room_id = r.id
    WHERE m.id = ?
");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    die("Meeting not found.");
}

$stmtParts = $pdo->prepare("
    SELECT mp.*, u.full_name, u.email, u.designation, d.name AS dept_name
    FROM meeting_participants mp
    JOIN users u ON mp.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE mp.meeting_id = ?
    ORDER BY FIELD(mp.meeting_role, 'chair', 'secretary', 'member', 'attendee', 'guest'), u.full_name ASC
");
$stmtParts->execute([$meetingId]);
$participants = $stmtParts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meeting Notice: <?= e($meeting['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #fff; }
        .univ-header { border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 25px; }
        .meta-table td { padding: 6px 12px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body class="p-4 p-md-5">

<div class="no-print mb-4 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print Document</button>
    <button onclick="window.close()" class="btn btn-light btn-sm border">Close Window</button>
</div>

<div class="container-fluid">
    <!-- University Header -->
    <div class="text-center univ-header">
        <h3 class="fw-bold mb-1 text-uppercase">The University of Haripur</h3>
        <h5 class="fw-semibold text-uppercase text-secondary mb-1">
            <?= e($meeting['department_name'] ?: ($meeting['office_name'] ?: 'Office of the Registrar')) ?>
        </h5>
        <div class="text-muted fs-7">Official Meeting Notice &bull; Ref: UoH/MMS/<?= date('Y', strtotime($meeting['meeting_date'])) ?>/<?= str_pad($meeting['id'], 4, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="text-end mb-3">
        <strong>Date of Issue:</strong> <?= date('F d, Y') ?>
    </div>

    <h4 class="text-center fw-bold text-decoration-underline mb-4 text-uppercase">
        MEETING NOTICE
    </h4>

    <p class="fs-6 lh-base mb-4">
        This is to officially inform all concerned that the <strong><?= e($meeting['title']) ?></strong> has been scheduled as per the following details:
    </p>

    <!-- Meeting Schedule Table -->
    <table class="table table-bordered meta-table mb-4">
        <tbody>
            <tr>
                <th width="25%" class="bg-light">Meeting Subject</th>
                <td class="fw-bold"><?= e($meeting['title']) ?></td>
            </tr>
            <tr>
                <th class="bg-light">Meeting Type</th>
                <td><?= ucfirst(e($meeting['meeting_type'])) ?> Meeting (Priority: <?= ucfirst(e($meeting['priority'])) ?>)</td>
            </tr>
            <tr>
                <th class="bg-light">Date</th>
                <td class="fw-bold"><?= format_date($meeting['meeting_date']) ?></td>
            </tr>
            <tr>
                <th class="bg-light">Time</th>
                <td><?= format_time($meeting['start_time']) ?> &mdash; <?= format_time($meeting['end_time']) ?></td>
            </tr>
            <tr>
                <th class="bg-light">Venue / Mode</th>
                <td>
                    <?= e($meeting['room_name'] ?: 'Virtual / Online') ?>
                    <?= !empty($meeting['room_building']) ? ' &bull; ' . e($meeting['room_building']) . ' (' . e($meeting['room_floor']) . ')' : '' ?>
                    <?= !empty($meeting['online_link']) ? '<br><small class="text-muted">Link: ' . e($meeting['online_link']) . '</small>' : '' ?>
                </td>
            </tr>
            <tr>
                <th class="bg-light">Chairperson</th>
                <td><?= e($meeting['chair_name'] ?: $meeting['requester_name']) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Agenda -->
    <div class="mb-4">
        <h5 class="fw-bold border-bottom pb-1">AGENDA OF THE MEETING:</h5>
        <div class="ps-3 pt-2 fs-6" style="white-space: pre-line;">
            <?= e($meeting['agenda'] ?: 'Agenda items will be placed on table.') ?>
        </div>
    </div>

    <!-- Participants Roll -->
    <div class="mb-5">
        <h5 class="fw-bold border-bottom pb-1">PARTICIPANTS & MEMBERS:</h5>
        <table class="table table-sm table-bordered mt-2">
            <thead class="table-light">
                <tr>
                    <th width="40">S#</th>
                    <th>Name & Designation</th>
                    <th>Department / Office</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $idx => $p): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= e($p['full_name']) ?>, <?= e($p['designation'] ?: 'Member') ?></td>
                        <td><?= e($p['dept_name'] ?: 'University Central') ?></td>
                        <td><?= ucfirst(e($p['meeting_role'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Official Signatures -->
    <div class="row mt-5 pt-4">
        <div class="col-6">
            <p class="mb-0"><strong>Copy for Information to:</strong></p>
            <ol class="fs-7 ps-3">
                <li>All respected members / participants</li>
                <li>PS to Vice Chancellor</li>
                <li>Office of the Registrar</li>
                <li>Notice Board & Official Record File</li>
            </ol>
        </div>
        <div class="col-6 text-end">
            <div style="height: 60px;"></div>
            <div class="fw-bold"><?= e($meeting['requester_name']) ?></div>
            <div class="fs-7 text-muted"><?= e($meeting['requester_designation'] ?: 'Meeting Convener') ?></div>
            <div class="fs-7"><?= e($meeting['department_name'] ?: 'The University of Haripur') ?></div>
        </div>
    </div>
</div>

</body>
</html>
