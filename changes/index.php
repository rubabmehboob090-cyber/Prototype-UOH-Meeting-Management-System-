<?php
/**
 * Change Requests Directory & Approver Queue
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$userId = get_current_user_id();
$pdo = Database::getConnection();

// Fetch Change Requests
$sql = "
    SELECT cr.*, m.title AS meeting_title, m.meeting_date, u.full_name AS requester_name,
           rev.full_name AS reviewer_name
    FROM meeting_change_requests cr
    JOIN meetings m ON cr.meeting_id = m.id
    JOIN users u ON cr.requester_id = u.id
    LEFT JOIN users rev ON cr.reviewed_by = rev.id
    WHERE 1=1
";
$params = [];

if (!has_permission('approvals.review_changes')) {
    // Regular users see only their own change requests or requests for meetings they organized
    $sql .= " AND (cr.requester_id = ? OR m.requester_id = ?)";
    $params[] = $userId;
    $params[] = $userId;
}

$sql .= " ORDER BY cr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$changeRequests = $stmt->fetchAll();

$pageTitle = "Meeting Change Requests";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-arrow-left-right me-2"></i> Meeting Change Requests</h4>
        <p class="text-muted mb-0 fs-7">Audit trail of post-submission participant modifications, rescheduling, and cancellation requests.</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($changeRequests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-semibold">No change requests found</h6>
                <p class="fs-7 mb-0">No post-submission change requests have been lodged.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-7">
                    <thead class="table-light">
                        <tr>
                            <th>Meeting</th>
                            <th>Change Category</th>
                            <th>Requested By</th>
                            <th>Justification</th>
                            <th>Status</th>
                            <th>Date Lodged</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($changeRequests as $cr): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $cr['meeting_id'] ?>" class="fw-bold text-primary text-decoration-none d-block">
                                        <?= e($cr['meeting_title']) ?>
                                    </a>
                                    <small class="text-muted"><?= format_date($cr['meeting_date']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <?= ucfirst(str_replace('_', ' ', e($cr['request_type']))) ?>
                                    </span>
                                </td>
                                <td><?= e($cr['requester_name']) ?></td>
                                <td style="max-width: 250px;">
                                    <div class="text-truncate" title="<?= e($cr['reason']) ?>"><?= e($cr['reason']) ?></div>
                                </td>
                                <td>
                                    <?php if ($cr['status'] === 'pending'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                                    <?php elseif ($cr['status'] === 'approved'): ?>
                                        <span class="badge bg-success-subtle text-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger"><?= ucfirst(e($cr['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_datetime($cr['created_at'], 'M d, Y h:i A') ?></td>
                                <td class="text-end">
                                    <?php if ($cr['status'] === 'pending' && has_permission('approvals.review_changes')): ?>
                                        <a href="<?= BASE_URL ?>/changes/review.php?id=<?= $cr['id'] ?>" class="btn btn-primary btn-sm fw-semibold">
                                            <i class="bi bi-check2-square me-1"></i> Review
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $cr['meeting_id'] ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
