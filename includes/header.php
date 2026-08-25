<?php
/**
 * Application Global Header (Bootstrap 5)
 * University Meeting Management System
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/permissions.php';

$currentUser = get_current_user_data();
$flash = get_flash();

// Fetch unread notifications count
$unreadNotifCount = 0;
if ($currentUser) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$currentUser['id']]);
        $unreadNotifCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $unreadNotifCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom Application CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid px-3 px-lg-4">
        <!-- Sidebar Toggle on Mobile -->
        <button class="btn btn-outline-light d-lg-none me-2 p-1 px-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list fs-5"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="<?= BASE_URL ?>/index.php">
            <i class="bi bi-mortarboard-fill fs-4 text-warning"></i>
            <span><?= APP_SHORT_NAME ?></span>
            <span class="d-none d-sm-inline opacity-75 fw-normal fs-6">| University Meeting System</span>
        </a>

        <!-- Right Side Nav Items -->
        <div class="d-flex align-items-center gap-3 ms-auto">
            <?php if ($currentUser): ?>
                <!-- Notifications Bell -->
                <a href="<?= BASE_URL ?>/notifications/index.php" class="btn btn-outline-light position-relative p-1 px-2 rounded-circle" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                            <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                            <span class="visually-hidden">unread notifications</span>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-primary d-flex align-items-center gap-2 dropdown-toggle border-0 py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle bg-light text-primary fw-bold text-uppercase">
                            <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                        </div>
                        <div class="text-start d-none d-md-block">
                            <div class="fw-semibold lh-1 text-truncate" style="max-width: 150px;"><?= e($currentUser['full_name']) ?></div>
                            <small class="opacity-75 fs-7"><?= e($currentUser['role_name']) ?></small>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li class="dropdown-header">
                            <span class="fw-bold d-block text-dark"><?= e($currentUser['full_name']) ?></span>
                            <small class="text-muted"><?= e($currentUser['email']) ?></small>
                            <div class="mt-1">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($currentUser['role_name']) ?></span>
                                <?php if (!empty($currentUser['department_name'])): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= e($currentUser['department_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="<?= BASE_URL ?>/auth/change_password.php"><i class="bi bi-key"></i> Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Sign Out</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-light text-primary fw-semibold btn-sm px-3">Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Main Application Layout Container -->
<div class="container-fluid">
    <div class="row">
        <?php if ($currentUser): ?>
            <!-- Left Sidebar Navigation -->
            <?php include APP_ROOT . '/includes/sidebar.php'; ?>
            <!-- Main Content Area with Sidebar Margin -->
            <main class="col-lg-10 ms-sm-auto px-md-4 py-4 main-content">
        <?php else: ?>
            <!-- Full Width Content Area for Guest Pages -->
            <main class="col-12 px-3 py-4">
        <?php endif; ?>

        <!-- Flash Message Alerts -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div class="flex-grow-1"><?= e($flash['message']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
