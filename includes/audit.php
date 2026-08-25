<?php
/**
 * System Audit Logging Mechanism
 * University Meeting Management System
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Record an action into the immutable audit trail
 * 
 * @param string $action E.g., 'user.login', 'meeting.create', 'approval.approve', 'room.update'
 * @param string $entityType E.g., 'meeting', 'user', 'room', 'approval', 'record'
 * @param int|null $entityId The primary key of the modified entity
 * @param array|null $oldValues State prior to modification
 * @param array|null $newValues State after modification
 * @param int|null $userId Defaults to current session user
 * @return bool
 */
function log_audit(
    string $action, 
    string $entityType, 
    ?int $entityId = null, 
    ?array $oldValues = null, 
    ?array $newValues = null, 
    ?int $userId = null
): bool {
    try {
        $pdo = Database::getConnection();
        
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        $ip = get_client_ip();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent', 0, 250);

        $oldJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldJson,
            $newJson,
            $ip,
            $userAgent
        ]);
    } catch (Exception $e) {
        // Fallback to system error log so auditing failure doesn't halt the main business transaction
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}
