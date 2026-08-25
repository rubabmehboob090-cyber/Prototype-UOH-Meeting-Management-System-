<?php
/**
 * User Authentication & Sign In Handler
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/audit.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];

// Check if system has been setup
try {
    $pdo = Database::getConnection();
    $checkUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    if ((int)$checkUsers === 0) {
        set_flash('info', 'Welcome! Please complete the initial system setup to create your administrator account.');
        redirect('setup.php');
    }
} catch (Exception $e) {
    set_flash('danger', 'Database error: ' . $e->getMessage());
    redirect('setup.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security validation token expired. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = "Please enter both your email address and password.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.*, r.name AS role_name 
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.email = ?
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['status'] !== 'active') {
                        $errors[] = "Your account is marked as {$user['status']}. Please contact the university administrator.";
                    } else {
                        // Set Session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role_name'];
                        $_SESSION['role_id'] = $user['role_id'];
                        $_SESSION['department_id'] = $user['department_id'];
                        $_SESSION['office_id'] = $user['office_id'];

                        // Cache permissions in session
                        require_once APP_ROOT . '/includes/permissions.php';
                        $_SESSION['user_permissions'][$user['id']] = get_user_all_permissions((int)$user['id']);

                        // Log audit
                        log_audit('user.login', 'user', $user['id'], null, ['email' => $email, 'ip' => get_client_ip()], $user['id']);

                        set_flash('success', "Welcome back, {$user['full_name']}!");

                        $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
                        unset($_SESSION['redirect_after_login']);
                        redirect($redirectTo);
                    }
                } else {
                    $errors[] = "Invalid email or password combination.";
                }
            } catch (Exception $e) {
                $errors[] = "Authentication service error: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Sign In";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 bg-primary text-white rounded-circle shadow-sm mb-3">
                    <i class="bi bi-mortarboard-fill fs-1 text-warning"></i>
                </div>
                <h3 class="fw-bold text-primary mb-1"><?= APP_SHORT_NAME ?></h3>
                <p class="text-muted fs-7">University Meeting Management System</p>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold text-dark mb-3">Sign In to Your Account</h5>

                    <?php $flash = get_flash(); ?>
                    <?php if ($flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm mb-4">
                            <?= e($flash['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm mb-4">
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
                            <label class="form-label fw-semibold text-secondary fs-7">University Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="name@uoh.edu.pk" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary fs-7">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center text-muted fs-8 mt-4">
                &copy; <?= date('Y') ?> University Meeting Management System &bull; Secure Authentication
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
