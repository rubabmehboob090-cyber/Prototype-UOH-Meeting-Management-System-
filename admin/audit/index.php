<?php
/**
 * Immutable System Audit Trail
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

require_permission('audit.view');

$pdo = Database::getConnection();

$actionFilter = trim($_GET['action_filter'] ?? '');
$userFilter = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;

$sql = "
    SELECT a.*, u.full_name AS user_name, u.email AS user_email
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($actionFilter)) {
    $sql .= " AND a.action LIKE ?";
    $params[] = "%{$actionFilter}%";
}

if ($userFilter) {
    $sql .= " AND a.user_id = ?";
    $params[] = $userFilter;
}

$sql .= " ORDER BY a.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll();

$pageTitle = "System Audit Logs";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-journal-check me-2"></i> Immutable Audit Trail</h4>
        <p class="text-muted mb-0 fs-7">Institutional security logging tracking all state mutations, approvals, logins, and overrides.</p>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fs-8 text-uppercase fw-bold text-muted">Action Filter</label>
                <input type="text" name="action_filter" class="form-control form-control-sm" placeholder="e.g. meeting.create, approval.approved" value="<?= e($actionFilter) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label fs-8 text-uppercase fw-bold text-muted">Actor User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">-- All Users --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userFilter === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter</button>
                <a href="<?= BASE_URL ?>/admin/audit/index.php" class="btn btn-light btn-sm border">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8 font-monospace">
                <thead class="table-light font-sans-serif">
                    <tr>
                        <th>Timestamp</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>IP Address</th>
                        <th>Payload Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted font-sans-serif">No audit entries found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?= format_datetime($l['created_at'], 'Y-m-d H:i:s') ?></td>
                                <td>
                                    <div class="fw-bold text-dark font-sans-serif"><?= e($l['user_name'] ?: 'System/CLI') ?></div>
                                    <small class="text-muted"><?= e($l['user_email'] ?: '') ?></small>
                                </td>
                                <td><span class="badge bg-light text-primary border"><?= e($l['action']) ?></span></td>
                                <td><?= e($l['entity_type']) ?> #<?= $l['entity_id'] ?></td>
                                <td><?= e($l['ip_address']) ?></td>
                                <td style="max-width: 300px;">
                                    <pre class="mb-0 text-truncate text-muted" style="max-height: 40px;"><?= e($l['new_values'] ?: ($l['old_values'] ?: 'N/A')) ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
