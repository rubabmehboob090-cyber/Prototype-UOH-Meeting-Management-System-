<?php
/**
 * Comprehensive Meeting Details & Workflow Hub
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$userId = get_current_user_id();
$meetingId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = Database::getConnection();

if (!$meetingId) {
    set_flash('danger', 'Invalid meeting requested.');
    redirect('meetings/index.php');
}

// 1. Fetch Meeting Details
$stmt = $pdo->prepare("
    SELECT m.*, 
           u.full_name AS requester_name, u.email AS requester_email, u.designation AS requester_designation,
           chair.full_name AS chair_name,
           d.name AS department_name, o.name AS office_name,
           r.name AS room_name, r.building AS room_building, r.floor AS room_floor
    FROM meetings m
    JOIN users u ON m.requester_id = u.id
    LEFT JOIN users chair ON m.chair_id = chair.id
    LEFT JOIN departments d ON m.department_id = d.id
    LEFT JOIN offices o ON m.office_id = o.id
    LEFT JOIN rooms r ON m.room_id = r.id
    WHERE m.id = ?
");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    set_flash('danger', 'Meeting record not found.');
    redirect('meetings/index.php');
}

// 2. Fetch Participants
$stmtParts = $pdo->prepare("
    SELECT mp.*, u.full_name, u.email, u.designation, d.name AS dept_name
    FROM meeting_participants mp
    JOIN users u ON mp.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE mp.meeting_id = ?
    ORDER BY FIELD(mp.meeting_role, 'chair', 'secretary', 'member', 'attendee', 'guest'), u.full_name ASC
");
$stmtParts->execute([$meetingId]);
$participants = $stmtParts->fetchAll();

// Check if current user is participant
$myParticipantRecord = null;
foreach ($participants as $p) {
    if ($p['user_id'] == $userId) {
        $myParticipantRecord = $p;
        break;
    }
}

// 3. Fetch Approval History
$stmtAppr = $pdo->prepare("
    SELECT ma.*, u.full_name AS approver_name, u.email AS approver_email, aa.scope_type, aa.description AS auth_description
    FROM meeting_approvals ma
    JOIN users u ON ma.approver_id = u.id
    LEFT JOIN approval_authorities aa ON ma.authority_id = aa.id
    WHERE ma.meeting_id = ?
    ORDER BY ma.approval_level ASC, ma.created_at ASC
");
$stmtAppr->execute([$meetingId]);
$approvals = $stmtAppr->fetchAll();

// 4. Fetch Status History
$stmtHist = $pdo->prepare("
    SELECT msh.*, u.full_name AS changed_by_name
    FROM meeting_status_history msh
    JOIN users u ON msh.changed_by = u.id
    WHERE msh.meeting_id = ?
    ORDER BY msh.created_at DESC
");
$stmtHist->execute([$meetingId]);
$statusHistory = $stmtHist->fetchAll();

// 5. Fetch Meeting Record / Minutes if published
$stmtRec = $pdo->prepare("SELECT * FROM meeting_records WHERE meeting_id = ?");
$stmtRec->execute([$meetingId]);
$meetingRecord = $stmtRec->fetch();

// 6. Fetch Active Change Requests
$stmtChg = $pdo->prepare("
    SELECT cr.*, u.full_name AS requester_name 
    FROM meeting_change_requests cr
    JOIN users u ON cr.requester_id = u.id
    WHERE cr.meeting_id = ?
    ORDER BY cr.created_at DESC
");
$stmtChg->execute([$meetingId]);
$changeRequests = $stmtChg->fetchAll();

$pageTitle = e($meeting['title']);
include APP_ROOT . '/includes/header.php';
?>

<!-- Header Action Strip -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge status-badge-<?= e($meeting['status']) ?> fs-7">
                <?= ucfirst(str_replace('_', ' ', e($meeting['status']))) ?>
            </span>
            <span class="badge bg-secondary-subtle text-secondary fs-7">
                <?= ucfirst(e($meeting['meeting_type'])) ?>
            </span>
            <?php if ($meeting['priority'] === 'urgent'): ?>
                <span class="badge bg-danger text-white">Urgent</span>
            <?php endif; ?>
        </div>
        <h4 class="fw-bold text-primary mb-0"><?= e($meeting['title']) ?></h4>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/meetings/print.php?id=<?= $meeting['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i> Print Notice
        </a>

        <?php if ($meeting['status'] === 'draft' && ($meeting['requester_id'] == $userId || is_super_admin())): ?>
            <a href="<?= BASE_URL ?>/meetings/edit.php?id=<?= $meeting['id'] ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Draft
            </a>
            <form method="POST" action="<?= BASE_URL ?>/meetings/submit.php" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                    <i class="bi bi-send me-1"></i> Submit for Approval
                </button>
            </form>
        <?php endif; ?>

        <?php if (in_array($meeting['status'], ['approved', 'pending_approval']) && ($meeting['requester_id'] == $userId || has_permission('meetings.request_change'))): ?>
            <a href="<?= BASE_URL ?>/changes/request.php?meeting_id=<?= $meeting['id'] ?>" class="btn btn-outline-warning text-dark btn-sm fw-semibold">
                <i class="bi bi-arrow-left-right me-1"></i> Request Meeting Change
            </a>
        <?php endif; ?>

        <?php if (in_array($meeting['status'], ['approved', 'completed']) && has_permission('records.create')): ?>
            <a href="<?= BASE_URL ?>/records/edit.php?meeting_id=<?= $meeting['id'] ?>" class="btn btn-success btn-sm fw-semibold">
                <i class="bi bi-file-earmark-text me-1"></i> <?= $meetingRecord ? 'Update Minutes' : 'Record Minutes' ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Participant RSVP Quick Action (If logged in user is invited) -->
<?php if ($myParticipantRecord && $meeting['status'] === 'approved'): ?>
    <div class="card shadow-sm border-primary border-opacity-25 mb-4 bg-primary-subtle bg-opacity-10">
        <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h6 class="fw-bold text-primary mb-1">
                    <i class="bi bi-envelope-check me-1"></i> Your Attendance RSVP Status: 
                    <span class="badge bg-light text-dark border"><?= ucfirst(e($myParticipantRecord['invitation_status'])) ?></span>
                </h6>
                <small class="text-muted">You are assigned as <strong><?= ucfirst(e($myParticipantRecord['meeting_role'])) ?></strong> (<?= ucfirst(e($myParticipantRecord['participant_type'])) ?>).</small>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/meetings/rsvp.php" class="d-flex align-items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                <button type="submit" name="status" value="accepted" class="btn btn-success btn-sm <?= $myParticipantRecord['invitation_status'] === 'accepted' ? 'active' : '' ?>">
                    <i class="bi bi-check-lg"></i> Accept
                </button>
                <button type="submit" name="status" value="tentative" class="btn btn-warning btn-sm text-dark <?= $myParticipantRecord['invitation_status'] === 'tentative' ? 'active' : '' ?>">
                    <i class="bi bi-question-lg"></i> Tentative
                </button>
                <button type="submit" name="status" value="declined" class="btn btn-danger btn-sm <?= $myParticipantRecord['invitation_status'] === 'declined' ? 'active' : '' ?>">
                    <i class="bi bi-x-lg"></i> Decline
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Core Meeting Data, Agenda, Minutes -->
    <div class="col-lg-8">
        <!-- Schedule and Location Banner Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Date & Time</div>
                        <div class="fw-bold text-dark fs-5">
                            <i class="bi bi-calendar3 text-primary me-1"></i> <?= format_date($meeting['meeting_date']) ?>
                        </div>
                        <div class="text-muted fs-7 mt-1">
                            <i class="bi bi-clock me-1"></i> <?= format_time($meeting['start_time']) ?> &mdash; <?= format_time($meeting['end_time']) ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Venue & Mode</div>
                        <div class="fw-bold text-dark fs-5">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($meeting['room_name'] ?: 'Online / Virtual') ?>
                        </div>
                        <div class="text-muted fs-7 mt-1">
                            <?= !empty($meeting['room_building']) ? e($meeting['room_building']) . ' (' . e($meeting['room_floor']) . ')' : ucfirst(str_replace('_', ' ', e($meeting['mode']))) ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($meeting['online_link'])): ?>
                    <div class="mt-3 pt-3 border-top d-flex align-items-center gap-2">
                        <i class="bi bi-camera-video text-primary fs-5"></i>
                        <span class="fs-7 fw-semibold">Virtual Meeting Link:</span>
                        <a href="<?= e($meeting['online_link']) ?>" target="_blank" class="text-truncate text-decoration-none fs-7" style="max-width: 400px;">
                            <?= e($meeting['online_link']) ?> <i class="bi bi-box-arrow-up-right fs-8"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Agenda & Description -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0"><i class="bi bi-journal-text me-1"></i> Agenda & Objectives</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($meeting['agenda'])): ?>
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <h6 class="fw-bold text-secondary fs-7 text-uppercase mb-2">Official Agenda</h6>
                        <div style="white-space: pre-line;" class="fs-7 text-dark"><?= e($meeting['agenda']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($meeting['description'])): ?>
                    <h6 class="fw-bold text-secondary fs-7 text-uppercase mb-1">Additional Notes</h6>
                    <p class="fs-7 text-dark mb-0" style="white-space: pre-line;"><?= e($meeting['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Participants List -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="card-title fw-bold text-primary mb-0">
                    <i class="bi bi-people-fill me-1"></i> Participants (<?= count($participants) ?>)
                </h6>
                <span class="badge bg-light text-muted border">Official Roll</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-7">
                        <thead class="table-light">
                            <tr>
                                <th>Participant</th>
                                <th>Role</th>
                                <th>Requirement</th>
                                <th>RSVP Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($p['full_name']) ?></div>
                                        <small class="text-muted"><?= e($p['designation'] ?: 'Faculty/Staff') ?> &bull; <?= e($p['dept_name'] ?: 'General') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <?= ucfirst(e($p['meeting_role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $p['participant_type'] === 'required' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' ?>">
                                            <?= ucfirst(e($p['participant_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['invitation_status'] === 'accepted'): ?>
                                            <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> Accepted</span>
                                        <?php elseif ($p['invitation_status'] === 'declined'): ?>
                                            <span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill"></i> Declined</span>
                                        <?php elseif ($p['invitation_status'] === 'tentative'): ?>
                                            <span class="text-warning-emphasis fw-semibold"><i class="bi bi-question-circle-fill"></i> Tentative</span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-hourglass"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Official Meeting Record / Minutes (If present) -->
        <?php if ($meetingRecord): ?>
            <div class="card shadow-sm border-success border-opacity-25 mb-4">
                <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
                    <h6 class="card-title fw-bold text-success mb-0">
                        <i class="bi bi-file-earmark-check-fill me-1"></i> Official Minutes of Meeting
                    </h6>
                    <span class="badge bg-success text-white">Published</span>
                </div>
                <div class="card-body p-4">
                    <h6 class="fw-bold fs-7 text-uppercase text-secondary mb-2">Minutes Summary</h6>
                    <div class="p-3 bg-light rounded-3 mb-3 fs-7" style="white-space: pre-line;">
                        <?= e($meetingRecord['minutes_summary']) ?>
                    </div>

                    <?php if (!empty($meetingRecord['key_decisions'])): ?>
                        <h6 class="fw-bold fs-7 text-uppercase text-secondary mb-2">Key Decisions Made</h6>
                        <div class="p-3 bg-light rounded-3 mb-3 fs-7" style="white-space: pre-line;">
                            <?= e($meetingRecord['key_decisions']) ?>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $actionItems = json_decode($meetingRecord['action_items'] ?? '[]', true);
                    if (!empty($actionItems)): 
                    ?>
                        <h6 class="fw-bold fs-7 text-uppercase text-secondary mb-2">Action Items & Deadlines</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm fs-7 mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Task Description</th>
                                        <th>Assignee</th>
                                        <th>Target Deadline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($actionItems as $ai): ?>
                                        <tr>
                                            <td><?= e($ai['task'] ?? '') ?></td>
                                            <td><?= e($ai['assignee'] ?? '') ?></td>
                                            <td><?= format_date($ai['deadline'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Approval Chain, History, Meta -->
    <div class="col-lg-4">
        <!-- Requester / Metadata Box -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0">Meeting Administration</h6>
            </div>
            <div class="card-body p-3 fs-7">
                <div class="mb-2">
                    <span class="text-muted d-block fs-8">Organized By</span>
                    <span class="fw-bold text-dark"><?= e($meeting['requester_name']) ?></span>
                    <div class="text-muted fs-8"><?= e($meeting['requester_email']) ?></div>
                </div>
                <hr class="my-2">
                <div class="mb-2">
                    <span class="text-muted d-block fs-8">Host Department / Office</span>
                    <span class="fw-semibold text-dark"><?= e($meeting['department_name'] ?: ($meeting['office_name'] ?: 'University Central')) ?></span>
                </div>
                <hr class="my-2">
                <div>
                    <span class="text-muted d-block fs-8">Created On</span>
                    <span class="text-dark"><?= format_datetime($meeting['created_at']) ?></span>
                </div>
            </div>
        </div>

        <!-- Approval Workflow Timeline -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0">
                    <i class="bi bi-shield-check me-1"></i> Approval Workflow
                </h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($approvals)): ?>
                    <p class="text-muted fs-7 mb-0">No approval review required or submitted yet.</p>
                <?php else: ?>
                    <div class="timeline-container">
                        <?php foreach ($approvals as $idx => $appr): ?>
                            <div class="timeline-step">
                                <div class="timeline-dot <?= $appr['status'] === 'approved' ? 'active' : ($appr['status'] === 'rejected' ? 'bg-danger' : '') ?>"></div>
                                <div class="fw-bold text-dark fs-7">
                                    Level <?= $appr['approval_level'] ?>: <?= e($appr['approver_name']) ?>
                                </div>
                                <small class="text-muted d-block fs-8"><?= e($appr['auth_description'] ?: ucfirst($appr['scope_type'] . ' Scope Authority')) ?></small>
                                
                                <div class="mt-1">
                                    <?php if ($appr['status'] === 'approved'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-8"><i class="bi bi-check-circle"></i> Approved</span>
                                    <?php elseif ($appr['status'] === 'rejected'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-8"><i class="bi bi-x-circle"></i> Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis fs-8"><i class="bi bi-hourglass-split"></i> Awaiting Review</span>
                                    <?php endif; ?>

                                    <?php if (!empty($appr['action_time'])): ?>
                                        <small class="text-muted fs-8 d-block mt-1"><?= format_datetime($appr['action_time']) ?></small>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($appr['comments'])): ?>
                                    <div class="p-2 bg-light rounded mt-2 fs-8 text-dark border">
                                        <em>"<?= e($appr['comments']) ?>"</em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Change History -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-secondary mb-0 fs-7">
                    <i class="bi bi-clock-history me-1"></i> Audit History
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush fs-8">
                    <?php foreach ($statusHistory as $sh): ?>
                        <div class="list-group-item p-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">
                                    <?= ucfirst(str_replace('_', ' ', e($sh['new_status']))) ?>
                                </span>
                                <span class="text-muted"><?= format_datetime($sh['created_at'], 'M d, h:i A') ?></span>
                            </div>
                            <div class="text-muted mt-1">
                                By: <?= e($sh['changed_by_name']) ?> <?= !empty($sh['reason']) ? '&bull; <em>' . e($sh['reason']) . '</em>' : '' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
