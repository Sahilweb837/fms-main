<?php
/**
 * SHARED INDUSTRY DASHBOARD TEMPLATE
 * Include from: school/dashboard.php, college/dashboard.php, etc.
 * Required vars: $btype_key, $panel_title, $panel_icon, $entity_label, $entity_icon
 */

require_once __DIR__ . '/auth.php';

// Removed strict business_type check to allow cross-module access
// if (!isSuperAdmin() && $_SESSION['business_type'] !== $btype_key) {
//     header("Location: ../index.php"); exit();
// }

include __DIR__ . '/header.php';

$bid = (int)($_SESSION['branch_id'] ?? 0);

// Stats — branch-scoped
$w = getBranchWhere('s');

$total_entities  = $conn->query("SELECT COUNT(*) as c FROM students s WHERE 1=1 $w")->fetch_assoc()['c'] ?? 0;
$active_entities = $conn->query("SELECT COUNT(*) as c FROM students s WHERE s.status='active' $w")->fetch_assoc()['c'] ?? 0;

$revenue_q = $conn->query("SELECT SUM(f.amount) as t FROM fees f JOIN students s ON f.student_id=s.id WHERE f.status='paid' $w");
$total_revenue = $revenue_q ? $revenue_q->fetch_assoc()['t'] ?? 0 : 0;

$today_q = $conn->query("SELECT SUM(f.amount) as t FROM fees f JOIN students s ON f.student_id=s.id WHERE DATE(f.date_collected)=CURDATE() AND f.status='paid' $w");
$today_revenue = $today_q ? $today_q->fetch_assoc()['t'] ?? 0 : 0;

$expense_q = $bid ? $conn->query("SELECT SUM(amount) as t FROM expenses WHERE branch_id=$bid") : null;
$total_expenses = ($expense_q && $er = $expense_q->fetch_assoc()) ? $er['t'] ?? 0 : 0;

// Recent 6 entries
$recent = $conn->query("SELECT s.*, c.course_name FROM students s LEFT JOIN courses c ON s.course_id=c.id WHERE 1=1 $w ORDER BY s.id DESC LIMIT 6");
?>

