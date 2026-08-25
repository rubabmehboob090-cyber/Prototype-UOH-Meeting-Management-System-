<?php
/**
 * Room Manager Assignments
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

// Handle Assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $selectedManagers = array_filter(array_map('intval', (array)($_POST['managers'] ?? [])));

        try {
            $pdo->beginTransaction();

            $stmtDel = $pdo->prepare("DELETE FROM room_managers WHERE room_id = ?");
            $stmtDel->execute([$roomId]);

            $stmtIns = $pdo->prepare("INSERT INTO room_managers (room_id, user_id, assigned_at) VALUES (?, ?, NOW())");
            foreach ($selectedManagers as $mId) {
                $stmtIns->execute([$roomId, $mId]);
            }

            log_audit('room.assign_managers', 'room', $roomId, null, ['managers' => $selectedManagers], $userId);

            $pdo->commit();
            set_flash('success', "Designated managers for '{$room['name']}' updated successfully.");
            redirect('rooms/index.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to update room managers: " . $e->getMessage();
        }
    }
}

// Fetch current managers
$currentManagers = $pdo->prepare("SELECT user_id FROM room_managers WHERE room_id = ?");
$currentManagers->execute([$roomId]);
$assignedManagerIds = $currentManagers->fetchAll(PDO::FETCH_COLUMN);

// Fetch all staff users
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.designation, r.name AS role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

$pageTitle = "Assign Managers: " . e($room['name']);
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-person-gear me-2"></i> Room Managers</h4>
        <p class="text-muted mb-0 fs-7">Designate managers authorized to review reservations for <strong><?= e($room['name']) ?></strong></p>
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

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?= csrf_field() ?>

            <p class="text-muted fs-7 mb-3">
                Select university personnel who have managerial oversight over this venue:
            </p>

            <div class="row g-3 mb-4" style="max-height: 350px; overflow-y: auto;">
                <?php foreach ($users as $u): ?>
                    <div class="col-md-6">
                        <div class="p-2 border rounded d-flex align-items-center gap-2 bg-light">
                            <input class="form-check-input ms-1" type="checkbox" name="managers[]" value="<?= $u['id'] ?>" id="mgr_<?= $u['id'] ?>" <?= in_array($u['id'], $assignedManagerIds) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-7 w-100 cursor-pointer" for="mgr_<?= $u['id'] ?>">
                                <span class="fw-bold text-dark d-block"><?= e($u['full_name']) ?></span>
                                <small class="text-muted"><?= e($u['designation'] ?: $u['role_name']) ?> &bull; <?= e($u['email']) ?></small>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/rooms/index.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <i class="bi bi-check-circle me-1"></i> Save Manager Assignments
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
