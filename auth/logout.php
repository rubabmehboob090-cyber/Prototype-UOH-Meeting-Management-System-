<?php
/**
 * User Logout Handler
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/audit.php';

if (is_logged_in()) {
    $userId = get_current_user_id();
    log_audit('user.logout', 'user', $userId, null, null, $userId);
}

// Clear all session variables
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

session_destroy();
session_start();

set_flash('info', 'You have been safely signed out.');
redirect('auth/login.php');
