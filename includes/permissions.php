<?php
/**
 * Role-Based Access Control (RBAC) & Custom User Permissions Checker
 * University Meeting Management System
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Check if the user has a specific permission
 * (Evaluates Role Permissions first, then individual User Permissions overrides)
 * 
 * @param string $permissionName E.g., 'meetings.create', 'approvals.review_department'
 * @param int|null $userId Defaults to current session user
 * @return bool
 */
function has_permission(string $permissionName, ?int $userId = null): bool {
    if ($userId === null) {
        $userId = get_current_user_id();
    }
    if (!$userId) {
        return false;
    }

    // Check cached permissions in session if available for performance
    if (isset($_SESSION['user_permissions'][$userId]) && is_array($_SESSION['user_permissions'][$userId])) {
        return in_array($permissionName, $_SESSION['user_permissions'][$userId], true);
    }

    try {
        $pdo = Database::getConnection();

        // 1. Fetch user role
        $stmt = $pdo->prepare("
            SELECT u.id, u.role_id, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();

        if (!$userData) {
            return false;
        }

        // Super Admin role always has all permissions
        if ($userData['role_name'] === 'Super Admin') {
            return true;
        }

        // 2. Check if custom user permission override exists (user_permissions)
        $stmtUserPerm = $pdo->prepare("
            SELECT up.is_granted 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.name = ?
        ");
        $stmtUserPerm->execute([$userId, $permissionName]);
        $userPerm = $stmtUserPerm->fetch();

        if ($userPerm !== false) {
            return (bool)$userPerm['is_granted'];
        }

        // 3. Fallback to Role Permissions (role_permissions)
        $stmtRolePerm = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.name = ?
        ");
        $stmtRolePerm->execute([$userData['role_id'], $permissionName]);
        return (int)$stmtRolePerm->fetchColumn() > 0;

    } catch (Exception $e) {
        error_log("Permission evaluation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Enforce permission requirement, aborting with 403 if unauthorized
 * @param string $permissionName
 */
function require_permission(string $permissionName): void {
    if (!has_permission($permissionName)) {
        http_response_code(403);
        set_flash('danger', 'Access Denied: You do not possess the required permission (' . htmlspecialchars($permissionName) . ') to perform this action.');
        redirect('index.php');
    }
}

/**
 * Check if current user is Super Admin
 * @param int|null $userId
 * @return bool
 */
function is_super_admin(?int $userId = null): bool {
    if ($userId === null) {
        $userId = get_current_user_id();
    }
    if (!$userId) return false;

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT r.name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return ($stmt->fetchColumn() === 'Super Admin');
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all granted permissions list for a user
 * @param int $userId
 * @return array List of permission names
 */
function get_user_all_permissions(int $userId): array {
    try {
        $pdo = Database::getConnection();
        
        // 1. Get role permissions
        $stmt = $pdo->prepare("
            SELECT p.name 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN users u ON u.role_id = rp.role_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $rolePerms = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Get user specific overrides
        $stmt = $pdo->prepare("
            SELECT p.name, up.is_granted
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ?
        ");
        $stmt->execute([$userId]);
        $overrides = $stmt->fetchAll();

        $perms = array_flip($rolePerms);
        foreach ($overrides as $ov) {
            if ($ov['is_granted']) {
                $perms[$ov['name']] = true;
            } else {
                unset($perms[$ov['name']]);
            }
        }

        return array_keys($perms);
    } catch (Exception $e) {
        return [];
    }
}
