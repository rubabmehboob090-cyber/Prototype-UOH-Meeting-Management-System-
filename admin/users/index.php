<?php
/**
 * User Management Directory
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

require_permission('users.manage');

$pdo = Database::getConnection();

$sql = "
    SELECT u.*, r.name AS role_name, d.name AS department_name, o.name AS office_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN offices o ON u.office_id = o.id
    ORDER BY u.full_name ASC
";
$users = $pdo->query($sql)->fetchAll();

$pageTitle = "User Management";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-people me-2"></i> University User Management</h4>
        <p class="text-muted mb-0 fs-7">Manage accounts, designations, roles, and departmental affiliations.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users/add.php" class="btn btn-primary btn-sm fw-semibold shadow-sm">
        <i class="bi bi-person-plus-fill me-1"></i> Add New User
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-7">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Email & Contact</th>
                        <th>Role</th>
                        <th>Department / Office</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                <small class="text-muted"><?= e($u['designation'] ?: 'Faculty/Staff') ?></small>
                            </td>
                            <td>
                                <div><?= e($u['email']) ?></div>
                                <small class="text-muted"><?= e($u['phone'] ?: '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= e($u['role_name']) ?>
                                </span>
                            </td>
                            <td><?= e($u['department_name'] ?: ($u['office_name'] ?: 'University Central')) ?></td>
                            <td>
                                <span class="badge <?= $u['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                    <?= ucfirst(e($u['status'])) ?>
                                </span>
                            </td>
                            <td><?= $u['last_login_at'] ? format_datetime($u['last_login_at'], 'M d, h:i A') : '<span class="text-muted">Never</span>' ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
