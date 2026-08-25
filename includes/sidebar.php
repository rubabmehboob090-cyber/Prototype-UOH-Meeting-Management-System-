<?php
/**
 * Role-Based Sidebar Navigation Component (Bootstrap 5 Offcanvas & Desktop)
 * University Meeting Management System
 */

$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
$isActive = function(string $path) use ($currentPath): string {
    return (strpos($currentPath, $path) !== false) ? 'active bg-primary text-white shadow-sm' : 'text-dark';
};

// Check pending approvals count for current user
$pendingApprovalsCount = 0;
if (isset($currentUser['id'])) {
    try {
        $pdo = Database::getConnection();
        // Check if user is an approver with pending meetings
        $stmtAppr = $pdo->prepare("
            SELECT COUNT(*) 
            FROM meeting_approvals ma
            JOIN meetings m ON ma.meeting_id = m.id
            WHERE ma.approver_id = ? AND ma.status = 'pending' AND m.status = 'pending_approval'
        ");
        $stmtAppr->execute([$currentUser['id']]);
        $pendingApprovalsCount = (int)$stmtAppr->fetchColumn();
    } catch (Exception $e) {
        $pendingApprovalsCount = 0;
    }
}
?>

<div class="offcanvas-lg offcanvas-start col-lg-2 bg-white border-end shadow-sm p-0 sidebar-wrapper" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header bg-primary text-white d-lg-none">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="sidebarMenuLabel">
            <i class="bi bi-mortarboard-fill text-warning"></i> <?= APP_SHORT_NAME ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-3 d-flex flex-column justify-content-between h-100">
        <ul class="nav nav-pills flex-column gap-1 mb-auto">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/index.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= (basename($currentPath) == 'index.php' && strpos($currentPath, '/admin/') === false) ? 'active bg-primary text-white shadow-sm' : 'text-dark' ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Section: Meetings -->
            <li class="nav-header text-uppercase fs-8 text-muted fw-bold mt-3 mb-1 px-3">Meetings</li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/meetings/index.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/meetings/index.php') ?>">
                    <i class="bi bi-calendar-event"></i>
                    <span>All Meetings</span>
                </a>
            </li>

            <?php if (has_permission('meetings.create')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/meetings/create.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/meetings/create.php') ?>">
                        <i class="bi bi-plus-circle-fill text-success"></i>
                        <span>New Meeting Request</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: Approvals & Changes -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/approvals/index.php" class="nav-link d-flex align-items-center justify-content-between py-2 px-3 rounded-3 <?= $isActive('/approvals/') ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle"></i>
                        <span>Pending Approvals</span>
                    </div>
                    <?php if ($pendingApprovalsCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $pendingApprovalsCount ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>/changes/index.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/changes/') ?>">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Change Requests</span>
                </a>
            </li>

            <!-- Section: Campus Facilities & Records -->
            <li class="nav-header text-uppercase fs-8 text-muted fw-bold mt-3 mb-1 px-3">Facilities & Records</li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>/rooms/index.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/rooms/') ?>">
                    <i class="bi bi-building"></i>
                    <span>Meeting Rooms</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>/records/index.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/records/') ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Minutes & Records</span>
                </a>
            </li>

            <!-- Section: Administration (RBAC Protected) -->
            <?php if (has_permission('users.manage') || has_permission('structure.manage_departments') || is_super_admin()): ?>
                <li class="nav-header text-uppercase fs-8 text-muted fw-bold mt-3 mb-1 px-3">Administration</li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/admin/users.php') ?>">
                        <i class="bi bi-people"></i>
                        <span>Users & RBAC</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/admin/departments.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/admin/departments.php') ?>">
                        <i class="bi bi-diagram-3"></i>
                        <span>Departments</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/admin/offices.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/admin/offices.php') ?>">
                        <i class="bi bi-briefcase"></i>
                        <span>Offices</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/admin/authorities.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/admin/authorities.php') ?>">
                        <i class="bi bi-shield-check"></i>
                        <span>Approval Authorities</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/admin/events.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/admin/events.php') ?>">
                        <i class="bi bi-calendar3"></i>
                        <span>Calendar Events</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Audit Trail -->
            <?php if (has_permission('audit.view') || is_super_admin()): ?>
                <li class="nav-item mt-2">
                    <a href="<?= BASE_URL ?>/audit/index.php" class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded-3 <?= $isActive('/audit/') ?>">
                        <i class="bi bi-journal-text text-secondary"></i>
                        <span>Audit Trail</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Bottom University Tag -->
        <div class="border-top pt-3 text-center text-muted fs-8">
            <small><?= APP_NAME ?><br>&copy; <?= date('Y') ?> Haripur University</small>
        </div>
    </div>
</div>
