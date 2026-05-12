<?php
require_once '../includes/auth.php';
checkAccess(['super_admin', 'admin']);
include '../includes/header.php';

$message = "";
$is_super = isSuperAdmin();
$my_branch = $_SESSION['branch_id'];

// ── Get current admin's business type (needed for ID generation)
$my_btype = $_SESSION['business_type'] ?? 'other';
if ($is_super && !empty($_POST['branch_id'])) {
    // When super admin creates a user, use that branch's type
    $bt_res = $conn->prepare("SELECT business_type FROM branches WHERE id = ?");
    $bt_res->bind_param("i", $_POST['branch_id']);
    $bt_res->execute();
    $bt_row = $bt_res->get_result()->fetch_assoc();
    $form_btype = $bt_row['business_type'] ?? 'other';
} else {
    $form_btype = $my_btype;
}

// ── Add User ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $uname      = trim($_POST['username']);
    $full_name  = trim($_POST['full_name'] ?? '');
    $plain_pass = $_POST['password'];
    $hashed     = password_hash($plain_pass, PASSWORD_DEFAULT);
    $urole      = $_POST['role'];
    $ubranch    = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;

    // Access control: admins can only create employees for their own branch
    if (!$is_super) {
        $urole   = 'employee';   // force
        $ubranch = $my_branch;   // force their own branch
    } elseif ($urole !== 'super_admin' && empty($ubranch)) {
        $message = "<div class='alert alert-danger border-0 rounded-3'>Error: You must assign a branch when creating an Admin or Employee.</div>";
    }

    if (empty($message)) {
        // Get business type for the target branch (for ID generation)
        $target_btype = 'other';
        if ($ubranch) {
            $btr = $conn->prepare("SELECT business_type FROM branches WHERE id = ?");
            $btr->bind_param("i", $ubranch);
            $btr->execute();
            $btr_row = $btr->get_result()->fetch_assoc();
            $target_btype = $btr_row['business_type'] ?? 'other';
        }

        // Generate Employee ID
        $emp_id = ($urole === 'super_admin') ? 'SUP-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT)
                                            : generateEmployeeId($conn, $target_btype, $urole);

        try {
            $stmt = $conn->prepare("INSERT INTO users (employee_id, full_name, username, password, plain_password, role, branch_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssii", $emp_id, $full_name, $uname, $hashed, $plain_pass, $urole, $ubranch, $_SESSION['user_id']);

            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user_id'], "Create User", "Created $urole: $uname ($emp_id) for branch $ubranch.");
                $message = "<div class='alert alert-success border-0 rounded-3 shadow-sm animate-up'>
                    <i class='fas fa-check-circle me-2'></i> User <strong>$uname</strong> created!
                    <code class='ms-2' style='background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:6px;'>$emp_id</code>
                </div>";
            } else {
                $message = "<div class='alert alert-danger border-0 rounded-3'>Username already exists.</div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger border-0 rounded-3'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// ── Delete User ────────────────────────────────────────────────────
if (isset($_GET['delete']) && ($is_super || canManageUsers())) {
    $del_id = (int)$_GET['delete'];
    if ($del_id == $_SESSION['user_id']) {
        $message = "<div class='alert alert-danger border-0 rounded-3'>You cannot delete yourself.</div>";
    } else {
        $check = $conn->query("SELECT role, username FROM users WHERE id = $del_id")->fetch_assoc();
        if ($check) {
            // Admins can only delete employees from their branch
            if (!$is_super && $check['role'] !== 'employee') {
                $message = "<div class='alert alert-danger border-0 rounded-3'>You can only delete employees.</div>";
            } else {
                try {
                    // Nullify references before deleting
                    $conn->query("UPDATE students SET added_by = NULL WHERE added_by = $del_id");
                    $conn->query("UPDATE fees SET collected_by = NULL WHERE collected_by = $del_id");
                    $conn->query("DELETE FROM users WHERE id = $del_id");
                    logActivity($conn, $_SESSION['user_id'], "Delete User", "Deleted: {$check['username']}.");
                    $message = "<div class='alert alert-success border-0 rounded-3'><i class='fas fa-trash me-2'></i>User removed.</div>";
                } catch (Throwable $e) {
                    $message = "<div class='alert alert-danger border-0 rounded-3'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
        }
    }
}

// ── Verify Pro (Restore Subscription) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'verify_pro') {
    $branch_id = (int)$_POST['branch_id'];
    $utr_id = $_POST['utr_id'];
    $stmt = $conn->prepare("UPDATE branches SET is_pro = 1, pro_utr_id = ? WHERE id = ?");
    $stmt->bind_param("si", $utr_id, $branch_id);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], "Verify Pro", "Restored branch ID $branch_id with UTR: $utr_id");
        $message = "<div class='alert alert-success border-0 rounded-3 shadow-sm animate-up'><i class='fas fa-check-circle me-2'></i> Subscription Restored (PRO Verified).</div>";
    }
}

