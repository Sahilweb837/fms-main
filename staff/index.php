<?php
require_once '../includes/auth.php';
checkAccess(['super_admin', 'admin']); // Branch admins and super admins can access
include '../includes/header.php';

$message = "";

// Handle Add Employee (Only branch admins creating employees for their branch)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username']);
    // Generate a simple random password like "emp1234"
    $plain_pass = 'emp' . rand(1000, 9999);
    $password_hash = password_hash($plain_pass, PASSWORD_DEFAULT);
    $role = 'employee'; // Forced
    
    // Assign to current admin's branch. Super admin should use the main users.php to assign to specific branches.
    $target_branch = $_SESSION['branch_id']; 
    
    if (empty($target_branch) && !isSuperAdmin()) {
        $message = "<div class='alert alert-danger'>Error: You are not assigned to a branch.</div>";
    } else {
        // Generate Dynamic Employee ID
        $bt_query = $conn->query("SELECT business_type FROM branches WHERE id = " . (int)$target_branch);
        $btype = ($bt_query && $row = $bt_query->fetch_assoc()) ? $row['business_type'] : 'OTH';
        $prefix = strtoupper(substr($btype, 0, 3));
        $emp_id = "EMP-" . $prefix . "-" . rand(1000, 9999);

        $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, plain_password, role, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $emp_id, $username, $password_hash, $plain_pass, $role, $target_branch);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], "Add Staff", "Created employee: $username.");
            $message = "<div class='alert alert-success border-0 shadow HUD-alert animate-up'><i class='fas fa-check-circle me-2'></i> Employee <strong>$username</strong> added successfully. Password: <strong>$plain_pass</strong></div>";
        } else {
            $message = "<div class='alert alert-danger border-0 shadow HUD-alert animate-up'>Error: Username may already exist.</div>";
        }
    }
}

// Handle Delete Staff
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    // Prevent deleting super_admins or admins if you are just an admin
    $check = $conn->query("SELECT role FROM users WHERE id = $del_id");
    if ($check && $row = $check->fetch_assoc()) {
        if ($row['role'] == 'super_admin' || ($row['role'] == 'admin' && !isSuperAdmin())) {
            $message = "<div class='alert alert-danger'>Error: You cannot delete this user.</div>";
        } else {
            try {
                // Nullify references before deleting
                $conn->query("UPDATE students SET added_by = NULL WHERE added_by = $del_id");
                $conn->query("UPDATE fees SET collected_by = NULL WHERE collected_by = $del_id");
                // Also ensure the user belongs to the same branch if not super admin
                $branch_filter = getBranchWhere();
                $conn->query("DELETE FROM users WHERE id = $del_id $branch_filter");
                if ($conn->affected_rows > 0) {
                    logActivity($conn, $_SESSION['user_id'], "Delete Staff", "Deleted user ID: $del_id.");
                    $message = "<div class='alert alert-success'><i class='fas fa-trash-alt me-2'></i> Staff member removed.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Error: Could not delete user. They may not belong to your branch.</div>";
                }
            } catch (Throwable $e) {
                $message = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Filter staff: Super Admin sees all (except super_admins), Branch Admin sees only their branch's employees.
$where_clause = "u.role != 'super_admin'";
if (!isSuperAdmin()) {
    $where_clause .= " AND u.branch_id = " . (int)$_SESSION['branch_id'] . " AND u.role = 'employee'";
}

// Staff Stats
$res_staff = $conn->query("SELECT COUNT(*) as count FROM users u WHERE $where_clause");
$total_staff = ($res_staff && $row = $res_staff->fetch_assoc()) ? $row['count'] : 0;

$staff_list = $conn->query("
    SELECT u.*, b.branch_name, b.business_type 
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.id 
    WHERE $where_clause
    ORDER BY u.id DESC
");
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-user-tie text-primary me-2"></i>Staff Management</h2>
            <p class="text-muted mb-0">Overview of branch staff and operators.</p>
        </div>
        <div>
            <?php if (!isSuperAdmin()): // Only show add button for branch admins here ?>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="fas fa-plus-circle me-2"></i>Add Employee
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php echo $message; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card glass-card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted text-uppercase fw-bold mb-1">Total Employees</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($total_staff); ?></h3>
                        </div>
                        <div class="icon-box bg-primary-gradient text-white">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4 py-3">Identity</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Login Password</th>
                            <th class="py-3">Branch Center</th>
                            <th class="py-3">Type</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($staff_list && $staff_list->num_rows > 0): ?>
                            <?php while($row = $staff_list->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['username']); ?></div>
                                    <div class="text-muted small" style="font-size: 10px;">ID: <?php echo htmlspecialchars($row['employee_id'] ?? '#'.$row['id']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border rounded-pill px-3">
                                        <?php echo strtoupper($row['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(isAdmin()): ?>
                                    <div class="input-group input-group-sm" style="max-width: 150px;">
                                        <input type="password" class="form-control border-0 bg-transparent fw-bold" value="<?php echo htmlspecialchars($row['plain_password'] ?? '********'); ?>" readonly id="pass_<?php echo $row['id']; ?>">
                                        <button class="btn btn-link btn-sm text-primary p-0 ms-1" type="button" onclick="togglePass(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-eye" id="icon_<?php echo $row['id']; ?>"></i>
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted small">••••••••</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['branch_name'] ?? 'Unassigned'); ?></td>
                                <td><span class="small text-muted text-uppercase"><?php echo htmlspecialchars($row['business_type'] ?? 'General'); ?></span></td>
                                <td class="pe-4 text-end">
                                    <?php if(!isSuperAdmin() && $row['role'] == 'employee'): ?>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-white btn-sm px-3 border" title="Delete Employee" onclick="return confirm('Are you sure you want to remove this employee?');">
                                        <i class="fas fa-trash-alt text-danger"></i>
                                    </a>
                                    <?php elseif(isSuperAdmin()): ?>
                                    <span class="text-muted small">Manage in Admin Panel</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No employees found in your branch.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-plus me-2 text-primary"></i>Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">This will create a new staff member for your branch. A password will be generated automatically.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Username</label>
                        <input type="text" name="username" class="form-control rounded-3" placeholder="e.g. jsmith" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Create Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById('pass_' + id);
    const icon = document.getElementById('icon_' + id);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
