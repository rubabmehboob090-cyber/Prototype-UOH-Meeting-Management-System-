<?php
/**
 * Room Blocks & Maintenance Management
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('rooms.manage');

$userId = get_current_user_id();
$pdo = Database::getConnection();
$errors = [];

// Handle New Block Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_block') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $blockDate = $_POST['block_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (!$roomId || empty($blockDate) || empty($startTime) || empty($endTime) || empty($reason)) {
            $errors[] = "All fields are required to block a room.";
        } elseif ($startTime >= $endTime) {
            $errors[] = "End time must be after start time.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO room_blocks (room_id, block_date, start_time, end_time, reason, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$roomId, $blockDate, $startTime, $endTime, $reason, $userId]);
                $blockId = (int)$pdo->lastInsertId();

                log_audit('room_block.create', 'room_block', $blockId, null, [
                    'room_id' => $roomId,
                    'block_date' => $blockDate,
                    'reason' => $reason
                ], $userId);

                set_flash('success', "Room block created successfully.");
                redirect('rooms/blocks.php');
            } catch (Exception $e) {
                $errors[] = "Failed to create block: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete Block
if (isset($_GET['delete_id']) && verify_csrf()) {
    $deleteId = (int)$_GET['delete_id'];
    $stmtDel = $pdo->prepare("DELETE FROM room_blocks WHERE id = ?");
    $stmtDel->execute([$deleteId]);

    log_audit('room_block.delete', 'room_block', $deleteId, null, null, $userId);
    set_flash('success', "Room block deleted successfully.");
    redirect('rooms/blocks.php');
}

// Fetch active and upcoming blocks
$sql = "
    SELECT rb.*, r.name AS room_name, r.building, u.full_name AS created_by_name
    FROM room_blocks rb
    JOIN rooms r ON rb.room_id = r.id
    JOIN users u ON rb.created_by = u.id
    ORDER BY rb.block_date DESC, rb.start_time ASC
";
$blocks = $pdo->query($sql)->fetchAll();

// Fetch rooms for dropdown
$rooms = $pdo->query("SELECT id, name, building FROM rooms ORDER BY name ASC")->fetchAll();

$pageTitle = "Room Maintenance & Blocks";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-shield-slash me-2"></i> Room Blocks & Maintenance</h4>
        <p class="text-muted mb-0 fs-7">Prevent conflicting reservations during scheduled repairs, exams, or administrative closures.</p>
    </div>
    <a href="<?= BASE_URL ?>/rooms/index.php" class="btn btn-light border">Back to Rooms</a>
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

<div class="row g-4">
    <!-- Block Form -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0">Create Room Block</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_block">

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-7">Target Venue <span class="text-danger">*</span></label>
                        <select name="room_id" class="form-select" required>
                            <option value="">-- Select Room --</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['building']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-7">Date to Block <span class="text-danger">*</span></label>
                        <input type="date" name="block_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold fs-7">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required value="08:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold fs-7">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required value="17:00">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold fs-7">Reason / Event Description <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="e.g. Annual HVAC Maintenance, Senate Examination Seating..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning text-dark fw-semibold w-100">
                        <i class="bi bi-lock me-1"></i> Block Room
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Active Blocks Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="card-title fw-bold text-primary mb-0">Scheduled Blocks & Closures</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($blocks)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-check fs-1 d-block mb-2 text-success opacity-50"></i>
                        <h6 class="fw-semibold">No active room blocks</h6>
                        <p class="fs-7 mb-0">All venues are operating under regular scheduling rules.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-7">
                            <thead class="table-light">
                                <tr>
                                    <th>Room</th>
                                    <th>Date</th>
                                    <th>Time Interval</th>
                                    <th>Reason</th>
                                    <th>Blocked By</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blocks as $b): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($b['room_name']) ?></div>
                                            <small class="text-muted"><?= e($b['building']) ?></small>
                                        </td>
                                        <td class="fw-semibold"><?= format_date($b['block_date']) ?></td>
                                        <td><?= format_time($b['start_time']) ?> &mdash; <?= format_time($b['end_time']) ?></td>
                                        <td style="max-width: 200px;">
                                            <div class="text-truncate" title="<?= e($b['reason']) ?>"><?= e($b['reason']) ?></div>
                                        </td>
                                        <td><?= e($b['created_by_name']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>/rooms/blocks.php?delete_id=<?= $b['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                               onclick="return confirm('Are you sure you want to remove this block?');"
                                               class="btn btn-outline-danger btn-sm py-0 px-2 fs-8">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