// ── Fetch Users ────────────────────────────────────────────────────
if ($is_super) {
    $users = $conn->query("SELECT u.*, b.branch_name, b.business_type, b.is_pro, b.created_at as branch_created_at FROM users u LEFT JOIN branches b ON u.branch_id = b.id ORDER BY u.id DESC");
} else {
    $bid = (int)$my_branch;
    $users = $conn->query("SELECT u.*, b.branch_name, b.business_type, b.is_pro, b.created_at as branch_created_at FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.branch_id = $bid AND u.role = 'employee' ORDER BY u.id DESC");
}

// ── Branches dropdown (super admin only)
$branches_list = $is_super ? $conn->query("SELECT id, branch_name, business_type FROM branches WHERE is_active=1 ORDER BY branch_name") : null;
?>

<div class="animate-up">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <?php echo $is_super ? 'System Users & Access Control' : 'My Branch Staff'; ?>
        </h2>
        <p class="text-muted mb-0 small">
            <?php echo $is_super ? 'Create and manage admins & employees for all branches.' : 'Create and manage employees for your branch.'; ?>
        </p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-2"></i><?php echo $is_super ? 'Provision User' : 'Add Employee'; ?>
    </button>
</div>

<?php echo $message; ?>

<div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4 py-3">Identity</th>
                        <th class="py-3">Role & ID</th>
                        <th class="py-3">Branch</th>
                        <th class="py-3">Password</th>
                        <th class="py-3">Created</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($users && $users->num_rows > 0): while($row = $users->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle fw-bold" style="width:38px;height:38px;font-size:14px;flex-shrink:0;">
                                <?php echo strtoupper(substr($row['username'],0,1)); ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['username']); ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($row['full_name'] ?? '—'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                        $rbadge = ['super_admin'=>'bg-danger','admin'=>'bg-warning text-dark','employee'=>'bg-info text-dark'];
                        $rc = $row['role']; $bg = $rbadge[$rc] ?? 'bg-secondary';
                        echo "<span class='badge $bg rounded-pill px-3 mb-1'>".strtoupper(str_replace('_',' ',$rc))."</span>";
                        ?>
                        <div><code style="font-size:0.7rem;color:var(--first-color);"><?php echo htmlspecialchars($row['employee_id'] ?? '—'); ?></code></div>
                    </td>
                    <td>
                        <?php if($row['branch_name']): ?>
                            <?php 
                                $sub_badge = "";
                                $days_active = (time() - strtotime($row['branch_created_at'])) / 86400;
                                if ($row['is_pro']) {
                                    $sub_badge = '<span class="badge bg-success-subtle text-success border border-success rounded-pill ms-1" style="font-size:0.55rem;"><i class="fas fa-crown me-1"></i>PRO</span>';
                                } elseif ($days_active > 30) {
                                    $sub_badge = '<span class="badge bg-danger-subtle text-danger border border-danger rounded-pill ms-1" style="font-size:0.55rem;"><i class="fas fa-ban me-1"></i>SUB STOPPED</span>';
                                } else {
                                    $sub_badge = '<span class="badge bg-warning-subtle text-warning border border-warning rounded-pill ms-1" style="font-size:0.55rem;"><i class="fas fa-clock me-1"></i>TRIAL</span>';
                                }
                            ?>
                            <div class="small fw-semibold text-dark"><i class="fas fa-building me-1 text-primary" style="font-size:0.75rem;"></i><?php echo htmlspecialchars($row['branch_name']); ?> <?php echo $sub_badge; ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?php echo ucfirst($row['business_type'] ?? ''); ?></div>
                        <?php else: ?>
                            <span class="badge bg-dark rounded-pill px-3"><i class="fas fa-globe me-1"></i>Universal</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width:140px;">
                            <input type="password" class="form-control border-0 bg-transparent fw-bold p-0" value="<?php echo htmlspecialchars($row['plain_password'] ?? '••••••••'); ?>" readonly id="pass_<?php echo $row['id']; ?>">
                            <button class="btn btn-sm btn-link text-primary p-0 ms-2" onclick="togglePass(<?php echo $row['id']; ?>)">
                                <i class="fas fa-eye" id="icon_<?php echo $row['id']; ?>"></i>
                            </button>
                        </div>
                    </td>
                    <td class="small text-muted"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                    <td class="pe-4 text-end">
                        <?php if($_SESSION['user_id'] == $row['id']): ?>
                            <span class="badge bg-success-subtle text-success border rounded-pill px-3">You</span>
                        <?php elseif($is_super || ($row['role'] === 'employee')): ?>
                            <?php if ($is_super && $row['branch_id'] && !$row['is_pro']): ?>
                                <button class="btn btn-outline-success btn-sm rounded-pill px-3 me-1" onclick="verifyPro(<?php echo $row['branch_id']; ?>)">
                                    <i class="fas fa-unlock me-1"></i>Restore
                                </button>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Remove this user?');">
                                <i class="fas fa-user-slash me-1"></i>Revoke
                            </a>
                        <?php else: ?>
                            <span class="badge bg-light text-muted rounded-pill px-3 border">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST" id="addUserForm">
        <input type="hidden" name="action" value="add">
        <div class="modal-header border-0 p-4 pb-2">
            <div>
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-plus me-2 text-primary"></i>
                    <?php echo $is_super ? 'Provision New User' : 'Add New Employee'; ?>
                </h5>
                <small class="text-muted">An Employee ID will be auto-generated based on the branch type.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. John Smith">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username / Login ID <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="username" id="new_username" class="form-control" placeholder="Unique login ID" required>
                        <button class="btn btn-outline-primary" type="button" onclick="generateUsername()"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="password" id="new_password" class="form-control" placeholder="Set password" required>
                        <button class="btn btn-outline-primary" type="button" onclick="generatePassword()"><i class="fas fa-key"></i></button>
                    </div>
                    <div class="text-muted mt-1" style="font-size:0.72rem;"><i class="fas fa-lock me-1"></i>Stored encrypted. Plain shown in table for admin reference.</div>
                </div>

                <?php if($is_super): ?>
                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" id="role_select" onchange="onRoleChange()" required>
                        <option value="employee" selected>Employee / Staff</option>
                        <option value="admin">Branch Admin</option>
                        <option value="super_admin">Super Admin (Root)</option>
                    </select>
                </div>
                <div class="col-md-12" id="branch_field">
                    <label class="form-label">Assign to Branch <span class="text-danger">*</span></label>
                    <select name="branch_id" id="branch_select" class="form-select">
                        <option value="">— Universal Access (Super Admin Only) —</option>
                        <?php if($branches_list): while($b = $branches_list->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>" data-type="<?php echo $b['business_type']; ?>">
                            <?php echo htmlspecialchars($b['branch_name']); ?> (<?php echo ucfirst($b['business_type']); ?>)
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <div class="mt-2 p-2 rounded-3" style="background:var(--first-color-light);font-size:0.78rem;" id="id_preview">
                        <i class="fas fa-id-badge me-1" style="color:var(--first-color);"></i>
                        Auto-generated ID will appear here after saving.
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="role" value="employee">
                <div class="col-md-12">
                    <div class="p-3 rounded-3" style="background:var(--first-color-light);">
                        <i class="fas fa-info-circle me-2" style="color:var(--first-color);"></i>
                        <strong>Auto-assignment:</strong> This employee will be assigned to your branch with an auto-generated ID (<?php echo strtoupper(substr($my_btype,0,3)); ?>-EMP-XXX).
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow"><i class="fas fa-check me-2"></i>Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function generateUsername() {
    const chars = 'abcdefghijklmnopqrstuvwxyz';
    const rand4 = Math.floor(1000 + Math.random() * 9000);
    const word = chars.charAt(Math.floor(Math.random()*26)) + chars.charAt(Math.floor(Math.random()*26));
    document.getElementById('new_username').value = 'user_' + word + rand4;
}
function generatePassword() {
    const c = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#";
    let p = "";
    for (let i = 0; i < 10; i++) p += c[Math.floor(Math.random() * c.length)];
    document.getElementById('new_password').value = p;
}
function togglePass(id) {
    const inp = document.getElementById('pass_' + id);
    const ico = document.getElementById('icon_' + id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.classList.toggle('fa-eye'); ico.classList.toggle('fa-eye-slash');
}
function onRoleChange() {
    const role = document.getElementById('role_select').value;
    const bf   = document.getElementById('branch_field');
    const bs   = document.getElementById('branch_select');
    if (role === 'super_admin') {
        bs.value = ''; bs.disabled = true;
    } else {
        bs.disabled = false;
    }
    updateIdPreview();
}
function updateIdPreview() {
    const role = (document.getElementById('role_select') || {value:'employee'}).value;
    const branchSel = document.getElementById('branch_select');
    const btype = branchSel && branchSel.options[branchSel.selectedIndex] ? branchSel.options[branchSel.selectedIndex].dataset.type || 'gen' : 'gen';
    const prefixes = {it_institution:'ITI',company:'CMP',school:'SCH',college:'COL',other:'GEN'};
    const p = prefixes[btype] || 'GEN';
    const r = role === 'admin' ? 'ADM' : (role === 'super_admin' ? 'SUP' : 'EMP');
    const prev = document.getElementById('id_preview');
    if (prev) prev.innerHTML = `<i class="fas fa-id-badge me-1" style="color:var(--first-color);"></i> Auto-ID: <strong>${p}-${r}-XXX</strong>`;
}
<?php if($is_super): ?>
document.getElementById('branch_select').addEventListener('change', updateIdPreview);
document.getElementById('role_select').addEventListener('change', onRoleChange);

function verifyPro(branch_id) {
    document.getElementById('verify_branch_id').value = branch_id;
    new bootstrap.Modal(document.getElementById('verifyProModal')).show();
}
<?php endif; ?>
</script>

<?php if($is_super): ?>
<!-- Verify PRO Modal for Restoring Subscription -->
<div class="modal fade" id="verifyProModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="verify_pro">
                <input type="hidden" name="branch_id" id="verify_branch_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-check-circle me-2 text-success"></i>Restore Sub</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Enter the UTR / Transaction ID provided to restore this branch's subscription (Unlock PRO features).</p>
                    <input type="text" name="utr_id" class="form-control rounded-3" placeholder="e.g. UTR123456789" required>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-success w-100 rounded-pill shadow">Restore Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
