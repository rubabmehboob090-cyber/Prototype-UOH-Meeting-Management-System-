<?php
/**
 * Authentication Enforcement Guard
 * University Meeting Management System
 */

require_once dirname(__DIR__) . '/config/config.php';

if (!is_logged_in()) {
    // Save intended destination
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
    $_SESSION['redirect_after_login'] = $currentUri;
    set_flash('warning', 'Please sign in to access the system.');
    redirect('auth/login.php');
}

// Ensure user account is still active in database
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT id, status, role_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        session_unset();
        session_destroy();
        session_start();
        set_flash('danger', 'Your account is inactive or has been suspended. Please contact the administrator.');
        redirect('auth/login.php');
    }
} catch (Exception $e) {
    // If DB fails, log and show safe error
    error_log("Auth verification failed: " . $e->getMessage());
}
