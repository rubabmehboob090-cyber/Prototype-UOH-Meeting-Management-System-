<?php
/**
 * Master Role-Appropriate Dashboard
 * University Meeting Management System
 */

define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$userId = get_current_user_id();
$today = date('Y-m-d');
$pdo = Database::getConnection();

// 1. Fetch Real Metric Counts from Database
$stats = [
    'upcoming_meetings' => 0,
    'my_pending_rsvps' => 0,
    'pending_approvals' => 0,
    'total_rooms' => 0
];

try {
    // Upcoming meetings count (Approved, today or future)
    if (has_permission('meetings.view_all')) {
        $stmtUpcoming = $pdo->prepare("
            SELECT COUNT(*) FROM meetings 
            WHERE status = 'approved' AND meeting_date >= ?
        ");
        $stmtUpcoming->execute([$today]);
    } else {
        $stmtUpcoming = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id) 
            FROM meetings m
            LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
            WHERE m.status = 'approved' AND m.meeting_date >= ?
              AND (m.requester_id = ? OR mp.user_id = ?)
        ");
        $stmtUpcoming->execute([$today, $userId, $userId]);
    }
    $stats['upcoming_meetings'] = (int)$stmtUpcoming->fetchColumn();

    // User's pending RSVPs
    $stmtRsvp = $pdo->prepare("
        SELECT COUNT(*) 
        FROM meeting_participants mp
        JOIN meetings m ON mp.meeting_id = m.id
        WHERE mp.user_id = ? AND mp.invitation_status = 'pending' AND m.status = 'approved' AND m.meeting_date >= ?
    ");
    $stmtRsvp->execute([$userId, $today]);
    $stats['my_pending_rsvps'] = (int)$stmtRsvp->fetchColumn();

    // Pending Approvals assigned to this user
    $stmtAppr = $pdo->prepare("
        SELECT COUNT(*) 
        FROM meeting_approvals ma
        JOIN meetings m ON ma.meeting_id = m.id
        WHERE ma.approver_id = ? AND ma.status = 'pending' AND m.status = 'pending_approval'
    ");
    $stmtAppr->execute([$userId]);
    $stats['pending_approvals'] = (int)$stmtAppr->fetchColumn();

    // Total Active Rooms
    $stmtRooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'");
    $stats['total_rooms'] = (int)$stmtRooms->fetchColumn();

    // 2. Fetch Today's Meetings
    if (has_permission('meetings.view_all')) {
        $stmtToday = $pdo->prepare("
            SELECT m.*, r.name AS room_name, u.full_name AS requester_name, d.name AS dept_name
            FROM meetings m
            LEFT JOIN rooms r ON m.room_id = r.id
            LEFT JOIN users u ON m.requester_id = u.id
            LEFT JOIN departments d ON m.department_id = d.id
            WHERE m.meeting_date = ? AND m.status = 'approved'
            ORDER BY m.start_time ASC
        ");
        $stmtToday->execute([$today]);
    } else {
        $stmtToday = $pdo->prepare("
            SELECT DISTINCT m.*, r.name AS room_name, u.full_name AS requester_name, d.name AS dept_name
            FROM meetings m
            LEFT JOIN rooms r ON m.room_id = r.id
            LEFT JOIN users u ON m.requester_id = u.id
            LEFT JOIN departments d ON m.department_id = d.id
            LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
            WHERE m.meeting_date = ? AND m.status = 'approved'
              AND (m.requester_id = ? OR mp.user_id = ?)
            ORDER BY m.start_time ASC
        ");
        $stmtToday->execute([$today, $userId, $userId]);
    }
    $todaysMeetings = $stmtToday->fetchAll();

    // 3. Fetch Recent Pending Requests / Action Queue
    $stmtRecent = $pdo->prepare("
        SELECT m.*, u.full_name AS requester_name, r.name AS room_name
        FROM meetings m
        JOIN users u ON m.requester_id = u.id
        LEFT JOIN rooms r ON m.room_id = r.id
        WHERE (m.requester_id = ? OR ? = 1)
        ORDER BY m.created_at DESC
        LIMIT 6
    ");
    $stmtRecent->execute([$userId, is_super_admin() ? 1 : 0]);
    $recentMeetings = $stmtRecent->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $todaysMeetings = [];
    $recentMeetings = [];
}

$pageTitle = "Dashboard";
include APP_ROOT . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
    <div>
        <h4 class="fw-bold text-primary mb-1">
            Welcome, <?= e($currentUser['full_name']) ?>
        </h4>
        <p class="text-muted mb-0 fs-7">
            <?= e($currentUser['designation'] ?: $currentUser['role_name']) ?> 
            <?= !empty($currentUser['department_name']) ? ' &bull; ' . e($currentUser['department_name']) : '' ?>
            <?= !empty($currentUser['office_name']) ? ' &bull; ' . e($currentUser['office_name']) : '' ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border px-3 py-2 fs-7 d-flex align-items-center gap-2">
            <i class="bi bi-calendar3 text-primary"></i> <?= date('l, F d, Y') ?>
        </span>
        <?php if (has_permission('meetings.create')): ?>
            <a href="<?= BASE_URL ?>/meetings/create.php" class="btn btn-primary fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Request Meeting
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Metrics Cards (Real Database Queries) -->
<div class="row g-3 mb-4">
    <!-- Card 1: Upcoming Meetings -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted fs-8 fw-bold text-uppercase d-block mb-1">Upcoming Meetings</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= $stats['upcoming_meetings'] ?></h3>
                </div>
                <div class="p-3 bg-primary-subtle text-primary rounded-3">
                    <i class="bi bi-calendar-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Pending Approvals -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted fs-8 fw-bold text-uppercase d-block mb-1">Pending Approvals</span>
                    <h3 class="fw-bold mb-0 <?= $stats['pending_approvals'] > 0 ? 'text-danger' : 'text-dark' ?>">
                        <?= $stats['pending_approvals'] ?>
                    </h3>
                </div>
                <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-3">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Pending Invitations RSVP -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted fs-8 fw-bold text-uppercase d-block mb-1">Invitations Pending RSVP</span>
                    <h3 class="fw-bold mb-0 <?= $stats['my_pending_rsvps'] > 0 ? 'text-primary' : 'text-dark' ?>">
                        <?= $stats['my_pending_rsvps'] ?>
                    </h3>
                </div>
                <div class="p-3 bg-info-subtle text-info-emphasis rounded-3">
                    <i class="bi bi-envelope-open fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Available Rooms -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted fs-8 fw-bold text-uppercase d-block mb-1">Available Rooms</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= $stats['total_rooms'] ?></h3>
                </div>
                <div class="p-3 bg-success-subtle text-success-emphasis rounded-3">
                    <i class="bi bi-building-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Today's Meeting Schedule -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold text-primary d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history"></i> Today's Schedule (<?= date('M d, Y') ?>)
                </div>
                <a href="<?= BASE_URL ?>/meetings/index.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($todaysMeetings)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        <p class="mb-0">No approved meetings scheduled for today.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todaysMeetings as $tm): ?>
                            <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $tm['id'] ?>" class="list-group-item list-group-item-action p-3">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold text-dark mb-0"><?= e($tm['title']) ?></h6>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-8">
                                        <?= format_time($tm['start_time']) ?> - <?= format_time($tm['end_time']) ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-3 text-muted fs-7">
                                    <span><i class="bi bi-geo-alt text-danger me-1"></i> <?= e($tm['room_name'] ?: 'Online / Virtual') ?></span>
                                    <span><i class="bi bi-person me-1"></i> Chair/Host: <?= e($tm['requester_name']) ?></span>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= ucfirst(e($tm['meeting_type'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity & Action Panel -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold text-primary d-flex align-items-center gap-2">
                    <i class="bi bi-list-task"></i> My Meeting Requests
                </div>
                <a href="<?= BASE_URL ?>/meetings/index.php" class="btn btn-sm btn-link text-decoration-none">Explore</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentMeetings)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-folder2-open fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        <p class="mb-0">No meeting requests submitted yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-7">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMeetings as $rm): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $rm['id'] ?>" class="text-dark fw-semibold text-decoration-none d-block text-truncate" style="max-width: 180px;">
                                                <?= e($rm['title']) ?>
                                            </a>
                                            <small class="text-muted"><?= e($rm['room_name'] ?: 'No Room') ?></small>
                                        </td>
                                        <td><?= format_date($rm['meeting_date']) ?></td>
                                        <td>
                                            <span class="badge status-badge-<?= e($rm['status']) ?>">
                                                <?= ucfirst(str_replace('_', ' ', e($rm['status']))) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
