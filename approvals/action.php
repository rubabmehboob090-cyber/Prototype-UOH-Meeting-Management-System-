<?php
/**
 * Process Approval / Rejection Action Endpoint
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/approval_engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect('approvals/index.php');
}

$approvalId = !empty($_POST['approval_id']) ? (int)$_POST['approval_id'] : 0;
$decision = $_POST['decision'] ?? '';
$comments = trim($_POST['comments'] ?? '');
$userId = get_current_user_id();

if (!in_array($decision, ['approved', 'rejected'])) {
    set_flash('danger', 'Invalid approval decision value.');
    redirect('approvals/index.php');
}

if ($decision === 'rejected' && empty($comments)) {
    set_flash('danger', 'Please provide a reason or remarks when rejecting a meeting proposal.');
    redirect('approvals/index.php');
}

$result = ApprovalEngine::processDecision($approvalId, $userId, $decision, $comments);

if ($result['success']) {
    set_flash('success', $result['message']);
} else {
    set_flash('danger', $result['message']);
}

redirect('approvals/index.php');
