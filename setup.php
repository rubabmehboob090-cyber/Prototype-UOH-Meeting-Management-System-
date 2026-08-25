<?php
/**
 * First-Time Installation & Super Admin Setup Wizard
 * University Meeting Management System
 */

define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';

$errors = [];
$successMessage = '';
$isInstalled = false;
$hasSuperAdmin = false;

try {
    $pdo = Database::getConnection();
    
    // Check if roles table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($checkTable) {
        $isInstalled = true;
        // Check if any Super Admin exists
        $stmtAdmin = $pdo->query("
            SELECT COUNT(*) 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.name = 'Super Admin' AND u.status = 'active'
        ");
        if ((int)$stmtAdmin->fetchColumn() > 0) {
            $hasSuperAdmin = true;
        }
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Handle Form Submission to Create First Administrator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $errors[] = "Security validation (CSRF) failed. Please refresh and try again.";
    } else {
        $action = $_POST['action'];

        if ($action === 'install_schema') {
            try {
                $sqlContent = file_get_contents(APP_ROOT . '/database/schema.sql');
                if (!$sqlContent) {
                    throw new Exception("Unable to read schema.sql from /database/schema.sql");
                }
                
                // Execute schema SQL commands
                $pdo->exec($sqlContent);
                $isInstalled = true;
                $successMessage = "Database schema and core RBAC roles created successfully! You can now create your Super Administrator account.";
            } catch (Exception $e) {
                $errors[] = "Schema installation failed: " . $e->getMessage();
            }
        } elseif ($action === 'create_admin') {
            if ($hasSuperAdmin) {
                $errors[] = "A Super Administrator already exists. Setup is locked for security.";
            } else {
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                $designation = trim($_POST['designation'] ?? 'System Administrator');
                $phone = trim($_POST['phone'] ?? '');

                if (empty($fullName)) $errors[] = "Full Name is required.";
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid university email address is required.";
                if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
                if ($password !== $confirmPassword) $errors[] = "Passwords do not match.";

                if (empty($errors)) {
                    try {
                        // Find Super Admin role ID
                        $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1");
                        $stmtRole->execute();
                        $roleId = $stmtRole->fetchColumn();

                        if (!$roleId) {
                            throw new Exception("Super Admin role not found. Please run the schema installation first.");
                        }

                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                        $stmtIns = $pdo->prepare("
                            INSERT INTO users (role_id, full_name, email, password_hash, designation, phone, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
                        ");
                        $stmtIns->execute([
                            $roleId,
                            $fullName,
                            $email,
                            $passwordHash,
                            $designation,
                            $phone
                        ]);

                        $adminId = $pdo->lastInsertId();

                        // Log audit
                        require_once APP_ROOT . '/includes/audit.php';
                        log_audit('system.install', 'user', $adminId, null, ['email' => $email, 'role' => 'Super Admin'], $adminId);

                        $successMessage = "Super Administrator account created successfully! You may now sign in to configure university departments, offices, and rooms.";
                        $hasSuperAdmin = true;

                    } catch (Exception $e) {
                        $errors[] = "Failed to create administrator account: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup & Installation - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 bg-primary text-white rounded-circle shadow-sm mb-3">
                    <i class="bi bi-mortarboard-fill fs-1"></i>
                </div>
                <h3 class="fw-bold text-primary mb-1"><?= APP_NAME ?></h3>
                <p class="text-muted">Installation & First-Time Super Administrator Setup</p>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm mb-4">
                            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Please resolve the following:</div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success shadow-sm d-flex align-items-center gap-2 mb-4">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div><?= e($successMessage) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($dbError)): ?>
                        <div class="alert alert-danger shadow-sm">
                            <h5 class="alert-heading"><i class="bi bi-database-x"></i> Database Connection Error</h5>
                            <p class="mb-2"><?= e($dbError) ?></p>
                            <hr>
                            <small class="text-muted">
                                Please ensure MySQL is running in XAMPP, database <code><?= DB_NAME ?></code> exists, and credentials in <code>config/database.php</code> are correct.
                            </small>
                        </div>
                    <?php elseif (!$isInstalled): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-database-gear text-primary" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold mt-3">Step 1: Initialize Database Schema</h5>
                            <p class="text-muted fs-7 mb-4">
                                The system tables and core RBAC permission matrices need to be created in your MySQL database (<code><?= DB_NAME ?></code>).
                            </p>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="install_schema">
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                                    <i class="bi bi-play-circle-fill me-1"></i> Import Schema & Initialize RBAC
                                </button>
                            </form>
                        </div>
                    <?php elseif (!$hasSuperAdmin): ?>
                        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2 text-primary">
                            <i class="bi bi-person-fill-lock"></i> Step 2: Create Super Administrator
                        </h5>
                        <p class="text-muted fs-7 mb-4">
                            Enter the official details for the primary university administrator account. No default passwords are used.
                        </p>

                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create_admin">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Muhammad Ahmed" required value="<?= e($_POST['full_name'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Official Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="e.g. admin@uoh.edu.pk" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Designation</label>
                                    <input type="text" name="designation" class="form-control" placeholder="e.g. Assistant Registrar" value="<?= e($_POST['designation'] ?? 'System Administrator') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Contact Phone</label>
                                    <input type="text" name="phone" class="form-control" placeholder="e.g. +92 995 920600" value="<?= e($_POST['phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" minlength="8" placeholder="Minimum 8 characters" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="8" placeholder="Re-enter password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-shield-lock-fill me-1"></i> Complete Setup & Register Admin
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="text-success mb-3">
                                <i class="bi bi-shield-check" style="font-size: 3.5rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark">System is Configured & Ready</h4>
                            <p class="text-muted fs-7 mb-4">
                                The database schema is initialized and an active Super Administrator is registered.
                            </p>
                            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary px-4 py-2 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Login
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="text-center text-muted fs-8 mt-4">
                <?= APP_NAME ?> &bull; XAMPP Local Deployment
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
