<?php
require_once '../includes/auth.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("Student ID is required.");
}

$student_id = $_GET['id'];

// ── VERIFY UTR (ADMIN ONLY) ───────────────────────────────────────
if (isset($_GET['verify_utr']) && isAdmin()) {
    $verify_id = (int)$_GET['verify_utr'];
    $conn->query("UPDATE fees SET is_verified = 1 WHERE id = $verify_id AND student_id = $student_id");
}

// Fetch Student Info
$stmt = $conn->prepare("
    SELECT s.*, c.course_name 
    FROM students s 
    LEFT JOIN courses c ON s.course_id = c.id 
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

// Fetch Payment History
$payments = $conn->query("
    SELECT f.*, u.username as collector_name 
    FROM fees f 
    LEFT JOIN users u ON f.collected_by = u.id 
    WHERE f.student_id = $student_id 
    ORDER BY f.date_collected DESC
");

// Fetch Attendance Stats
$attendance_stats = $conn->query("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance 
    WHERE student_id = $student_id
")->fetch_assoc();

$present_percent = $attendance_stats['total_days'] > 0 
    ? round(($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100) 
    : 0;
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark">Student Profile & Ledger</h2>
            <p class="text-muted">Viewing history for <span class="text-primary fw-bold"><?php echo htmlspecialchars($student['student_name']); ?></span></p>
        </div>
        <a href="students.php" class="btn btn-light rounded-pill px-4 border">
            <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
    </div>

    <div class="row g-4">
        <!-- Student Info Card -->
        <div class="col-lg-4">
            <div class="card glass-card border-0 mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php echo strtoupper(substr($student['student_name'], 0, 1)); ?>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($student['student_name']); ?></h4>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3"><?php echo htmlspecialchars($student['course_name'] ?? 'No Course'); ?></span>
                    </div>

                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between py-3">
                            <span class="text-muted">Father's Name</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($student['father_name']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between py-3">
                            <span class="text-muted">Contact</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($student['contact']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between py-3">
                            <span class="text-muted">Email</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($student['email'] ?: 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between py-3">
                            <span class="text-muted">College</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($student['college']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between py-3">
                            <span class="text-muted">Duration</span>
                            <span class="badge bg-info text-white"><?php echo str_replace('_', ' ', $student['duration']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Attendance Widget -->
            <div class="card glass-card border-0">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-calendar-check me-2 text-info"></i> Attendance Summary</h6>
                    <div class="d-flex align-items-center mb-3">
                        <div class="h2 fw-bold mb-0 me-3"><?php echo $present_percent; ?>%</div>
                        <div class="flex-grow-1">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $present_percent; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row text-center g-2">
                        <div class="col-6">
                            <div class="p-2 bg-success-subtle rounded">
                                <small class="d-block text-muted">Present</small>
                                <span class="fw-bold text-success"><?php echo $attendance_stats['present_days']; ?> Days</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-danger-subtle rounded">
                                <small class="d-block text-muted">Absent Fine</small>
                                <span class="fw-bold text-danger">₹<?php echo $attendance_stats['absent_days'] * 50; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Card -->
        <div class="col-lg-8">
            <div class="card glass-card border-0 min-vh-50">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-ul me-2 text-primary"></i> Payment Ledger</h5>
                    <a href="fees.php?student_id=<?php echo $student_id; ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i>New Payment
                    </a>
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover datatable mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Fee Type</th>
                                    <th>Mode & UTR</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th class="pe-4 text-end">Invoice</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($p = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold"><?php echo date('d M Y', strtotime($p['date_collected'])); ?></div>
                                        <small class="text-muted">By: <?php echo htmlspecialchars($p['collector_name']); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo ucfirst($p['fee_type']); ?></span></td>
                                    <td>
                                        <span class="small fw-bold text-uppercase"><?php echo $p['payment_mode']; ?></span>
                                        <?php if($p['utr_number']): ?>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <code style="font-size:9px;color:#64748b;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo htmlspecialchars($p['utr_number']); ?></code>
                                                <?php if(isset($p['is_verified']) && $p['is_verified']): ?>
                                                    <i class="fas fa-check-circle text-success" title="Verified UTR" style="font-size:10px;"></i>
                                                <?php elseif(isAdmin()): ?>
                                                    <a href="?id=<?php echo $student_id; ?>&verify_utr=<?php echo $p['id']; ?>" class="badge bg-warning text-dark text-decoration-none" style="font-size:8px;">Verify</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-success">₹<?php echo number_format($p['amount'], 2); ?></td>
                                    <td><span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3">Paid</span></td>
                                    <td class="pe-4 text-end">
                                        <a href="invoice.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary btn-sm rounded-circle shadow-sm" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($payments->num_rows == 0): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No payment records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
