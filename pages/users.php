<?php
require_once '../includes/auth.php';
checkAccess(['super_admin', 'admin']);
include '../includes/header.php';

$message = "";

// Handle Form Submission for Adding User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $uname = $_POST['username'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $urole = $_POST['role'];
    
    // Admins can only create employees
    if ($_SESSION['role'] == 'admin' && $urole != 'employee') {
        $message = "<div class='alert alert-danger'>You can only create employees.</div>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $uname, $pass, $urole);
            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user_id'], "Add User", "Created user: $uname with role $urole.");
                $message = "<div class='alert alert-success'>User added successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error adding user (might already exist).</div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Username already exists!</div>";
        }
    }
}

// Handle User Deletion
if (isset($_GET['delete'])) {
    $del_id = $_GET['delete'];
    
    // Prevent deleting self or super admin if not super admin
    if ($del_id == $_SESSION['user_id']) {
        $message = "<div class='alert alert-danger'>You cannot delete yourself.</div>";
    } else {
        $check = $conn->query("SELECT role, username FROM users WHERE id = $del_id")->fetch_assoc();
        if ($_SESSION['role'] == 'admin' && $check['role'] != 'employee') {
            $message = "<div class='alert alert-danger'>Access Denied. You can only delete employees.</div>";
        } else {
            $conn->query("DELETE FROM users WHERE id = $del_id");
            logActivity($conn, $_SESSION['user_id'], "Delete User", "Deleted user: {$check['username']}.");
            $message = "<div class='alert alert-success'>User deleted successfully.</div>";
        }
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Manage Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-plus"></i> Add New User</button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped datatable w-100">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td>
                            <?php 
                                $r = $row['role'];
                                $badge = 'bg-secondary';
                                if($r == 'super_admin') $badge = 'bg-danger';
                                else if($r == 'admin') $badge = 'bg-warning text-dark';
                                else if($r == 'employee') $badge = 'bg-info text-dark';
                                echo "<span class='badge $badge'>" . strtoupper(str_replace('_', ' ', $r)) . "</span>";
                            ?>
                        </td>
                        <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if($_SESSION['user_id'] != $row['id']): ?>
                                <?php if($_SESSION['role'] == 'super_admin' || ($_SESSION['role'] == 'admin' && $row['role'] == 'employee')): ?>
                                    <a href="users.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?');"><i class="fas fa-trash"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled><i class="fas fa-ban"></i></button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-success">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
          <input type="hidden" name="action" value="add">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus"></i> Add New User</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Role</label>
                <select name="role" class="form-select" required>
                    <?php if($_SESSION['role'] == 'super_admin'): ?>
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                    <?php endif; ?>
                    <option value="employee" selected>Employee</option>
                </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save User</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
