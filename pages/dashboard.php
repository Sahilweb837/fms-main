<?php
require_once '../includes/auth.php';
include '../includes/header.php';

// Stats Queries
$res_students = $conn->query("SELECT COUNT(*) as count FROM students WHERE status='active'");
$total_students = ($res_students && $row = $res_students->fetch_assoc()) ? $row['count'] : 0;

$res_courses = $conn->query("SELECT COUNT(*) as count FROM courses");
$total_courses = ($res_courses && $row = $res_courses->fetch_assoc()) ? $row['count'] : 0;

// Financial Metrics (Only for Admins)
$expected = 0;
$collected = 0;
$remaining = 0;
$collection_rate = 0;

if (isAdmin()) {
    $revenue_query = "
        SELECT 
            SUM(total_fees) as total_expected,
            (SELECT SUM(amount) FROM fees) as total_collected
        FROM students
    ";
    $res_revenue = $conn->query($revenue_query);
    $revenue_data = ($res_revenue && $row = $res_revenue->fetch_assoc()) ? $row : ['total_expected' => 0, 'total_collected' => 0];

    $expected = $revenue_data['total_expected'] ?? 0;
    $collected = $revenue_data['total_collected'] ?? 0;
    $remaining = $expected - $collected;
    $collection_rate = $expected > 0 ? ($collected / $expected) * 100 : 0;
}

// Recent Activity
$recent_logs = $conn->query("
    SELECT l.*, u.username 
    FROM activity_logs l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 6
");

// Recent Fees
$recent_fees = $conn->query("
    SELECT f.*, s.student_name 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    ORDER BY f.date_collected DESC 
    LIMIT 6
");
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <div class="d-flex align-items-center mt-1">
                <span class="badge bg-primary-gradient text-white shadow-sm rounded-pill px-3 me-2">
                    <i class="fas fa-id-badge me-1"></i> <?php echo strtoupper($_SESSION['business_type'] ?? 'GENERAL'); ?> PANEL
                </span>
                <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i> Operational Hub</span>
            </div>
        </div>
        <div class="text-end d-none d-md-block">
            <div class="fw-bold text-dark"><?php echo date('l, d M Y'); ?></div>
            <div class="text-muted small">Access: <?php echo strtoupper($_SESSION['role']); ?></div>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box" style="background: rgba(255, 85, 50, 0.1); color: var(--first-color);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?php echo number_format($total_students); ?></h3>
                <p class="text-muted small mb-0 text-uppercase fw-bold" style="letter-spacing: 1px;">Active Students</p>
            </div>
        </div>
        <?php if (isAdmin()): ?>
        <div class="col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1">₹<?php echo number_format($collected); ?></h3>
                <p class="text-muted small mb-0 text-uppercase fw-bold" style="letter-spacing: 1px;">Fees Collected</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1">₹<?php echo number_format($remaining); ?></h3>
                <p class="text-muted small mb-0 text-uppercase fw-bold" style="letter-spacing: 1px;">Pending Balance</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?php echo number_format($collection_rate, 1); ?>%</h3>
                <p class="text-muted small mb-0 text-uppercase fw-bold" style="letter-spacing: 1px;">Collection Rate</p>
                <div class="progress mt-2" style="height: 4px;">
                    <div class="progress-bar" style="width: <?php echo $collection_rate; ?>%; background: #007bff;"></div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?php echo number_format($total_courses); ?></h3>
                <p class="text-muted small mb-0 text-uppercase fw-bold" style="letter-spacing: 1px;">Available Courses</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- Recent Collections -->
        <div class="col-lg-8">
            <div class="card glass-card border-0 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Recent Collections</h5>
                        <a href="fees.php" class="btn btn-light btn-sm rounded-pill px-3">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-muted small text-uppercase">
                                    <th class="ps-0 border-0">Student</th>
                                    <th class="border-0">Type</th>
                                    <th class="text-end pe-0 border-0">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent_fees): ?>
                                    <?php while($fee = $recent_fees->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-0">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($fee['student_name']); ?></div>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($fee['date_collected'])); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo ucfirst($fee['fee_type']); ?></span></td>
                                        <td class="text-end pe-0">
                                            <div class="fw-bold text-success">₹<?php echo number_format($fee['amount']); ?></div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No recent fees found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="col-lg-4">
            <div class="card glass-card border-0 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Recent Activity</h5>
                        <a href="logs.php" class="text-primary small text-decoration-none">Logs <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="activity-timeline">
                        <?php if($recent_logs): ?>
                            <?php while($log = $recent_logs->fetch_assoc()): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0 me-3">
                                    <div class="rounded-circle bg-light border" style="width: 10px; height: 10px; margin-top: 5px;"></div>
                                </div>
                                <div>
                                    <div class="fw-bold small text-dark"><?php echo htmlspecialchars($log['action']); ?></div>
                                    <div class="text-muted small" style="font-size: 11px;"><?php echo htmlspecialchars($log['details']); ?></div>
                                    <small class="text-muted" style="font-size: 10px;"><?php echo date('h:i A', strtotime($log['created_at'])); ?> by <?php echo htmlspecialchars($log['username']); ?></small>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center text-muted">No recent activity found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
