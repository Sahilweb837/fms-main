<?php
/**
 * BRANCH USERS/STAFF MANAGEMENT — for Branch Admins
 * Shared template; include from each industry folder's users.php
 * Required: $btype_key
 */
require_once __DIR__ . '/auth.php';
checkAccess(['admin', 'super_admin']);
// Removed strict business_type check
// if (!isSuperAdmin() && $_SESSION['business_type'] !== $btype_key) {
//     header("Location: ../index.php"); exit();
// }
include __DIR__ . '/header.php';

$msg = "";
$bid = (int)($_SESSION['branch_id'] ?? 0);

// ── ADD EMPLOYEE ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $uname      = trim($_POST['username']);
    $full_name  = trim($_POST['full_name'] ?? '');
    $plain_pass = $_POST['password'];
    $hashed     = password_hash($plain_pass, PASSWORD_DEFAULT);
    $emp_id     = generateEmployeeId($conn, $btype_key, 'employee');

    try {
        $stmt = $conn->prepare("INSERT INTO users (employee_id, full_name, username, password, plain_password, role, branch_id, created_by) VALUES (?,?,?,?,?,'employee',?,?)");
        $stmt->bind_param("sssssii", $emp_id, $full_name, $uname, $hashed, $plain_pass, $bid, $_SESSION['user_id']);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], "Add Employee", "Created: $uname ($emp_id).");
            $msg = "<div class='alert alert-success border-0 rounded-3 animate-up'><i class='fas fa-check-circle me-2'></i><strong>$uname</strong> added! ID: <code style='background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:4px;'>$emp_id</code></div>";
        } else {
            $msg = "<div class='alert alert-danger border-0 rounded-3'>Username already exists.</div>";
        }
    } catch (Exception $e) {
        $msg = "<div class='alert alert-danger border-0 rounded-3'>Error: ".htmlspecialchars($e->getMessage())."</div>";
    }
}

// ── DELETE ─────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    if ($did == $_SESSION['user_id']) {
        $msg = "<div class='alert alert-danger border-0 rounded-3'>Cannot delete yourself.</div>";
    } else {
        $u = $conn->query("SELECT username, role FROM users WHERE id=$did AND branch_id=$bid")->fetch_assoc();
        if ($u && $u['role'] === 'employee') {
            $conn->query("DELETE FROM users WHERE id=$did");
            logActivity($conn, $_SESSION['user_id'], "Delete Employee", "Removed: {$u['username']}");
            $msg = "<div class='alert alert-success border-0 rounded-3'><i class='fas fa-trash me-2'></i>Employee removed.</div>";
        } else {
            $msg = "<div class='alert alert-danger border-0 rounded-3'>Cannot remove this user.</div>";
        }
    }
}

$employees = $conn->query("SELECT * FROM users WHERE branch_id=$bid AND role='employee' ORDER BY id DESC");
?>
<div class="animate-up">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-user-tie me-2 text-primary"></i>My Branch Staff</h2>
        <p class="text-muted mb-0 small">Manage employees for your branch. Auto-generates Employee IDs.</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addEmpModal">
        <i class="fas fa-user-plus me-2"></i>Add Employee
    </button>
</div>
<?php echo $msg; ?>
<div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4 py-3">Employee</th>
                        <th class="py-3">Employee ID</th>
                        <th class="py-3">Password</th>
                        <th class="py-3">Created</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($employees && $employees->num_rows > 0): while($e = $employees->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle fw-bold" style="width:34px;height:34px;font-size:13px;flex-shrink:0;">
                                <?php echo strtoupper(substr($e['username'],0,1)); ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark small"><?php echo htmlspecialchars($e['username']); ?></div>
                                <div class="text-muted" style="font-size:0.7rem;"><?php echo htmlspecialchars($e['full_name'] ?? ''); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><code style="color:var(--first-color);font-size:0.8rem;"><?php echo htmlspecialchars($e['employee_id'] ?? '—'); ?></code></td>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <input type="password" class="form-control form-control-sm border-0 bg-transparent p-0 fw-bold" value="<?php echo htmlspecialchars($e['plain_password'] ?? '••••••'); ?>" readonly id="pw_<?php echo $e['id']; ?>" style="max-width:110px;">
                            <button class="btn btn-sm btn-link text-muted p-0" onclick="tp(<?php echo $e['id']; ?>)"><i class="fas fa-eye" id="pi_<?php echo $e['id']; ?>"></i></button>
                        </div>
                    </td>
                    <td class="small text-muted"><?php echo date('d M Y', strtotime($e['created_at'])); ?></td>
                    <td class="pe-4 text-end">
                        <?php if($e['id'] != $_SESSION['user_id']): ?>
                        <a href="?delete=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Remove employee?')">
                            <i class="fas fa-user-slash me-1"></i>Remove
                        </a>
                        <?php else: ?>
                        <span class="badge bg-success-subtle text-success border rounded-pill px-3">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">No employees added yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmpModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header border-0 p-4 pb-2">
            <div>
                <h5 class="fw-bold mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Add New Employee</h5>
                <small class="text-muted">ID will be auto-generated (e.g. <?php echo strtoupper(substr($btype_key,0,3)); ?>-EMP-XXX)</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="Employee full name">
            </div>
            <div class="mb-3">
                <label class="form-label">Username / Login ID <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="username" id="emp_user" class="form-control" placeholder="Unique login" required>
                    <button class="btn btn-outline-primary" type="button" onclick="genU()"><i class="fas fa-magic"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="password" id="emp_pass" class="form-control" placeholder="Set password" required>
                    <button class="btn btn-outline-primary" type="button" onclick="genP()"><i class="fas fa-key"></i></button>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow"><i class="fas fa-save me-2"></i>Create Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function genU() {
    const a = 'abcdefghijklmnopqrstuvwxyz';
    document.getElementById('emp_user').value = 'emp_' + a[Math.floor(Math.random()*26)] + a[Math.floor(Math.random()*26)] + Math.floor(1000+Math.random()*9000);
}
function genP() {
    const c = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#";
    let p = "";
    for (let i = 0; i < 10; i++) p += c[Math.floor(Math.random()*c.length)];
    document.getElementById('emp_pass').value = p;
}
function tp(id) {
    const i = document.getElementById('pw_'+id);
    const c = document.getElementById('pi_'+id);
    i.type = i.type === 'password' ? 'text' : 'password';
    c.classList.toggle('fa-eye'); c.classList.toggle('fa-eye-slash');
}
</script>
<?php include '../includes/footer.php'; ?>
