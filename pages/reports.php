<?php
require_once '../includes/auth.php';
// Restrict to branch admins only.
if (!isAdmin()) {
    header("Location: ../index.php"); exit();
}

$page_title = "Generate Reports";
include '../includes/header.php';

$bwhere_s = getBranchWhere('s');
$bwhere_e = getBranchWhere('e');

$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year  = isset($_GET['year']) ? $_GET['year'] : date('Y');

$date_filter_f = " AND MONTH(f.date_collected) = " . (int)$filter_month . " AND YEAR(f.date_collected) = " . (int)$filter_year;
$date_filter_e = " AND MONTH(e.expense_date) = " . (int)$filter_month . " AND YEAR(e.expense_date) = " . (int)$filter_year;

// --- Get Statistics ---
// 1. Total Members Registered this month
$q_mem = $conn->query("SELECT COUNT(id) as count FROM students s WHERE 1=1 $bwhere_s AND MONTH(s.created_at)=".(int)$filter_month." AND YEAR(s.created_at)=".(int)$filter_year);
$total_new_members = ($q_mem && $r = $q_mem->fetch_assoc()) ? $r['count'] : 0;

// 2. Total Payments Collected this month
$q_pay = $conn->query("SELECT SUM(f.amount) as total FROM fees f JOIN students s ON f.student_id = s.id WHERE f.status='paid' $bwhere_s $date_filter_f");
$total_revenue = ($q_pay && $r = $q_pay->fetch_assoc()) ? ($r['total'] ?? 0) : 0;

// 3. Total Expenses this month (if tracking expenses is enabled/available)
$total_expenses = 0;
// Check if expenses table exists and query it
$q_exp = $conn->query("SELECT SUM(e.amount) as total FROM expenses e WHERE 1=1 $bwhere_e $date_filter_e");
if ($q_exp && $r = $q_exp->fetch_assoc()) {
    $total_expenses = $r['total'] ?? 0;
}

$net_profit = $total_revenue - $total_expenses;

// Fetch Recent Transactions
$history = $conn->query("
    SELECT f.id, f.date_collected, f.amount, f.fee_type, f.payment_mode, f.utr_number, f.is_verified, s.student_name, s.entity_id 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    WHERE f.status='paid' $bwhere_s $date_filter_f 
    ORDER BY f.date_collected DESC 
    LIMIT 50
");

// Entity Label logic
$btype = $_SESSION['business_type'] ?? 'other';
$el = 'Client';
if (in_array($btype, ['school','college','it_institution'])) $el = 'Student';
if ($btype == 'dispensary') $el = 'Patient';
if ($btype == 'hotel') $el = 'Guest';
if ($btype == 'restaurant') $el = 'Customer';
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-chart-pie me-2 text-primary"></i>Branch Reports</h2>
            <p class="text-muted mb-0 small">Generate and view financial reports for your branch.</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary rounded-pill shadow-sm px-4">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card glass-card border-0 shadow-sm rounded-4 mb-4 d-print-none">
        <div class="card-body p-3">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="form-label small text-muted text-uppercase mb-0">Select Month</label>
                    <select name="month" class="form-select form-select-sm border-0 bg-light rounded-3">
                        <?php 
                        for($m=1; $m<=12; $m++){
                            $sel = ($m == $filter_month) ? 'selected' : '';
                            echo "<option value='".sprintf("%02d", $m)."' $sel>".date('F', mktime(0,0,0,$m,1))."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted text-uppercase mb-0">Select Year</label>
                    <select name="year" class="form-select form-select-sm border-0 bg-light rounded-3">
                        <?php 
                        $y = date('Y');
                        for($i = $y; $i >= $y-5; $i--){
                            $sel = ($i == $filter_year) ? 'selected' : '';
                            echo "<option value='$i' $sel>$i</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-auto mt-4 pt-2">
                    <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <h4 class="mb-4 text-dark print-only" style="display:none;">Financial Report for <?php echo date('F Y', mktime(0,0,0,$filter_month,1,$filter_year)); ?></h4>

    <!-- Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="metric-card p-4 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted small text-uppercase fw-bold" style="letter-spacing:1px;">New <?php echo $el; ?>s</div>
                    <i class="fas fa-user-plus text-primary fs-5 opacity-50"></i>
                </div>
                <h3 class="fw-bold mb-0 text-dark"><?php echo $total_new_members; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card p-4 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted small text-uppercase fw-bold" style="letter-spacing:1px;">Revenue</div>
                    <i class="fas fa-wallet text-success fs-5 opacity-50"></i>
                </div>
                <h3 class="fw-bold mb-0 text-success">₹<?php echo number_format($total_revenue); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card p-4 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted small text-uppercase fw-bold" style="letter-spacing:1px;">Expenses</div>
                    <i class="fas fa-file-invoice-dollar text-danger fs-5 opacity-50"></i>
                </div>
                <h3 class="fw-bold mb-0 text-danger">₹<?php echo number_format($total_expenses); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card p-4 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted small text-uppercase fw-bold" style="letter-spacing:1px;">Net Collection</div>
                    <i class="fas fa-piggy-bank text-info fs-5 opacity-50"></i>
                </div>
                <h3 class="fw-bold mb-0 text-info">₹<?php echo number_format($net_profit); ?></h3>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden">
        <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="fas fa-list-ul me-2 text-primary"></i>Collection History (<?php echo date('F', mktime(0,0,0,$filter_month,1)); ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4 py-3">Date</th>
                        <th class="py-3"><?php echo $el; ?></th>
                        <th class="py-3">Type</th>
                        <th class="py-3">Mode</th>
                        <th class="pe-4 py-3 text-end">Amount</th>
                        <th class="pe-4 py-3 text-end d-print-none">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history && $history->num_rows > 0): while($row = $history->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark small"><?php echo date('d M, Y', strtotime($row['date_collected'])); ?></div>
                            <div class="text-muted" style="font-size:10px;"><?php echo date('h:i A', strtotime($row['date_collected'])); ?></div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['student_name']); ?></div>
                            <code style="font-size:0.68rem;color:var(--first-color);"><?php echo htmlspecialchars($row['entity_id'] ?? ''); ?></code>
                        </td>
                        <td>
                            <span class='badge bg-light text-dark border rounded-pill px-3'><?php echo ucfirst(str_replace('_',' ',$row['fee_type'])); ?></span>
                        </td>
                        <td>
                            <span class="small fw-bold text-uppercase text-secondary"><?php echo $row['payment_mode']; ?></span>
                            <?php if($row['utr_number']): ?>
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <code style="font-size:9px;color:#64748b;background:#f1f5f9;padding:2px 4px;border-radius:4px;"><?php echo htmlspecialchars($row['utr_number']); ?></code>
                                    <?php if($row['is_verified']): ?>
                                        <i class="fas fa-check-circle text-success" style="font-size:10px;"></i>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4 text-end">
                            <div class="fw-bold text-success">₹<?php echo number_format($row['amount']); ?></div>
                        </td>
                        <td class="pe-4 text-end d-print-none">
                            <a href="../pages/invoice.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 text-primary"><i class="fas fa-print me-1"></i>Invoice</a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x d-block mb-2 opacity-30"></i>No collections this month.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; }
    .sidebar, .navbar, .d-print-none, .qa-list { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .print-only { display: block !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