<div class="animate-up">
    <!-- Page Header & Pro Version Badge -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <h2 class="fw-bold text-dark mb-0">
                    <i class="fas <?php echo $panel_icon; ?> me-2 text-primary"></i><?php echo $panel_title; ?>
                </h2>
                <?php if($is_pro): ?>
                    <span class="badge bg-gradient rounded-pill px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, #f59e0b, #d97706); border: 1px solid #fbbf24;">
                        <i class="fas fa-crown text-white me-1"></i> PRO VERSION
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary rounded-pill px-3 py-2 shadow-sm">
                        <i class="fas fa-info-circle text-white me-1"></i> STANDARD VERSION
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0 small">
                Welcome back, <strong class="text-primary"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong> 
                <span class="mx-2">•</span> <i class="fas fa-calendar-alt me-1"></i> <?php echo date('l, d F Y'); ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="members.php?action=add" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                <i class="fas fa-plus me-2"></i>Add <?php echo $entity_label; ?>
            </a>
            <a href="payments.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold bg-white">
                <i class="fas fa-wallet me-2"></i>Payments
            </a>
        </div>
    </div>

    <!-- Analytics / Metric Cards -->
    <?php $lc = (isset($trial_expired) && $trial_expired) ? 'pro-locked' : ''; ?>
    <?php if(isAdmin()): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="icon-box mb-3" style="background:var(--first-color-light);color:var(--first-color);">
                    <i class="fas <?php echo $entity_icon; ?>"></i>
                </div>
                <h3 class="fw-bold mb-0"><?php echo number_format($total_entities); ?></h3>
                <p class="text-muted small mb-0 mt-1">Total <?php echo $entity_label; ?>s</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card p-4 h-100">
                <div class="icon-box bg-success-subtle text-success mb-3">
                    <i class="fas fa-circle-check"></i>
                </div>
                <h3 class="fw-bold mb-0"><?php echo number_format($active_entities); ?></h3>
                <p class="text-muted small mb-0 mt-1">Active</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card p-4 h-100 <?php echo $lc; ?>">
                <div class="icon-box bg-success-subtle text-success mb-3">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h3 class="fw-bold mb-0">₹<?php echo number_format($total_revenue); ?></h3>
                <p class="text-muted small mb-0 mt-1">Total Collected</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card p-4 h-100 <?php echo $lc; ?>">
                <div class="icon-box bg-warning-subtle text-warning mb-3">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3 class="fw-bold mb-0">₹<?php echo number_format($today_revenue); ?></h3>
                <p class="text-muted small mb-0 mt-1">Today's Income</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions + Recent Entries -->
    <div class="row g-4">
        <!-- Recent Entries Table -->
        <div class="col-lg-8">
            <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                        <h5 class="fw-bold mb-0"><i class="fas fa-clock me-2 text-primary"></i>Recent <?php echo $entity_label; ?>s</h5>
                        <a href="members.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="small text-muted text-uppercase">
                                    <th class="ps-4 py-3">ID</th>
                                    <th class="py-3">Name</th>
                                    <th class="py-3">Contact</th>
                                    <th class="py-3">Reference</th>
                                    <th class="pe-4 py-3 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent && $recent->num_rows > 0): while($rc = $recent->fetch_assoc()): ?>
                                <tr onclick="window.location='members.php?view=<?php echo $rc['id']; ?>'" style="cursor:pointer;">
                                    <td class="ps-4">
                                        <code style="font-size:0.72rem;color:var(--first-color);"><?php echo htmlspecialchars($rc['entity_id'] ?? '#'.$rc['id']); ?></code>
                                    </td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($rc['student_name']); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($rc['contact']); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($rc['industry_field_1'] ?? '—'); ?></td>
                                    <td class="pe-4 text-end">
                                        <span class="badge <?php echo $rc['status']=='active' ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary'; ?> rounded-pill px-3">
                                            <?php echo ucfirst($rc['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-30"></i>
                                    No <?php echo strtolower($entity_label); ?>s registered yet.
                                    <br><a href="members.php?action=add" class="btn btn-primary btn-sm rounded-pill px-4 mt-3">Register First <?php echo $entity_label; ?></a>
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:linear-gradient(145deg, #0f172a, #1e293b);">
                <div class="p-4 border-bottom" style="border-color:rgba(255,255,255,0.07)!important;">
                    <h6 class="fw-bold text-white mb-0"><i class="fas fa-bolt me-2" style="color:var(--first-color);"></i>Quick Actions</h6>
                </div>
                <div class="list-group list-group-flush qa-list">
                    <style>
                        .qa-list .list-group-item { transition: all 0.2s ease; border-left: 3px solid transparent; }
                        .qa-list .list-group-item:hover { background: rgba(255,255,255,0.05) !important; color: #fff !important; padding-left: 1.5rem !important; border-left: 3px solid var(--first-color); }
                    </style>
                    <a href="members.php?action=add" class="list-group-item list-group-item-action p-3" style="background:transparent;border-color:rgba(255,255,255,0.05);color:#cbd5e1;">
                        <i class="fas fa-user-plus me-3" style="color:var(--first-color);width:16px;"></i>Register <?php echo $entity_label; ?>
                    </a>
                    <a href="payments.php" class="list-group-item list-group-item-action p-3" style="background:transparent;border-color:rgba(255,255,255,0.05);color:#cbd5e1;">
                        <i class="fas fa-wallet me-3 text-success;width:16px;"></i>Collect Payment
                    </a>
                    <a href="members.php" class="list-group-item list-group-item-action p-3" style="background:transparent;border-color:rgba(255,255,255,0.05);color:#cbd5e1;">
                        <i class="fas fa-list me-3 text-info;width:16px;"></i>View All Records
                    </a>
                    <?php if(isAdmin()): ?>
                    <a href="users.php" class="list-group-item list-group-item-action p-3 <?php echo $lc; ?>" style="background:transparent;border-color:rgba(255,255,255,0.05);color:#cbd5e1;">
                        <i class="fas fa-user-tie me-3 text-warning;width:16px;"></i>Manage Staff
                    </a>
                    <a href="courses.php" class="list-group-item list-group-item-action p-3 <?php echo $lc; ?>" style="background:transparent;border-color:rgba(255,255,255,0.05);color:#cbd5e1;">
                        <i class="fas fa-book-open me-3 text-primary;width:16px;"></i>Manage <?php echo in_array($btype_key,['restaurant','hotel']) ? 'Services' : 'Courses'; ?>
                    </a>
                    <a href="../pages/expenses.php" class="list-group-item list-group-item-action p-3 <?php echo $lc; ?>" style="background:transparent;border-color:rgba(255,255,255,0.05);color:#cbd5e1;">
                        <i class="fas fa-file-invoice-dollar me-3 text-danger;width:16px;"></i>Track Expenses
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(isAdmin()): ?>
            <!-- Mini Revenue Summary -->
            <div class="metric-card p-4 mt-3 <?php echo $lc; ?>">
                <div class="small text-muted text-uppercase fw-bold mb-3">Revenue vs Expenses</div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Revenue</span>
                        <span class="fw-bold text-success">₹<?php echo number_format($total_revenue); ?></span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-success" style="width:<?php $mx=max($total_revenue,$total_expenses,1); echo min(100,($total_revenue/$mx)*100); ?>%"></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Expenses</span>
                        <span class="fw-bold text-danger">₹<?php echo number_format($total_expenses); ?></span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-danger" style="width:<?php echo min(100,($total_expenses/$mx)*100); ?>%"></div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between">
                    <span class="small fw-bold text-muted">Net Profit</span>
                    <span class="fw-bold <?php echo ($total_revenue - $total_expenses) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        ₹<?php echo number_format($total_revenue - $total_expenses); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
