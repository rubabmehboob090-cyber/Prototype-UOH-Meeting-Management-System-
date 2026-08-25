<?php
/**
 * Self-Service Password Change
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/audit.php';

$userId = get_current_user_id();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security validation failed. Please try again.";
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $errors[] = "Please fill in all required password fields.";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "New password must be at least 8 characters long.";
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = "New password confirmation does not match.";
        } else {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $hash = $stmt->fetchColumn();

                if (!$hash || !password_verify($currentPassword, $hash)) {
                    $errors[] = "Your current password was entered incorrectly.";
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtUpd = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $stmtUpd->execute([$newHash, $userId]);

                    log_audit('user.change_password', 'user', $userId, null, ['status' => 'password_updated'], $userId);
                    set_flash('success', 'Your password has been successfully updated.');
                    redirect('index.php');
                }
            } catch (Exception $e) {
                $errors[] = "Error updating password: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Change Password";
include APP_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fw-bold mb-0 text-primary">
                    <i class="bi bi-shield-lock me-1"></i> Change Account Password
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-7">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-7">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                        <div class="form-text fs-8">Must be at least 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold fs-7">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= BASE_URL ?>/index.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-check-circle me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
