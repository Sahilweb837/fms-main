<?php
require_once '../includes/auth.php';
// Super Admin can view, but this is mainly for Branch Admins/Employees
include '../includes/header.php';

$message = "";
$branch_id = $_SESSION['branch_id'];

if (!$branch_id && !isSuperAdmin()) {
    die("Access denied. No branch assigned.");
}

// Ensure super admin can filter by branch or just view all if they want, but usually expenses are branch specific.
$where_clause = "1=1";
if (!isSuperAdmin()) {
    $where_clause = "e.branch_id = " . (int)$branch_id;
} else {
    // Super admin views all or selected
    if (isset($_GET['branch_id']) && !empty($_GET['branch_id'])) {
        $where_clause = "e.branch_id = " . (int)$_GET['branch_id'];
    }
}

// Add Expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $expense_date = $_POST['expense_date'];
    $target_branch = isSuperAdmin() ? $_POST['branch_id'] : $branch_id;

    if (empty($target_branch)) {
        $message = "<div class='alert alert-danger'>Please select a branch.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO expenses (branch_id, amount, category, description, expense_date, added_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsssi", $target_branch, $amount, $category, $description, $expense_date, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], "Add Expense", "Added expense of ₹$amount for $category.");
            $message = "<div class='alert alert-success border-0 shadow-sm HUD-alert animate-up'><i class='fas fa-check-circle me-2'></i> Expense added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger border-0 shadow-sm HUD-alert animate-up'>Error adding expense.</div>";
        }
    }
}

// Delete Expense
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Must belong to branch
    $conn->query("DELETE FROM expenses e WHERE id = $del_id AND ($where_clause)");
    if ($conn->affected_rows > 0) {
        logActivity($conn, $_SESSION['user_id'], "Delete Expense", "Deleted expense ID: $del_id.");
        $message = "<div class='alert alert-success'><i class='fas fa-trash-alt me-2'></i> Expense deleted.</div>";
    }
}

$expenses = $conn->query("
    SELECT e.*, u.username, b.branch_name 
    FROM expenses e
    LEFT JOIN users u ON e.added_by = u.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE $where_clause
    ORDER BY e.expense_date DESC, e.id DESC
");

$total_expenses_res = $conn->query("SELECT SUM(amount) as total FROM expenses e WHERE $where_clause");
$total_expenses = ($total_expenses_res && $row = $total_expenses_res->fetch_assoc()) ? $row['total'] : 0;
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Expenses Management</h2>
            <p class="text-muted mb-0">Track and manage operational expenditures.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-plus-circle me-2"></i>Record Expense
        </button>
    </div>

    <?php echo $message; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card glass-card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted text-uppercase fw-bold mb-1">Total Expenditures</div>
                            <h3 class="fw-bold text-dark mb-0">₹<?php echo number_format($total_expenses, 2); ?></h3>
                        </div>
                        <div class="icon-box bg-danger-subtle text-danger">
                            <i class="fas fa-arrow-trend-down"></i>
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
                            <th class="ps-4 py-3">Date</th>
                            <th class="py-3">Category</th>
                            <th class="py-3">Description</th>
                            <th class="py-3">Amount</th>
                            <th class="py-3">Recorded By</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($expenses && $expenses->num_rows > 0): ?>
                            <?php while($row = $expenses->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark mb-0"><?php echo date('d M Y', strtotime($row['expense_date'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-3">
                                        <?php echo htmlspecialchars($row['category']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['description']); ?></div>
                                    <?php if(isSuperAdmin()): ?>
                                    <div class="small fw-bold text-primary" style="font-size: 10px;"><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($row['branch_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><div class="fw-bold text-danger">₹<?php echo number_format($row['amount'], 2); ?></div></td>
                                <td><span class="small text-muted"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($row['username']); ?></span></td>
                                <td class="pe-4 text-end">
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-white btn-sm px-3 border" onclick="return confirm('Are you sure you want to delete this record?');">
                                        <i class="fas fa-trash-alt text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No expenses recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Record Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if(isSuperAdmin()): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Branch</label>
                        <select name="branch_id" class="form-select rounded-3" required>
                            <option value="">Select Branch</option>
                            <?php 
                            $b_res = $conn->query("SELECT id, branch_name FROM branches");
                            while($b = $b_res->fetch_assoc()) echo "<option value='{$b['id']}'>{$b['branch_name']}</option>";
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Date</label>
                            <input type="date" name="expense_date" class="form-control rounded-3" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Category</label>
                        <select name="category" class="form-select rounded-3" required>
                            <option value="Maintenance">Maintenance & Repairs</option>
                            <option value="Supplies">Supplies & Inventory</option>
                            <option value="Utilities">Utilities (Electricity, Water)</option>
                            <option value="Salary">Staff Salary</option>
                            <option value="Marketing">Marketing & Ads</option>
                            <option value="Other">Other Operational</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Description</label>
                        <textarea name="description" class="form-control rounded-3" rows="2" placeholder="Brief details about this expense..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
