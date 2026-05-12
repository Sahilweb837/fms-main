<?php
require_once '../includes/auth.php';
include '../includes/header.php';

$message = "";

// Handle Add Course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = $_POST['course_name'];
    $total_fee = $_POST['total_fee'];
    $monthly_fee = $_POST['monthly_fee'];
    $registration_fee = $_POST['registration_fee'];
    
    $stmt = $conn->prepare("INSERT INTO courses (course_name, total_fee, monthly_fee, registration_fee) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sddd", $name, $total_fee, $monthly_fee, $registration_fee);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Course added successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error adding course: " . $conn->error . "</div>";
    }
}

// Handle Edit Course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['course_id'];
    $name = $_POST['course_name'];
    $total_fee = $_POST['total_fee'];
    $monthly_fee = $_POST['monthly_fee'];
    $registration_fee = $_POST['registration_fee'];
    
    $stmt = $conn->prepare("UPDATE courses SET course_name=?, total_fee=?, monthly_fee=?, registration_fee=? WHERE id=?");
    $stmt->bind_param("sdddi", $name, $total_fee, $monthly_fee, $registration_fee, $id);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Course updated successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error updating course: " . $conn->error . "</div>";
    }
}

// Handle Delete Course
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Course deleted successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error deleting course.</div>";
    }
}

$courses = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Manage Courses</h2>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="fas fa-plus me-2"></i>Add New Course
        </button>
    </div>

    <?php echo $message; ?>

    <div class="card glass-card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course Name</th>
                            <th>Total Fee</th>
                            <th>Monthly Fee</th>
                            <th>Reg. Fee</th>
                            <th>Date Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($courses): ?>
                            <?php while($row = $courses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['course_name']); ?></td>
                                <td class="text-primary fw-bold">₹<?php echo number_format($row['total_fee'] ?? 0, 2); ?></td>
                                <td class="text-success">₹<?php echo number_format($row['monthly_fee'] ?? 0, 2); ?></td>
                                <td class="text-info">₹<?php echo number_format($row['registration_fee'] ?? 0, 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-outline-warning btn-sm edit-course-btn" 
                                        data-id="<?php echo $row['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['course_name']); ?>"
                                        data-total="<?php echo $row['total_fee'] ?? 0; ?>"
                                        data-monthly="<?php echo $row['monthly_fee'] ?? 0; ?>"
                                        data-reg="<?php echo $row['registration_fee'] ?? 0; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editCourseModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">No courses found or database error.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Course Name</label>
                        <input type="text" name="course_name" class="form-control" placeholder="e.g. Full Stack Development" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Fee (₹)</label>
                            <input type="number" step="0.01" name="total_fee" class="form-control" value="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Monthly (₹)</label>
                            <input type="number" step="0.01" name="monthly_fee" class="form-control" value="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reg. Fee (₹)</label>
                            <input type="number" step="0.01" name="registration_fee" class="form-control" value="0.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Create Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Course Name</label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Fee (₹)</label>
                            <input type="number" step="0.01" name="total_fee" id="edit_total_fee" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Monthly (₹)</label>
                            <input type="number" step="0.01" name="monthly_fee" id="edit_monthly_fee" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reg. Fee (₹)</label>
                            <input type="number" step="0.01" name="registration_fee" id="edit_registration_fee" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const editBtns = document.querySelectorAll('.edit-course-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_course_id').value = this.getAttribute('data-id');
            document.getElementById('edit_course_name').value = this.getAttribute('data-name');
            document.getElementById('edit_total_fee').value = this.getAttribute('data-total');
            document.getElementById('edit_monthly_fee').value = this.getAttribute('data-monthly');
            document.getElementById('edit_registration_fee').value = this.getAttribute('data-reg');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
