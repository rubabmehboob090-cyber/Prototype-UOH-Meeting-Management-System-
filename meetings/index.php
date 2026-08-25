<?php
/**
 * Meetings Directory & Filterable Calendar Matrix
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$userId = get_current_user_id();
$pdo = Database::getConnection();

// Query Filters
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$deptFilter = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
$dateFilter = $_GET['date'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

// Build Query
$sql = "
    SELECT m.*, r.name AS room_name, u.full_name AS requester_name, d.name AS department_name
    FROM meetings m
    LEFT JOIN rooms r ON m.room_id = r.id
    LEFT JOIN users u ON m.requester_id = u.id
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE 1=1
";
$params = [];

// Permissions check: If user cannot view all meetings, restrict to assigned / departmental
if (!has_permission('meetings.view_all')) {
    $sql .= " AND (
        m.requester_id = ? 
        OR m.id IN (SELECT meeting_id FROM meeting_participants WHERE user_id = ?)
        " . ($currentUser['department_id'] ? "OR (m.department_id = {$currentUser['department_id']} AND m.status = 'approved')" : "") . "
    )";
    $params[] = $userId;
    $params[] = $userId;
}

if ($statusFilter !== 'all') {
    $sql .= " AND m.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $sql .= " AND m.meeting_type = ?";
    $params[] = $typeFilter;
}

if ($deptFilter) {
    $sql .= " AND m.department_id = ?";
    $params[] = $deptFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND m.meeting_date = ?";
    $params[] = $dateFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (m.title LIKE ? OR m.description LIKE ? OR u.full_name LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY m.meeting_date DESC, m.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meetings = $stmt->fetchAll();

// Fetch Departments for Filter Dropdown
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = "University Meetings";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1">
            <i class="bi bi-calendar-event me-2"></i> University Meetings
        </h4>
        <p class="text-muted mb-0 fs-7">Browse, search, and manage official meetings and schedules.</p>
    </div>
    <?php if (has_permission('meetings.create')): ?>
        <a href="<?= BASE_URL ?>/meetings/create.php" class="btn btn-primary fw-semibold shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> New Meeting Request
        </a>
    <?php endif; ?>
</div>

<!-- Filters Bar -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search title or requester..." value="<?= e($searchQuery) ?>">
                </div>
            </div>

            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="departmental" <?= $typeFilter === 'departmental' ? 'selected' : '' ?>>Departmental</option>
                    <option value="office" <?= $typeFilter === 'office' ? 'selected' : '' ?>>Administrative Office</option>
                    <option value="committee" <?= $typeFilter === 'committee' ? 'selected' : '' ?>>Committee</option>
                    <option value="university" <?= $typeFilter === 'university' ? 'selected' : '' ?>>University Statutory</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="dept_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($dateFilter) ?>" placeholder="Filter by Date">
            </div>

            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply Filter"><i class="bi bi-funnel"></i></button>
                <a href="<?= BASE_URL ?>/meetings/index.php" class="btn btn-light btn-sm border" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Meetings Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($meetings)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h5 class="fw-semibold">No meetings found</h5>
                <p class="fs-7 mb-0">No meetings match your selected criteria or schedule permissions.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-7">
                        <tr>
                            <th>Meeting Details</th>
                            <th>Date & Time</th>
                            <th>Venue / Mode</th>
                            <th>Requester / Dept</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meetings as $m): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $m['id'] ?>" class="fw-bold text-primary text-decoration-none d-block">
                                        <?= e($m['title']) ?>
                                    </a>
                                    <div class="d-flex align-items-center gap-2 mt-1 fs-8 text-muted">
                                        <span class="badge bg-secondary-subtle text-secondary"><?= ucfirst(e($m['meeting_type'])) ?></span>
                                        <?php if ($m['priority'] === 'urgent'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Urgent</span>
                                        <?php elseif ($m['priority'] === 'high'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis">High Priority</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark fs-7"><?= format_date($m['meeting_date']) ?></div>
                                    <small class="text-muted fs-8"><?= format_time($m['start_time']) ?> - <?= format_time($m['end_time']) ?></small>
                                </td>
                                <td>
                                    <div class="fs-7 text-dark"><?= e($m['room_name'] ?: 'No Physical Room') ?></div>
                                    <small class="badge bg-light text-secondary border"><?= ucfirst(str_replace('_', ' ', e($m['mode']))) ?></small>
                                </td>
                                <td>
                                    <div class="fs-7 text-dark fw-semibold"><?= e($m['requester_name']) ?></div>
                                    <small class="text-muted fs-8"><?= e($m['department_name'] ?: 'General / Office') ?></small>
                                </td>
                                <td>
                                    <span class="badge status-badge-<?= e($m['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', e($m['status']))) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $m['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($m['status'] === 'draft' && ($m['requester_id'] == $userId || is_super_admin())): ?>
                                            <a href="<?= BASE_URL ?>/meetings/edit.php?id=<?= $m['id'] ?>" class="btn btn-outline-secondary" title="Edit Draft">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
