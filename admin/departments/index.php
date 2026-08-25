<?php
/**
 * Department Management
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

// Handle Department Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $hodId = !empty($_POST['hod_id']) ? (int)$_POST['hod_id'] : null;
        $status = $_POST['status'] ?? 'active';
        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

        if (empty($name)) $errors[] = "Department name is required.";
        if (empty($code)) $errors[] = "Department code is required.";

        if (empty($errors)) {
            try {
                if ($editId > 0) {
                    $stmt = $pdo->prepare("UPDATE departments SET name = ?, code = ?, hod_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $code, $hodId, $status, $editId]);
                    log_audit('department.update', 'department', $editId, null, ['name' => $name], $userId);
                    set_flash('success', "Department '{$name}' updated.");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO departments (name, code, hod_id, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$name, $code, $hodId, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    log_audit('department.create', 'department', $newId, null, ['name' => $name], $userId);
                    set_flash('success', "Department '{$name}' created.");
                }
                redirect('admin/departments/index.php');
            } catch (Exception $e) {
                $errors[] = "Operation failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch all departments with HOD names
$sql = "
    SELECT d.*, u.full_name AS hod_name, u.email AS hod_email,
           (SELECT COUNT(*) FROM users WHERE department_id = d.id) AS faculty_count
    FROM departments d
    LEFT JOIN users u ON d.hod_id = u.id
    ORDER BY d.name ASC
";
$departments = $pdo->query($sql)->fetchAll();

// Fetch potential HOD users
$facultyUsers = $pdo->query("SELECT id, full_name, designation FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

$pageTitle = "Academic Departments";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-mortarboard me-2"></i> Academic Departments</h4>
        <p class="text-muted mb-0 fs-7">Manage teaching faculties, academic departments, and designated HODs.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="resetDeptForm()">
        <i class="bi bi-plus-circle me-1"></i> Add Department
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
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Head of Department (HOD)</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $d): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($d['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($d['code']) ?></span></td>
                            <td>
                                <?php if ($d['hod_name']): ?>
                                    <div class="fw-semibold"><?= e($d['hod_name']) ?></div>
                                    <small class="text-muted"><?= e($d['hod_email']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= $d['faculty_count'] ?> faculty/staff</span></td>
                            <td>
                                <span class="badge <?= $d['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                    <?= ucfirst(e($d['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8" 
                                        onclick="editDept(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)">
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

<!-- Add / Edit Department Modal -->
<div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_id" id="dept_edit_id" value="0">

                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-primary" id="deptModalTitle">Add Academic Department</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body fs-7">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="dept_name" class="form-control" required placeholder="e.g. Department of Computer Science">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Dept Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="dept_code" class="form-control" required placeholder="e.g. CS">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="dept_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Head of Department (HOD)</label>
                        <select name="hod_id" id="dept_hod_id" class="form-select">
                            <option value="">-- Select Faculty / HOD --</option>
                            <?php foreach ($facultyUsers as $fu): ?>
                                <option value="<?= $fu['id'] ?>"><?= e($fu['full_name']) ?> (<?= e($fu['designation'] ?: 'Faculty') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetDeptForm() {
    document.getElementById('dept_edit_id').value = '0';
    document.getElementById('deptModalTitle').innerText = 'Add Academic Department';
    document.getElementById('dept_name').value = '';
    document.getElementById('dept_code').value = '';
    document.getElementById('dept_hod_id').value = '';
    document.getElementById('dept_status').value = 'active';
}

function editDept(data) {
    document.getElementById('dept_edit_id').value = data.id;
    document.getElementById('deptModalTitle').innerText = 'Edit Department: ' + data.name;
    document.getElementById('dept_name').value = data.name;
    document.getElementById('dept_code').value = data.code;
    document.getElementById('dept_hod_id').value = data.hod_id || '';
    document.getElementById('dept_status').value = data.status;
    new bootstrap.Modal(document.getElementById('deptModal')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
