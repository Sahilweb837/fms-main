<?php
/**
 * BRANCH COURSES / SERVICES MANAGEMENT
 * Required: $btype_key, $course_label (e.g. 'Course', 'Service', 'Menu Item')
 */
require_once __DIR__ . '/auth.php';
checkAccess(['admin','super_admin']);
// Removed strict business_type check to allow cross-module access
// if (!isSuperAdmin() && $_SESSION['business_type'] !== $btype_key) {
//     header("Location: ../index.php"); exit();
// }
include __DIR__ . '/header.php';

$msg = "";
$bid = (int)($_SESSION['branch_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $name  = trim($_POST['course_name']);
    $code  = trim($_POST['course_code'] ?? '');
    $total = (float)($_POST['total_fee'] ?? 0);
    $mon   = (float)($_POST['monthly_fee'] ?? 0);
    $reg   = (float)($_POST['registration_fee'] ?? 0);
    $dur   = !empty($_POST['duration_months']) ? (int)$_POST['duration_months'] : null;
    $stmt  = $conn->prepare("INSERT INTO courses (branch_id, course_name, course_code, total_fee, monthly_fee, registration_fee, duration_months) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issdddi", $bid, $name, $code, $total, $mon, $reg, $dur);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], "Add {$course_label}", "Added: $name for branch $bid.");
        $msg = "<div class='alert alert-success border-0 rounded-3 animate-up'><i class='fas fa-check me-2'></i><strong>$name</strong> added.</div>";
    }
}
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM courses WHERE id=".(int)$_GET['delete']." AND branch_id=$bid");
    $msg = "<div class='alert alert-success border-0 rounded-3'><i class='fas fa-trash me-2'></i>{$course_label} removed.</div>";
}

$courses = $conn->query("SELECT * FROM courses WHERE (branch_id=$bid OR branch_id IS NULL) AND is_active=1 ORDER BY course_name");
?>
<div class="animate-up">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-book-open me-2 text-primary"></i><?php echo $course_label; ?> Catalog</h2>
        <p class="text-muted mb-0 small">Manage <?php echo strtolower($course_label); ?>s offered by your branch.</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
        <i class="fas fa-plus me-2"></i>Add <?php echo $course_label; ?>
    </button>
</div>
<?php echo $msg; ?>
<div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4 py-3"><?php echo $course_label; ?> Name</th>
                        <th class="py-3">Code</th>
                        <th class="py-3">Total Fee</th>
                        <th class="py-3">Monthly</th>
                        <th class="py-3">Reg. Fee</th>
                        <th class="py-3">Duration</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($courses && $courses->num_rows > 0): while($c=$courses->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($c['course_name']); ?></td>
                    <td><code class="small"><?php echo htmlspecialchars($c['course_code'] ?? '—'); ?></code></td>
                    <td class="fw-bold text-success">₹<?php echo number_format($c['total_fee']); ?></td>
                    <td class="text-muted small">₹<?php echo number_format($c['monthly_fee']); ?></td>
                    <td class="text-muted small">₹<?php echo number_format($c['registration_fee']); ?></td>
                    <td class="text-muted small"><?php echo $c['duration_months'] ? $c['duration_months'].' months' : '—'; ?></td>
                    <td class="pe-4 text-end">
                        <?php if($c['branch_id'] == $bid): ?>
                        <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                        <?php else: ?>
                        <span class="badge bg-light text-muted border rounded-pill px-3">Global</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center py-5 text-muted">No <?php echo strtolower($course_label); ?>s yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="addCourseModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header border-0 p-4 pb-2">
            <h5 class="fw-bold"><i class="fas fa-plus me-2 text-primary"></i>Add <?php echo $course_label; ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-8">
                    <label class="form-label"><?php echo $course_label; ?> Name <span class="text-danger">*</span></label>
                    <input type="text" name="course_name" class="form-control" required placeholder="e.g. Class 10 / Deluxe Room / Burger">
                </div>
                <div class="col-4">
                    <label class="form-label">Code</label>
                    <input type="text" name="course_code" class="form-control" placeholder="e.g. C10">
                </div>
                <div class="col-4">
                    <label class="form-label">Total Fee (₹)</label>
                    <input type="number" step="0.01" name="total_fee" class="form-control" placeholder="0.00">
                </div>
                <div class="col-4">
                    <label class="form-label">Monthly (₹)</label>
                    <input type="number" step="0.01" name="monthly_fee" class="form-control" placeholder="0.00">
                </div>
                <div class="col-4">
                    <label class="form-label">Reg. Fee (₹)</label>
                    <input type="number" step="0.01" name="registration_fee" class="form-control" placeholder="0.00">
                </div>
                <div class="col-12">
                    <label class="form-label">Duration (months)</label>
                    <input type="number" name="duration_months" class="form-control" placeholder="e.g. 12">
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow"><i class="fas fa-save me-2"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
