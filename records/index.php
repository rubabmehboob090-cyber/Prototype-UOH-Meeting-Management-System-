<?php
/**
 * Official Meeting Minutes & Records Directory
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$pdo = Database::getConnection();

$search = trim($_GET['q'] ?? '');
$deptId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

$sql = "
    SELECT mr.*, m.title AS meeting_title, m.meeting_date, m.meeting_type,
           u.full_name AS recorder_name, d.name AS department_name
    FROM meeting_records mr
    JOIN meetings m ON mr.meeting_id = m.id
    JOIN users u ON mr.recorded_by = u.id
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (m.title LIKE ? OR mr.minutes_summary LIKE ? OR mr.key_decisions LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($deptId) {
    $sql .= " AND m.department_id = ?";
    $params[] = $deptId;
}

$sql .= " ORDER BY mr.published_at DESC, mr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = "Minutes of Meeting (MoM) Repository";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1">
            <i class="bi bi-file-earmark-text me-2"></i> Official Meeting Records & Minutes
        </h4>
        <p class="text-muted mb-0 fs-7">Institutional repository of approved proceedings, decisions, and action item trackers.</p>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fs-8 text-uppercase fw-bold text-muted">Keyword Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search by title, resolutions, decisions..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fs-8 text-uppercase fw-bold text-muted">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter</button>
                <a href="<?= BASE_URL ?>/records/index.php" class="btn btn-light btn-sm border">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Records List -->
<div class="row g-4">
    <?php if (empty($records)): ?>
        <div class="col-12">
            <div class="card shadow-sm border-0 text-center py-5 text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-semibold">No meeting records found</h6>
                <p class="fs-7 mb-0">No published meeting minutes match your query.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($records as $r): ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle me-1"></i> Published MoM
                        </span>
                        <small class="text-muted"><?= format_date($r['meeting_date']) ?></small>
                    </div>
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-2">
                            <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $r['meeting_id'] ?>" class="text-decoration-none text-primary">
                                <?= e($r['meeting_title']) ?>
                            </a>
                        </h6>
                        <small class="text-muted d-block mb-3">
                            <?= e($r['department_name'] ?: 'University Central') ?> &bull; Recorded by: <?= e($r['recorder_name']) ?>
                        </small>

                        <div class="p-3 bg-light rounded-3 mb-3 fs-7" style="max-height: 120px; overflow-y: auto;">
                            <?= nl2br(e(substr($r['minutes_summary'], 0, 300))) ?>...
                        </div>

                        <?php 
                        $actions = json_decode($r['action_items'] ?? '[]', true);
                        if (!empty($actions)):
                        ?>
                            <div class="d-flex align-items-center gap-2 text-dark fs-8 fw-semibold mb-3">
                                <i class="bi bi-list-task text-warning-emphasis"></i>
                                <span><?= count($actions) ?> Action Item(s) Assigned</span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fs-8 text-muted">Published: <?= format_datetime($r['published_at'], 'M d, Y') ?></span>
                            <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $r['meeting_id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                View Full Minutes <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
