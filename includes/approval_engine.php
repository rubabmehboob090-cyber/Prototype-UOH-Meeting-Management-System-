<?php
/**
 * Approval Workflow Engine & Escalation Dispatcher
 * University Meeting Management System
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/audit.php';

class ApprovalEngine {

    /**
     * Determine and initialize approval chain for a submitted meeting
     * @param int $meetingId
     * @return bool
     */
    public static function initializeApprovalChain(int $meetingId): bool {
        $pdo = Database::getConnection();

        // 1. Fetch meeting details
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name AS requester_name, u.email AS requester_email,
                   d.name AS department_name, o.name AS office_name, r.requires_approval AS room_requires_approval,
                   r.name AS room_name
            FROM meetings m
            JOIN users u ON m.requester_id = u.id
            LEFT JOIN departments d ON m.department_id = d.id
            LEFT JOIN offices o ON m.office_id = o.id
            LEFT JOIN rooms r ON m.room_id = r.id
            WHERE m.id = ?
        ");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch();

        if (!$meeting) {
            return false;
        }

        // 2. Find eligible approval authorities based on scope
        $approvers = [];

        if ($meeting['meeting_type'] === 'university') {
            // University Scope Authority (e.g. Registrar)
            $stmtAuth = $pdo->prepare("
                SELECT aa.*, u.full_name, u.email 
                FROM approval_authorities aa
                JOIN users u ON aa.user_id = u.id
                WHERE aa.scope_type = 'university' AND aa.status = 'active' AND u.status = 'active'
                ORDER BY aa.level_order ASC
            ");
            $stmtAuth->execute();
            $approvers = $stmtAuth->fetchAll();
        } elseif ($meeting['meeting_type'] === 'departmental' && !empty($meeting['department_id'])) {
            // Department Scope Authority (e.g. HOD / Dean)
            $stmtAuth = $pdo->prepare("
                SELECT aa.*, u.full_name, u.email 
                FROM approval_authorities aa
                JOIN users u ON aa.user_id = u.id
                WHERE aa.scope_type = 'department' AND aa.department_id = ? AND aa.status = 'active' AND u.status = 'active'
                ORDER BY aa.level_order ASC
            ");
            $stmtAuth->execute([$meeting['department_id']]);
            $approvers = $stmtAuth->fetchAll();
        } elseif ($meeting['meeting_type'] === 'office' && !empty($meeting['office_id'])) {
            // Office Scope Authority (e.g. Director)
            $stmtAuth = $pdo->prepare("
                SELECT aa.*, u.full_name, u.email 
                FROM approval_authorities aa
                JOIN users u ON aa.user_id = u.id
                WHERE aa.scope_type = 'office' AND aa.office_id = ? AND aa.status = 'active' AND u.status = 'active'
                ORDER BY aa.level_order ASC
            ");
            $stmtAuth->execute([$meeting['office_id']]);
            $approvers = $stmtAuth->fetchAll();
        }

        // Fallback: If no dedicated authority is configured, default to Registrar / Super Admin users with approval permissions
        if (empty($approvers)) {
            $stmtFallback = $pdo->prepare("
                SELECT u.id AS user_id, u.full_name, u.email, NULL AS id, 1 AS level_order
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE r.name IN ('Registrar', 'Super Admin') AND u.status = 'active'
                LIMIT 3
            ");
            $stmtFallback->execute();
            $approvers = $stmtFallback->fetchAll();
        }

        // Clear any old pending approvals if re-submitting
        $stmtClear = $pdo->prepare("DELETE FROM meeting_approvals WHERE meeting_id = ?");
        $stmtClear->execute([$meetingId]);

        // Insert approval records for level 1 (or sequential levels)
        foreach ($approvers as $auth) {
            $stmtIns = $pdo->prepare("
                INSERT INTO meeting_approvals (meeting_id, approver_id, authority_id, approval_level, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmtIns->execute([
                $meetingId,
                $auth['user_id'],
                $auth['id'] ?? null,
                $auth['level_order'] ?? 1
            ]);

            // Create in-app notification & send email to approver
            Mailer::notify(
                $auth['user_id'],
                $meetingId,
                'meeting_approval_request',
                "New Meeting Approval Request: {$meeting['title']}",
                "Dear {$auth['full_name']},\n\nA new meeting request '{$meeting['title']}' scheduled on " . format_date($meeting['meeting_date']) . " from " . format_time($meeting['start_time']) . " to " . format_time($meeting['end_time']) . " has been submitted by {$meeting['requester_name']} for your official review and approval.",
                null
            );
        }

        return true;
    }

    /**
     * Process an approval or rejection decision
     * 
     * @param int $approvalId
     * @param int $approverId
     * @param string $decision 'approved' | 'rejected'
     * @param string $comments
     * @return array ['success' => bool, 'message' => string]
     */
    public static function processDecision(int $approvalId, int $approverId, string $decision, string $comments = ''): array {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT ma.*, m.id AS meeting_id, m.title, m.requester_id, m.status AS meeting_status,
                       u.full_name AS requester_name, u.email AS requester_email, m.meeting_date, m.start_time, m.end_time
                FROM meeting_approvals ma
                JOIN meetings m ON ma.meeting_id = m.id
                JOIN users u ON m.requester_id = u.id
                WHERE ma.id = ?
            ");
            $stmt->execute([$approvalId]);
            $approval = $stmt->fetch();

            if (!$approval) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Approval record not found.'];
            }

            if ($approval['status'] !== 'pending') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'This request has already been processed.'];
            }

            $meetingId = $approval['meeting_id'];
            $now = date('Y-m-d H:i:s');

            // 1. Update approval record
            $stmtUpd = $pdo->prepare("
                UPDATE meeting_approvals 
                SET status = ?, comments = ?, action_time = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([$decision, $comments, $now, $approvalId]);

            $oldStatus = $approval['meeting_status'];
            $newStatus = ($decision === 'approved') ? 'approved' : 'rejected';

            // 2. Update meeting status
            if ($decision === 'approved') {
                $stmtMtg = $pdo->prepare("
                    UPDATE meetings 
                    SET status = 'approved', approval_time = ? 
                    WHERE id = ?
                ");
                $stmtMtg->execute([$now, $meetingId]);
            } else {
                $stmtMtg = $pdo->prepare("
                    UPDATE meetings 
                    SET status = 'rejected', rejection_reason = ? 
                    WHERE id = ?
                ");
                $stmtMtg->execute([$comments, $meetingId]);
            }

            // 3. Insert status history
            $stmtHist = $pdo->prepare("
                INSERT INTO meeting_status_history (meeting_id, old_status, new_status, changed_by, reason, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtHist->execute([$meetingId, $oldStatus, $newStatus, $approverId, $comments, $now]);

            // 4. Log Audit
            log_audit(
                "meeting.{$decision}",
                'meeting',
                $meetingId,
                ['status' => $oldStatus],
                ['status' => $newStatus, 'comments' => $comments, 'approver_id' => $approverId],
                $approverId
            );

            // 5. Notify Requester
            $subject = ($decision === 'approved') 
                ? "Meeting Approved: {$approval['title']}" 
                : "Meeting Request Rejected: {$approval['title']}";
            
            $body = "Dear {$approval['requester_name']},\n\nYour meeting request '{$approval['title']}' scheduled on " . format_date($approval['meeting_date']) . " has been " . strtoupper($decision) . ".\n\nReviewer Comments: " . ($comments ?: 'No additional comments provided.');

            Mailer::notify(
                $approval['requester_id'],
                $meetingId,
                "meeting_{$decision}",
                $subject,
                $body
            );

            // 6. If approved, notify all participants with official invitations
            if ($decision === 'approved') {
                $stmtParts = $pdo->prepare("
                    SELECT mp.user_id, u.full_name, u.email, mp.meeting_role, mp.participant_type
                    FROM meeting_participants mp
                    JOIN users u ON mp.user_id = u.id
                    WHERE mp.meeting_id = ?
                ");
                $stmtParts->execute([$meetingId]);
                $participants = $stmtParts->fetchAll();

                foreach ($participants as $p) {
                    Mailer::notify(
                        $p['user_id'],
                        $meetingId,
                        'meeting_invitation',
                        "Official Invitation: {$approval['title']}",
                        "Dear {$p['full_name']},\n\nYou have been invited to participate as '{$p['meeting_role']}' in '{$approval['title']}' on " . format_date($approval['meeting_date']) . " from " . format_time($approval['start_time']) . " to " . format_time($approval['end_time']) . ".\n\nPlease log in to the portal to confirm your RSVP status."
                    );
                }
            }

            $pdo->commit();
            return ['success' => true, 'message' => "Meeting has been successfully {$decision}."];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Process decision failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while saving the decision: ' . $e->getMessage()];
        }
    }
}
