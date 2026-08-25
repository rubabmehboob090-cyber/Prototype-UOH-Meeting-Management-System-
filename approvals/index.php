<?php
/**
 * Pending Meeting Approvals Queue
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$userId = get_current_user_id();
$pdo = Database::getConnection();

// Fetch Pending Approvals assigned to this user or users with university-wide authority
$stmt = $pdo->prepare("
    SELECT ma.*, m.title, m.meeting_type, m.priority, m.meeting_date, m.start_time, m.end_time,
           u.full_name AS requester_name, u.email AS requester_email,
           d.name AS department_name, r.name AS room_name,
           aa.scope_type, aa.description AS auth_description
    FROM meeting_approvals ma
    JOIN meetings m ON ma.meeting_id = m.id
    JOIN users u ON m.requester_id = u.id
    LEFT JOIN departments d ON m.department_id = d.id
    LEFT JOIN rooms r ON m.room_id = r.id
    LEFT JOIN approval_authorities aa ON ma.authority_id = aa.id
    WHERE (ma.approver_id = ? OR ? = 1)
      AND ma.status = 'pending'
      AND m.status = 'pending_approval'
    ORDER BY FIELD(m.priority, 'urgent', 'high', 'normal'), m.meeting_date ASC
");
$stmt->execute([$userId, is_super_admin() ? 1 : 0]);
$pendingApprovals = $stmt->fetchAll();

$pageTitle = "Meeting Approvals Queue";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1">
            <i class="bi bi-shield-check me-2"></i> Pending Meeting Approvals
        </h4>
        <p class="text-muted mb-0 fs-7">Official university meeting proposals awaiting your authorization.</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($pendingApprovals)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
                <h5 class="fw-semibold text-dark">Queue is Clear</h5>
                <p class="fs-7 mb-0">You have no meeting requests awaiting approval at this time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-7">
                    <thead class="table-light">
                        <tr>
                            <th>Meeting Title</th>
                            <th>Scope & Type</th>
                            <th>Proposed Schedule</th>
                            <th>Venue</th>
                            <th>Requester</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApprovals as $pa): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $pa['meeting_id'] ?>" class="fw-bold text-primary text-decoration-none d-block">
                                        <?= e($pa['title']) ?>
                                    </a>
                                    <?php if ($pa['priority'] === 'urgent'): ?>
                                        <span class="badge bg-danger text-white">Urgent Priority</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= ucfirst(e($pa['meeting_type'])) ?></span>
                                    <small class="d-block text-muted fs-8 mt-1"><?= e($pa['department_name'] ?: 'University-Wide') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= format_date($pa['meeting_date']) ?></div>
                                    <small class="text-muted"><?= format_time($pa['start_time']) ?> - <?= format_time($pa['end_time']) ?></small>
                                </td>
                                <td><?= e($pa['room_name'] ?: 'Virtual / None') ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($pa['requester_name']) ?></div>
                                    <small class="text-muted"><?= e($pa['requester_email']) ?></small>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#decisionModal_<?= $pa['id'] ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Review & Decide
                                    </button>
                                </td>
                            </tr>

                            <!-- Approval Modal for each item -->
                            <div class="modal fade" id="decisionModal_<?= $pa['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="<?= BASE_URL ?>/approvals/action.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="approval_id" value="<?= $pa['id'] ?>">

                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold text-primary">
                                                    Review: <?= e($pa['title']) ?>
                                                </h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body fs-7">
                                                <div class="mb-3 p-2 bg-light rounded border">
                                                    <div><strong>Schedule:</strong> <?= format_date($pa['meeting_date']) ?>, <?= format_time($pa['start_time']) ?> - <?= format_time($pa['end_time']) ?></div>
                                                    <div><strong>Venue:</strong> <?= e($pa['room_name'] ?: 'Virtual') ?></div>
                                                    <div><strong>Requester:</strong> <?= e($pa['requester_name']) ?></div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Decision <span class="text-danger">*</span></label>
                                                    <div class="d-flex gap-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="decision" id="dec_app_<?= $pa['id'] ?>" value="approved" checked>
                                                            <label class="form-check-label text-success fw-semibold" for="dec_app_<?= $pa['id'] ?>">
                                                                <i class="bi bi-check-circle-fill"></i> Approve
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="decision" id="dec_rej_<?= $pa['id'] ?>" value="rejected">
                                                            <label class="form-check-label text-danger fw-semibold" for="dec_rej_<?= $pa['id'] ?>">
                                                                <i class="bi bi-x-circle-fill"></i> Reject
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Official Remarks / Reason</label>
                                                    <textarea name="comments" class="form-control form-control-sm" rows="3" placeholder="Enter remarks or justification (Mandatory if rejecting)..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Decision</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
