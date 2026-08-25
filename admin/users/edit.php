<?php
/**
 * Edit User Account
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('users.manage');

$currentUserId = get_current_user_id();
$targetUserId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$targetUserId]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'User account not found.');
    redirect('admin/users/index.php');
}

$errors = [];
$roles = $pdo->query("SELECT id, name FROM roles ORDER BY id ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$offices = $pdo->query("SELECT id, name FROM offices WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $officeId = !empty($_POST['office_id']) ? (int)$_POST['office_id'] : null;
        $designation = trim($_POST['designation'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($fullName)) $errors[] = "Full name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
        if (!$roleId) $errors[] = "Please assign a system role.";

        // Check email uniqueness excluding current user
        $stmtChk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtChk->execute([$email, $targetUserId]);
        if ($stmtChk->fetch()) {
            $errors[] = "An account with this email address already exists.";
        }

        if (empty($errors)) {
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmtUpd = $pdo->prepare("
                        UPDATE users SET 
                            role_id = ?, department_id = ?, office_id = ?, full_name = ?,
                            email = ?, password_hash = ?, designation = ?, phone = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([
                        $roleId, $departmentId, $officeId, $fullName,
                        $email, $hash, $designation, $phone, $status, $targetUserId
                    ]);
                } else {
                    $stmtUpd = $pdo->prepare("
                        UPDATE users SET 
                            role_id = ?, department_id = ?, office_id = ?, full_name = ?,
                            email = ?, designation = ?, phone = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([
                        $roleId, $departmentId, $officeId, $fullName,
                        $email, $designation, $phone, $status, $targetUserId
                    ]);
                }

                log_audit('user.update', 'user', $targetUserId, $user, ['name' => $fullName, 'role_id' => $roleId, 'status' => $status], $currentUserId);

                set_flash('success', "User account for '{$fullName}' updated successfully.");
                redirect('admin/users/index.php');
            } catch (Exception $e) {
                $errors[] = "Update failed: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Edit User: " . e($user['full_name']);
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-person-gear me-2"></i> Edit User Account</h4>
        <p class="text-muted mb-0 fs-7">Modify profile, security role, and administrative permissions for <strong><?= e($user['full_name']) ?></strong></p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-light border">Cancel</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($user['full_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= e($user['email']) ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Reset Password (Leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control" placeholder="New password...">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">System Role <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ((int)$user['role_id'] === (int)$r['id']) ? 'selected' : '' ?>>
                                <?= e($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Designation / Title</label>
                    <input type="text" name="designation" class="form-control" value="<?= e($user['designation']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Department (If Academic)</label>
                    <select name="department_id" class="form-select">
                        <option value="">-- None / General --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ((int)$user['department_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                                <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Office (If Administrative)</label>
                    <select name="office_id" class="form-select">
                        <option value="">-- None / Academic --</option>
                        <?php foreach ($offices as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ((int)$user['office_id'] === (int)$o['id']) ? 'selected' : '' ?>>
                                <?= e($o['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Phone / Extension</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Account Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
