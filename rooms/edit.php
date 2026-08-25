<?php
/**
 * Edit Meeting Room
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('rooms.manage');

$userId = get_current_user_id();
$roomId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    set_flash('danger', 'Room not found.');
    redirect('rooms/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $building = trim($_POST['building'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 20);
        $facilities = trim($_POST['facilities'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requiresApproval = isset($_POST['requires_approval']) ? 1 : 0;
        $status = $_POST['status'] ?? 'available';

        if (empty($name)) $errors[] = "Room name is required.";
        if (empty($building)) $errors[] = "Building name is required.";
        if ($capacity <= 0) $errors[] = "Capacity must be at least 1.";

        if (empty($errors)) {
            try {
                $stmtUpd = $pdo->prepare("
                    UPDATE rooms SET 
                        name = ?, building = ?, floor = ?, capacity = ?, facilities = ?,
                        description = ?, requires_approval = ?, status = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $name, $building, $floor, $capacity, $facilities, $description,
                    $requiresApproval, $status, $roomId
                ]);

                log_audit('room.update', 'room', $roomId, $room, ['name' => $name, 'status' => $status], $userId);

                set_flash('success', "Room '{$name}' updated successfully.");
                redirect('rooms/index.php');
            } catch (Exception $e) {
                $errors[] = "Update failed: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Edit Room: " . e($room['name']);
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-pencil-square me-2"></i> Edit Room</h4>
        <p class="text-muted mb-0 fs-7">Modify parameters for <strong><?= e($room['name']) ?></strong></p>
    </div>
    <a href="<?= BASE_URL ?>/rooms/index.php" class="btn btn-light border">Cancel</a>
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

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Room / Hall Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= e($room['name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-7">Building <span class="text-danger">*</span></label>
                    <input type="text" name="building" class="form-control" required value="<?= e($room['building']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-7">Floor</label>
                    <input type="text" name="floor" class="form-control" value="<?= e($room['floor']) ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Seating Capacity</label>
                    <input type="number" name="capacity" class="form-control" min="1" max="1000" required value="<?= $room['capacity'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Status</label>
                    <select name="status" class="form-select">
                        <option value="available" <?= $room['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="maintenance" <?= $room['status'] === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                        <option value="unavailable" <?= $room['status'] === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-center pt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requires_approval" id="chkReqAppr" value="1" <?= $room['requires_approval'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold fs-7" for="chkReqAppr">
                            Requires Room Manager Approval
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-7">Facilities & Equipment</label>
                <input type="text" name="facilities" class="form-control" value="<?= e($room['facilities']) ?>">
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold fs-7">Description / Notes</label>
                <textarea name="description" class="form-control" rows="3"><?= e($room['description']) ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/rooms/index.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <i class="bi bi-check-circle me-1"></i> Update Room Details
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
