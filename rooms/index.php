<?php
/**
 * University Rooms & Venues Directory
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$pdo = Database::getConnection();

// Fetch rooms with assigned managers
$sql = "
    SELECT r.*, 
           GROUP_CONCAT(u.full_name SEPARATOR ', ') AS manager_names
    FROM rooms r
    LEFT JOIN room_managers rm ON r.id = rm.room_id
    LEFT JOIN users u ON rm.user_id = u.id
    GROUP BY r.id
    ORDER BY r.building ASC, r.name ASC
";
$rooms = $pdo->query($sql)->fetchAll();

$pageTitle = "Meeting Rooms & Facilities";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-building me-2"></i> Meeting Rooms & Facilities</h4>
        <p class="text-muted mb-0 fs-7">Campus conference rooms, syndicate halls, and auditoriums.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/rooms/blocks.php" class="btn btn-outline-warning text-dark fw-semibold btn-sm">
            <i class="bi bi-shield-slash me-1"></i> Room Blocks & Maintenance
        </a>
        <?php if (has_permission('rooms.manage') || is_super_admin()): ?>
            <a href="<?= BASE_URL ?>/rooms/add.php" class="btn btn-primary fw-semibold btn-sm shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add Room
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($rooms)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm text-center py-5 text-muted">
                <i class="bi bi-building-slash fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h5 class="fw-semibold">No rooms have been added yet</h5>
                <p class="fs-7 mb-3">Authorized managers can register campus halls and conference rooms.</p>
                <?php if (has_permission('rooms.manage') || is_super_admin()): ?>
                    <div>
                        <a href="<?= BASE_URL ?>/rooms/add.php" class="btn btn-primary btn-sm">Add First Room</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($rooms as $r): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-primary mb-0 text-truncate" title="<?= e($r['name']) ?>">
                            <?= e($r['name']) ?>
                        </h6>
                        <span class="badge <?= $r['status'] === 'available' ? 'bg-success-subtle text-success' : ($r['status'] === 'maintenance' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger') ?>">
                            <?= ucfirst(e($r['status'])) ?>
                        </span>
                    </div>
                    <div class="card-body p-3 fs-7">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><i class="bi bi-geo-alt"></i> Location:</span>
                            <span class="fw-semibold text-dark"><?= e($r['building']) ?> <?= !empty($r['floor']) ? '(' . e($r['floor']) . ')' : '' ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><i class="bi bi-people"></i> Seating Capacity:</span>
                            <span class="fw-bold text-dark"><?= $r['capacity'] ?> Persons</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block fs-8">Facilities & Equipment:</span>
                            <span class="text-dark fs-8"><?= e($r['facilities'] ?: 'Standard projector, whiteboard, podium') ?></span>
                        </div>
                        <?php if (!empty($r['manager_names'])): ?>
                            <div class="mb-0 pt-2 border-top">
                                <span class="text-muted fs-8 d-block">Designated Manager(s):</span>
                                <span class="fw-semibold text-dark fs-8"><?= e($r['manager_names']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (has_permission('rooms.manage') || is_super_admin()): ?>
                        <div class="card-footer bg-light border-top-0 d-flex justify-content-between align-items-center py-2 px-3">
                            <a href="<?= BASE_URL ?>/rooms/managers.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-link text-decoration-none p-0 fs-8">
                                <i class="bi bi-person-gear"></i> Assign Managers
                            </a>
                            <a href="<?= BASE_URL ?>/rooms/edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-8">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
