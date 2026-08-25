<?php
/**
 * Approval Authorities Configuration
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/audit.php';

require_permission('authorities.manage');

$userId = get_current_user_id();
$pdo = Database::getConnection();
$errors = [];

// Handle Authority Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security token validation failed.";
    } else {
        $scopeType = $_POST['scope_type'] ?? 'department';
        $scopeId = !empty($_POST['scope_id']) ? (int)$_POST['scope_id'] : null;
        $approverId = (int)($_POST['approver_id'] ?? 0);
        $level = (int)($_POST['level'] ?? 1);
        $description = trim($_POST['description'] ?? '');
        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

        if (!$approverId) $errors[] = "Approver user must be selected.";

        if (empty($errors)) {
            try {
                if ($editId > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE approval_authorities 
                        SET scope_type = ?, scope_id = ?, approver_id = ?, level = ?, description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$scopeType, $scopeId, $approverId, $level, $description, $editId]);
                    log_audit('authority.update', 'approval_authority', $editId, null, ['scope_type' => $scopeType, 'level' => $level], $userId);
                    set_flash('success', "Approval authority updated.");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO approval_authorities (scope_type, scope_id, approver_id, level, description, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$scopeType, $scopeId, $approverId, $level, $description]);
                    $newId = (int)$pdo->lastInsertId();
                    log_audit('authority.create', 'approval_authority', $newId, null, ['scope_type' => $scopeType, 'level' => $level], $userId);
                    set_flash('success', "Approval authority added.");
                }
                redirect('admin/authorities/index.php');
            } catch (Exception $e) {
                $errors[] = "Operation failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch all configured authorities
$sql = "
    SELECT aa.*, u.full_name AS approver_name, u.email AS approver_email, r.name AS approver_role,
           CASE 
               WHEN aa.scope_type = 'department' THEN (SELECT name FROM departments WHERE id = aa.scope_id)
               WHEN aa.scope_type = 'office' THEN (SELECT name FROM offices WHERE id = aa.scope_id)
               ELSE 'All University'
           END AS scope_target_name
    FROM approval_authorities aa
    JOIN users u ON aa.approver_id = u.id
    JOIN roles r ON u.role_id = r.id
    ORDER BY aa.scope_type ASC, aa.level ASC
";
$authorities = $pdo->query($sql)->fetchAll();

$users = $pdo->query("SELECT u.id, u.full_name, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.status = 'active' ORDER BY u.full_name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$offices = $pdo->query("SELECT id, name FROM offices WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = "Approval Authorities";
include APP_ROOT . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-shield-check me-2"></i> Approval Authorities Configuration</h4>
        <p class="text-muted mb-0 fs-7">Configure statutory sign-off chains, hierarchical tiers, and review scopes.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#authModal" onclick="resetAuthForm()">
        <i class="bi bi-plus-circle me-1"></i> Add Authority
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
                        <th>Scope</th>
                        <th>Target Department / Office</th>
                        <th>Designated Approver</th>
                        <th>Hierarchy Tier</th>
                        <th>Description / Note</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authorities as $a): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= ucfirst(e($a['scope_type'])) ?> Scope
                                </span>
                            </td>
                            <td class="fw-semibold text-dark"><?= e($a['scope_target_name']) ?></td>
                            <td>
                                <div class="fw-bold"><?= e($a['approver_name']) ?></div>
                                <small class="text-muted"><?= e($a['approver_role']) ?> &bull; <?= e($a['approver_email']) ?></small>
                            </td>
                            <td><span class="badge bg-primary text-white">Tier Level <?= $a['level'] ?></span></td>
                            <td class="text-muted fs-8"><?= e($a['description'] ?: 'Standard statutory review') ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8"
                                        onclick="editAuth(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">
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

<!-- Add / Edit Authority Modal -->
<div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_id" id="auth_edit_id" value="0">

                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-primary" id="authModalTitle">Add Approval Authority</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body fs-7">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scope Type <span class="text-danger">*</span></label>
                        <select name="scope_type" id="auth_scope_type" class="form-select" onchange="toggleScopeInputs()">
                            <option value="department">Department Specific</option>
                            <option value="office">Office Specific</option>
                            <option value="university">University-Wide (Registrar / VC)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="scope_dept_container">
                        <label class="form-label fw-semibold">Target Department</label>
                        <select name="scope_id" id="auth_scope_dept" class="form-select">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Designated Reviewer / Approver <span class="text-danger">*</span></label>
                        <select name="approver_id" id="auth_approver_id" class="form-select" required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['role_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Approval Level <span class="text-danger">*</span></label>
                            <input type="number" name="level" id="auth_level" class="form-control" value="1" min="1" max="5" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role Description / Scope Details</label>
                        <input type="text" name="description" id="auth_desc" class="form-control" placeholder="e.g. Dean of Faculty Official Endorsement">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Authority</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleScopeInputs() {
    const scope = document.getElementById('auth_scope_type').value;
    const deptBox = document.getElementById('scope_dept_container');
    if (scope === 'university') {
        deptBox.classList.add('d-none');
    } else {
        deptBox.classList.remove('d-none');
    }
}

function resetAuthForm() {
    document.getElementById('auth_edit_id').value = '0';
    document.getElementById('authModalTitle').innerText = 'Add Approval Authority';
    document.getElementById('auth_scope_type').value = 'department';
    document.getElementById('auth_scope_dept').value = '';
    document.getElementById('auth_approver_id').value = '';
    document.getElementById('auth_level').value = '1';
    document.getElementById('auth_desc').value = '';
    toggleScopeInputs();
}

function editAuth(data) {
    document.getElementById('auth_edit_id').value = data.id;
    document.getElementById('authModalTitle').innerText = 'Edit Approval Authority';
    document.getElementById('auth_scope_type').value = data.scope_type;
    document.getElementById('auth_scope_dept').value = data.scope_id || '';
    document.getElementById('auth_approver_id').value = data.approver_id;
    document.getElementById('auth_level').value = data.level;
    document.getElementById('auth_desc').value = data.description || '';
    toggleScopeInputs();
    new bootstrap.Modal(document.getElementById('authModal')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
