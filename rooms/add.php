<?php
/**
 * Register New Meeting Room
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
        if ($capacity <= 0) $errors[] = "Capacity must be at least 1 person.";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO rooms (name, building, floor, capacity, facilities, description, requires_approval, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name, $building, $floor, $capacity, $facilities, $description, $requiresApproval, $status
                ]);

                $roomId = (int)$pdo->lastInsertId();
                log_audit('room.create', 'room', $roomId, null, ['name' => $name, 'capacity' => $capacity], $userId);

                set_flash('success', "Room '{$name}' created successfully.");
                redirect('rooms/index.php');

            } catch (Exception $e) {
                $errors[] = "Failed to add room: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Add New Room";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-building-add me-2"></i> Add University Room</h4>
        <p class="text-muted mb-0 fs-7">Register a new meeting hall, conference room, or syndicate facility.</p>
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
                    <input type="text" name="name" class="form-control" placeholder="e.g. Syndicate Hall A" required value="<?= e($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-7">Building <span class="text-danger">*</span></label>
                    <input type="text" name="building" class="form-control" placeholder="e.g. Academic Block 1" required value="<?= e($_POST['building'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-7">Floor</label>
                    <input type="text" name="floor" class="form-control" placeholder="e.g. 2nd Floor" value="<?= e($_POST['floor'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Seating Capacity <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" class="form-control" min="1" max="1000" required value="<?= e($_POST['capacity'] ?? '25') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Initial Status</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="maintenance">Under Maintenance</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-center pt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requires_approval" id="chkReqAppr" value="1" checked>
                        <label class="form-check-label fw-semibold fs-7" for="chkReqAppr">
                            Requires Room Manager Approval
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-7">Facilities & Equipment</label>
                <input type="text" name="facilities" class="form-control" placeholder="e.g. Dual 4K Projectors, Cisco Webex Kit, Wireless Mic, Podium" value="<?= e($_POST['facilities'] ?? '') ?>">
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold fs-7">Description / Special Guidelines</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Food/Beverage policy, setup instructions..."><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/rooms/index.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <i class="bi bi-check-circle me-1"></i> Register Room
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
