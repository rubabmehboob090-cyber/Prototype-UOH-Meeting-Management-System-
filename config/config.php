<?php
/**
 * Application Master Configuration
 * University Meeting Management System (UoH-MMS)
 */

// Define Base Application Root
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Application Info
define('APP_NAME', 'University Meeting Management System');
define('APP_SHORT_NAME', 'UoH-MMS');
define('APP_VERSION', '1.0.0');

// Base URL calculation for XAMPP (e.g., http://localhost/meeting_management_system)
// or dynamic server detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Normalize root path for subdirectory hosting in XAMPP
$baseFolder = trim($scriptName, '/');
if (!empty($baseFolder)) {
    // If inside a subfolder like auth, admin, meetings, strip that subfolder level
    $parts = explode('/', $baseFolder);
    $rootFolder = $parts[0];
    define('BASE_URL', $protocol . $host . '/' . $rootFolder);
} else {
    define('BASE_URL', $protocol . $host);
}

// Start Secure Session if not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // Enable secure cookie if HTTPS is detected
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// -------------------------------------------------------------
// GMAIL SMTP CONFIGURATION
// (Set in environment variables or configure via system settings)
// -------------------------------------------------------------
define('SMTP_ENABLED', getenv('SMTP_ENABLED') === 'true' || true); // Enabled by default
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587); // 587 for TLS or 465 for SSL
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
define('SMTP_USER', getenv('SMTP_USER') ?: 'uoh.meeting.system@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: ''); // Use Google 16-character App Password
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@uoh.edu.pk');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'UoH Meeting Management System');

// Timezone setup
date_default_timezone_set('Asia/Karachi'); // University standard timezone (UTC+5)

// Include DB and Helper Functions
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
