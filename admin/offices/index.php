<?php
/**
 * Administrative Offices Management
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('departments.manage');

$userId = get_current_user_id();
$pdo = Database::getConnection();
$errors = [];

// Handle Office Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $headId = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
        $status = $_POST['status'] ?? 'active';
        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

        if (empty($name)) $errors[] = "Office name is required.";
        if (empty($code)) $errors[] = "Office code is required.";

        if (empty($errors)) {
            try {
                if ($editId > 0) {
                    $stmt = $pdo->prepare("UPDATE offices SET name = ?, code = ?, head_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $code, $headId, $status, $editId]);
                    log_audit('office.update', 'office', $editId, null, ['name' => $name], $userId);
                    set_flash('success', "Office '{$name}' updated.");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO offices (name, code, head_id, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$name, $code, $headId, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    log_audit('office.create', 'office', $newId, null, ['name' => $name], $userId);
                    set_flash('success', "Office '{$name}' created.");
                }
                redirect('admin/offices/index.php');
            } catch (Exception $e) {
                $errors[] = "Operation failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch all offices
$sql = "
    SELECT o.*, u.full_name AS head_name, u.email AS head_email,
           (SELECT COUNT(*) FROM users WHERE office_id = o.id) AS staff_count
    FROM offices o
    LEFT JOIN users u ON o.head_id = u.id
    ORDER BY o.name ASC
";
$offices = $pdo->query($sql)->fetchAll();

// Fetch potential Head of Office users
$staffUsers = $pdo->query("SELECT id, full_name, designation FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

$pageTitle = "Administrative Offices";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-briefcase me-2"></i> Administrative Offices</h4>
        <p class="text-muted mb-0 fs-7">Manage statutory branches, directorates, and administrative sections.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#officeModal" onclick="resetOfficeForm()">
        <i class="bi bi-plus-circle me-1"></i> Add Office
    </button>
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
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-7">
                <thead class="table-light">
                    <tr>
                        <th>Office / Section Name</th>
                        <th>Code</th>
                        <th>Head of Office</th>
                        <th>Staff Count</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offices as $o): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($o['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($o['code']) ?></span></td>
                            <td>
                                <?php if ($o['head_name']): ?>
                                    <div class="fw-semibold"><?= e($o['head_name']) ?></div>
                                    <small class="text-muted"><?= e($o['head_email']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= $o['staff_count'] ?> staff</span></td>
                            <td>
                                <span class="badge <?= $o['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                    <?= ucfirst(e($o['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8" 
                                        onclick="editOffice(<?= htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Office Modal -->
<div class="modal fade" id="officeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_id" id="office_edit_id" value="0">

                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-primary" id="officeModalTitle">Add Administrative Office</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body fs-7">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Office Full Title <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="office_name" class="form-control" required placeholder="e.g. Office of the Registrar">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Office Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="office_code" class="form-control" required placeholder="e.g. REG">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="office_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Head of Office / In-Charge</label>
                        <select name="head_id" id="office_head_id" class="form-select">
                            <option value="">-- Select Officer --</option>
                            <?php foreach ($staffUsers as $su): ?>
                                <option value="<?= $su['id'] ?>"><?= e($su['full_name']) ?> (<?= e($su['designation'] ?: 'Officer') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Office</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetOfficeForm() {
    document.getElementById('office_edit_id').value = '0';
    document.getElementById('officeModalTitle').innerText = 'Add Administrative Office';
    document.getElementById('office_name').value = '';
    document.getElementById('office_code').value = '';
    document.getElementById('office_head_id').value = '';
    document.getElementById('office_status').value = 'active';
}

function editOffice(data) {
    document.getElementById('office_edit_id').value = data.id;
    document.getElementById('officeModalTitle').innerText = 'Edit Office: ' + data.name;
    document.getElementById('office_name').value = data.name;
    document.getElementById('office_code').value = data.code;
    document.getElementById('office_head_id').value = data.head_id || '';
    document.getElementById('office_status').value = data.status;
    new bootstrap.Modal(document.getElementById('officeModal')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
