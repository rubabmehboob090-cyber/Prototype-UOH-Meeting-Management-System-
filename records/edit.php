<?php
/**
 * Record Official Minutes of Meeting (MoM) & Action Items
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/audit.php';
require_once APP_ROOT . '/includes/mailer.php';

require_permission('records.create');

$userId = get_current_user_id();
$meetingId = !empty($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
$pdo = Database::getConnection();

// Fetch Meeting
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    set_flash('danger', 'Meeting not found.');
    redirect('meetings/index.php');
}

// Fetch Existing Record if any
$stmtRec = $pdo->prepare("SELECT * FROM meeting_records WHERE meeting_id = ?");
$stmtRec->execute([$meetingId]);
$existingRecord = $stmtRec->fetch();

// Fetch Participants
$stmtParts = $pdo->prepare("
    SELECT mp.*, u.full_name, u.email, u.designation 
    FROM meeting_participants mp
    JOIN users u ON mp.user_id = u.id
    WHERE mp.meeting_id = ?
    ORDER BY u.full_name ASC
");
$stmtParts->execute([$meetingId]);
$participants = $stmtParts->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $minutesSummary = trim($_POST['minutes_summary'] ?? '');
        $keyDecisions = trim($_POST['key_decisions'] ?? '');
        $attendance = $_POST['attendance'] ?? []; // [user_id => 'attended'|'absent']
        
        // Parse dynamic action items
        $taskNames = (array)($_POST['action_task'] ?? []);
        $taskAssignees = (array)($_POST['action_assignee'] ?? []);
        $taskDeadlines = (array)($_POST['action_deadline'] ?? []);
        
        $actionItems = [];
        for ($i = 0; $i < count($taskNames); $i++) {
            $t = trim($taskNames[$i] ?? '');
            if (!empty($t)) {
                $actionItems[] = [
                    'task' => $t,
                    'assignee' => trim($taskAssignees[$i] ?? ''),
                    'deadline' => trim($taskDeadlines[$i] ?? '')
                ];
            }
        }

        if (empty($minutesSummary)) {
            $errors[] = "Minutes summary is required.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $actionItemsJson = json_encode($actionItems);

                if ($existingRecord) {
                    $stmtUpd = $pdo->prepare("
                        UPDATE meeting_records SET 
                            minutes_summary = ?, key_decisions = ?, action_items = ?,
                            recorded_by = ?, published_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([
                        $minutesSummary, $keyDecisions, $actionItemsJson,
                        $userId, $existingRecord['id']
                    ]);
                    $recordId = $existingRecord['id'];
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO meeting_records (meeting_id, recorded_by, minutes_summary, key_decisions, action_items, published_at, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmtIns->execute([
                        $meetingId, $userId, $minutesSummary, $keyDecisions, $actionItemsJson
                    ]);
                    $recordId = (int)$pdo->lastInsertId();
                }

                // Update participant attendance
                $stmtAtt = $pdo->prepare("UPDATE meeting_participants SET attendance_status = ? WHERE meeting_id = ? AND user_id = ?");
                foreach ($participants as $p) {
                    $attStatus = $attendance[$p['user_id']] ?? 'absent';
                    $stmtAtt->execute([$attStatus, $meetingId, $p['user_id']]);
                }

                // Mark meeting as completed
                $pdo->prepare("UPDATE meetings SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$meetingId]);

                log_audit('record.publish', 'meeting_record', $recordId, null, ['meeting_id' => $meetingId], $userId);

                // Notify all participants about published minutes
                foreach ($participants as $p) {
                    Mailer::notify(
                        $p['user_id'],
                        $meetingId,
                        'minutes_published',
                        "Minutes Published: {$meeting['title']}",
                        "The official minutes of meeting for '{$meeting['title']}' have been recorded and published."
                    );
                }

                $pdo->commit();
                set_flash('success', 'Official Minutes of Meeting and Action Items published successfully.');
                redirect("meetings/view.php?id={$meetingId}");

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to save minutes: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Record Minutes: " . e($meeting['title']);
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-file-earmark-text me-2"></i> Record Minutes of Meeting (MoM)</h4>
        <p class="text-muted mb-0 fs-7">Document official deliberations, resolutions, and assign responsible action items.</p>
    </div>
    <a href="<?= BASE_URL ?>/meetings/view.php?id=<?= $meetingId ?>" class="btn btn-light border">Back to Meeting</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" id="momForm">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Summary & Key Decisions -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title fw-bold text-primary mb-0">1. Minutes & Decisions</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Executive Minutes Summary <span class="text-danger">*</span></label>
                        <textarea name="minutes_summary" class="form-control" rows="6" required placeholder="Detailed summary of points discussed during the session..."><?= e($existingRecord['minutes_summary'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Official Resolutions / Key Decisions</label>
                        <textarea name="key_decisions" class="form-control" rows="4" placeholder="1. Resolution A approved unanimously&#10;2. Committee recommended formation of subcommittee..."><?= e($existingRecord['key_decisions'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Action Items Dynamic Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="card-title fw-bold text-primary mb-0">2. Action Items & Assignments</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" onclick="addActionItemRow()">
                        <i class="bi bi-plus-circle me-1"></i> Add Task
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle fs-7 mb-0" id="actionItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Task Description</th>
                                    <th width="200">Assignee Person / Dept</th>
                                    <th width="150">Target Deadline</th>
                                    <th width="50" class="text-center">Del</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $existingActions = json_decode($existingRecord['action_items'] ?? '[]', true);
                                if (empty($existingActions)) {
                                    $existingActions = [['task' => '', 'assignee' => '', 'deadline' => '']];
                                }
                                foreach ($existingActions as $ea):
                                ?>
                                    <tr>
                                        <td><input type="text" name="action_task[]" class="form-control form-control-sm" placeholder="Task description..." value="<?= e($ea['task'] ?? '') ?>"></td>
                                        <td><input type="text" name="action_assignee[]" class="form-control form-control-sm" placeholder="Name or Office" value="<?= e($ea['assignee'] ?? '') ?>"></td>
                                        <td><input type="date" name="action_deadline[]" class="form-control form-control-sm" value="<?= e($ea['deadline'] ?? '') ?>"></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Roll & Publish -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title fw-bold text-primary mb-0">3. Attendance Record</h6>
                </div>
                <div class="card-body p-3">
                    <p class="text-muted fs-8 mb-2">Mark participant attendance for official roll:</p>
                    <div style="max-height: 320px; overflow-y: auto;">
                        <?php foreach ($participants as $p): ?>
                            <div class="d-flex justify-content-between align-items-center p-2 border-bottom fs-7">
                                <div>
                                    <div class="fw-bold text-dark"><?= e($p['full_name']) ?></div>
                                    <small class="text-muted"><?= ucfirst(e($p['meeting_role'])) ?></small>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="attendance[<?= $p['user_id'] ?>]" id="att_pres_<?= $p['user_id'] ?>" value="attended" <?= ($p['attendance_status'] === 'attended' || empty($p['attendance_status'])) ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success" for="att_pres_<?= $p['user_id'] ?>">Present</label>

                                    <input type="radio" class="btn-check" name="attendance[<?= $p['user_id'] ?>]" id="att_abs_<?= $p['user_id'] ?>" value="absent" <?= ($p['attendance_status'] === 'absent') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-danger" for="att_abs_<?= $p['user_id'] ?>">Absent</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-file-earmark-check me-1"></i> Publish Official Minutes
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function addActionItemRow() {
    const tbody = document.querySelector('#actionItemsTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="action_task[]" class="form-control form-control-sm" placeholder="Task description..."></td>
        <td><input type="text" name="action_assignee[]" class="form-control form-control-sm" placeholder="Name or Office"></td>
        <td><input type="date" name="action_deadline[]" class="form-control form-control-sm"></td>
        <td class="text-center">
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
