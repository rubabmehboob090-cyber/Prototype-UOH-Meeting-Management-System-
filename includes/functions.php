<?php
/**
 * Global Utility and Security Functions
 * University Meeting Management System
 */

/**
 * Escapes HTML output safely
 * @param mixed $value
 * @return string
 */
function e($value): string {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or retrieve CSRF Token
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden CSRF token input field
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify submitted CSRF token
 * @param string|null $token
 * @return bool
 */
function verify_csrf(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message in session
 * @param string $type success | danger | warning | info
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 * @return array|null
 */
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']);
}

/**
 * Get current logged in user ID
 * @return int|null
 */
function get_current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Retrieve full current user record from database
 * @return array|null
 */
function get_current_user_data(): ?array {
    $userId = get_current_user_id();
    if (!$userId) {
        return null;
    }
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name, d.name AS department_name, o.name AS office_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN offices o ON u.office_id = o.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Format datetime for display
 * @param string|null $datetime
 * @param string $format
 * @return string
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) return '—';
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Format date for display
 * @param string|null $date
 * @return string
 */
function format_date(?string $date): string {
    return format_datetime($date, 'D, M d, Y');
}

/**
 * Format time for display (e.g. 14:30:00 -> 02:30 PM)
 * @param string|null $time
 * @return string
 */
function format_time(?string $time): string {
    if (empty($time)) return '—';
    try {
        $dt = new DateTime($time);
        return $dt->format('h:i A');
    } catch (Exception $e) {
        return $time;
    }
}

/**
 * Get client IP address
 * @return string
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Send JSON response and exit
 * @param mixed $data
 * @param int $statusCode
 */
function json_response($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Redirect to a path relative to BASE_URL or absolute
 * @param string $path
 */
function redirect(string $path): void {
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header("Location: " . $path);
    } else {
        $path = ltrim($path, '/');
        header("Location: " . BASE_URL . '/' . $path);
    }
    exit;
}
