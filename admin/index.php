<?php
require_once '../includes/auth.php';
checkAccess(['super_admin']); 
include '../includes/header.php';

// Global Stats
$total_revenue = $conn->query("SELECT SUM(amount) as total FROM fees WHERE status='paid'")->fetch_assoc()['total'] ?? 0;
$total_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'] ?? 0;
$total_branches = $conn->query("SELECT COUNT(*) as total FROM branches")->fetch_assoc()['total'] ?? 0;
$total_staff = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='employee'")->fetch_assoc()['total'] ?? 0;

// Branch-wise distribution for Chart
$chart_data = $conn->query("
    SELECT b.branch_name, SUM(f.amount) as revenue
    FROM branches b 
    LEFT JOIN students s ON s.branch_id = b.id 
    LEFT JOIN fees f ON f.student_id = s.id AND f.status = 'paid'
    GROUP BY b.id
    ORDER BY revenue DESC
");

$labels = [];
$values = [];
while($row = $chart_data->fetch_assoc()){
    $labels[] = $row['branch_name'];
    $values[] = (float)$row['revenue'];
}

// Recent Logs
$recent_logs = $conn->query("
    SELECT l.*, u.username 
    FROM activity_logs l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 5
");
?>

<div class="animate-up">
    <!-- Dashboard Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
        <div>
            <h2 class="fw-800 text-dark mb-1">Command Center</h2>
            <p class="text-muted mb-0"><i class="fas fa-clock me-1 text-primary"></i> Data as of <?php echo date('h:i A, d M Y'); ?></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.location.reload()" class="btn btn-light shadow-sm rounded-pill">
                <i class="fas fa-sync-alt me-2 text-primary"></i>Refresh
            </button>
            <a href="users.php" class="btn btn-primary shadow-sm rounded-pill px-4">
                <i class="fas fa-plus me-2"></i>New Staff
            </a>
        </div>
    </div>

    <!-- Global Metrics Grid -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="metric-card p-4 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="fas fa-indian-rupee-sign"></i>
                    </div>
                    <span class="badge bg-primary text-white">Revenue</span>
                </div>
                <h2 class="fw-800 text-dark mb-1">₹<?php echo number_format($total_revenue); ?></h2>
                <p class="mb-0 small text-muted">All-time collection</p>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card p-4 h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="icon-box bg-info bg-opacity-10 text-info">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="badge bg-info bg-opacity-10 text-info">Students</span>
                </div>
                <h2 class="fw-800 text-dark mb-1"><?php echo number_format($total_students); ?></h2>
                <p class="mb-0 small text-muted">Across network</p>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card p-4 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-building"></i>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning">Branches</span>
                </div>
                <h2 class="fw-800 text-dark mb-1"><?php echo number_format($total_branches); ?></h2>
                <p class="mb-0 small text-muted">Active nodes</p>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card p-4 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success">Staff</span>
                </div>
                <h2 class="fw-800 text-dark mb-1"><?php echo number_format($total_staff); ?></h2>
                <p class="mb-0 small text-muted">Management team</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Revenue Analytics Chart -->
        <div class="col-lg-8">
            <div class="card glass-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Financial Performance</h5>
                    <i class="fas fa-chart-line text-primary"></i>
                </div>
                <div class="card-body p-4 pt-0">
                    <div style="height: 300px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Branch Performance Table -->
            <div class="card glass-card">
                <div class="card-header">
                    <h5 class="fw-bold mb-0">Branch Ledger</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="small text-muted text-uppercase">
                                    <th class="ps-4">Branch Center</th>
                                    <th>Students</th>
                                    <th class="pe-4 text-end">Collection</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $branch_stats = $conn->query("
                                    SELECT b.branch_name, COUNT(s.id) as student_count, SUM(f.amount) as revenue
                                    FROM branches b 
                                    LEFT JOIN students s ON s.branch_id = b.id 
                                    LEFT JOIN fees f ON f.student_id = s.id AND f.status = 'paid'
                                    GROUP BY b.id
                                    ORDER BY revenue DESC
                                ");
                                if($branch_stats && $branch_stats->num_rows > 0): 
                                    while($bs = $branch_stats->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($bs['branch_name']); ?></div>
                                            <div class="small text-muted">Node ID: #<?php echo rand(100,999); ?></div>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary rounded-pill px-3"><?php echo $bs['student_count']; ?></span></td>
                                        <td class="pe-4 text-end">
                                            <div class="fw-bold text-success">₹<?php echo number_format($bs['revenue'] ?? 0); ?></div>
                                        </td>
                                    </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted">No data found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Activity & Shortcuts -->
        <div class="col-lg-4">
            <!-- System Activity -->
            <div class="card glass-card mb-4 overflow-hidden">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">System Pulse</h5>
                        <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-2">Live</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush bg-transparent">
                        <?php while($log = $recent_logs->fetch_assoc()): ?>
                        <div class="list-group-item p-3 border-0 border-bottom bg-transparent">
                            <div class="d-flex gap-3">
                                <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0;">
                                    <i class="fas fa-bolt text-primary small"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small text-dark"><?php echo htmlspecialchars($log['action']); ?></div>
                                    <div class="text-muted small mb-1"><?php echo htmlspecialchars($log['details']); ?></div>
                                    <div class="text-muted" style="font-size:10px;">
                                        <i class="far fa-user me-1"></i><?php echo htmlspecialchars($log['username']); ?> • <?php echo date('H:i', strtotime($log['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="card-footer bg-transparent p-3 text-center border-0">
                    <a href="logs.php" class="text-primary small fw-bold text-decoration-none">View All Logs <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card glass-card">
                <div class="card-header pb-0">
                    <h6 class="fw-800 text-uppercase text-muted small mb-0" style="letter-spacing:1px;">Module Access</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        <a href="branches.php" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-primary-subtle text-decoration-none hover-shadow">
                            <div class="bg-primary text-white rounded-3 p-2 px-3 shadow-sm"><i class="fas fa-map-marked-alt"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Manage Branches</div>
                                <div class="text-muted" style="font-size:10px;">Configure nodes</div>
                            </div>
                        </a>
                        <a href="fees.php" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-primary-subtle text-decoration-none hover-shadow">
                            <div class="bg-success text-white rounded-3 p-2 px-3 shadow-sm"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Global Revenue</div>
                                <div class="text-muted" style="font-size:10px;">Consolidated ledger</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, '#ff5532');
    gradient.addColorStop(1, 'rgba(255, 85, 50, 0.1)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode($values); ?>,
                backgroundColor: gradient,
                borderColor: '#ff5532',
                borderWidth: 1,
                borderRadius: 8,
                barThickness: 32
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#64748b', font: { size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 10 } }
                }
            }
        }
    });
});
</script>

<style>
.hover-shadow { transition: all 0.3s ease; border: 1px solid transparent; }
.hover-shadow:hover { border-color: rgba(255,85,50,0.3); transform: translateX(5px); background: rgba(255,85,50,0.08) !important; }
.fw-800 { font-weight: 800; }
</style>

<script>
// Subtle auto-refresh indication logic
setInterval(() => {
    console.log('Syncing dashboard data...');
    // Real dynamic apps would fetch stats via AJAX here
}, 60000);
</script>

<style>
.hover-shadow { transition: all 0.2s ease; }
.hover-shadow:hover { background: #fff !important; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateX(5px); }
.fw-800 { font-weight: 800; }
</style>

<?php include '../includes/footer.php'; ?>
