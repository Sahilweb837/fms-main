<?php
require_once '../includes/auth.php';
include '../includes/header.php';

$message = "";
$prefill_student = isset($_GET['student_id']) ? $_GET['student_id'] : '';

// Handle Fee Collection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'collect') {
    $student_id = $_POST['student_id'];
    $fee_type = $_POST['fee_type'];
    $amount = $_POST['amount'];
    $mode = $_POST['payment_mode'];
    $utr = !empty($_POST['utr_number']) ? $_POST['utr_number'] : NULL;
    
    // Monthly Duplicate Check
    if ($fee_type == 'monthly') {
        $check = $conn->prepare("SELECT id FROM fees WHERE student_id = ? AND fee_type = 'monthly' AND MONTH(date_collected) = MONTH(CURRENT_DATE()) AND YEAR(date_collected) = YEAR(CURRENT_DATE())");
        $check->bind_param("i", $student_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-warning border-0 shadow HUD-alert animate-up'><i class='fas fa-exclamation-triangle me-2'></i> Monthly fee already paid for this month.</div>";
        }
    }

    if ($message == "") {
        $stmt = $conn->prepare("INSERT INTO fees (student_id, fee_type, amount, status, collected_by, payment_mode, utr_number) VALUES (?, ?, ?, 'paid', ?, ?, ?)");
        $stmt->bind_param("isdiss", $student_id, $fee_type, $amount, $_SESSION['user_id'], $mode, $utr);
        
        if ($stmt->execute()) {
            $fee_id = $conn->insert_id;
            $student_name_res = $conn->query("SELECT student_name FROM students WHERE id = $student_id");
            $student_name = ($student_name_res && $row = $student_name_res->fetch_assoc()) ? $row['student_name'] : 'Unknown';
            logActivity($conn, $_SESSION['user_id'], "Collect Fee", "Collected $fee_type fee of ₹$amount from $student_name.");
            $message = "<div class='alert alert-success border-0 shadow HUD-alert animate-up'>
                <i class='fas fa-check-circle me-2'></i> ₹$amount received from $student_name! 
                <a href='invoice.php?id=$fee_id' class='btn btn-sm btn-primary rounded-pill ms-3 px-3 shadow-sm' target='_blank'><i class='fas fa-print me-1'></i> Invoice</a>
            </div>";
        } else {
            $message = "<div class='alert alert-danger border-0 shadow HUD-alert animate-up'>Error processing transaction.</div>";
        }
    }
}

// Stats for Header (Only for Admins)
$total_collected = 0;
$total_revenue = 0;
$total_expected = 0;
$today_amount = 0;
$pending = 0;
$today_count = 0;

if (isAdmin()) {
    $stats_res_query = $conn->query("
        SELECT 
            (SELECT SUM(amount) FROM fees WHERE fee_type IN ('monthly', 'full_payment')) as course_fees_collected,
            (SELECT SUM(amount) FROM fees) as total_revenue,
            (SELECT SUM(total_fees) FROM students) as total_expected,
            (SELECT COUNT(*) FROM fees WHERE DATE(date_collected) = CURRENT_DATE()) as today_count,
            (SELECT SUM(amount) FROM fees WHERE DATE(date_collected) = CURRENT_DATE()) as today_amount
    ");
    $stats_res = ($stats_res_query && $row = $stats_res_query->fetch_assoc()) ? $row : ['course_fees_collected' => 0, 'total_revenue' => 0, 'total_expected' => 0, 'today_count' => 0, 'today_amount' => 0];

    $total_collected = $stats_res['course_fees_collected'] ?? 0;
    $total_revenue = $stats_res['total_revenue'] ?? 0;
    $total_expected = $stats_res['total_expected'] ?? 0;
    $today_amount = $stats_res['today_amount'] ?? 0;
    $today_count = $stats_res['today_count'] ?? 0;
    $pending = $total_expected - $total_collected;
}

// Fetch Students for Dropdown
$students_query = $conn->query("
    SELECT s.id, s.student_name, s.total_fees, s.college, c.course_name, c.monthly_fee, c.registration_fee,
    (SELECT SUM(amount) FROM fees WHERE student_id = s.id AND fee_type IN ('monthly', 'full_payment')) as total_paid
    FROM students s 
    LEFT JOIN courses c ON s.course_id = c.id 
    WHERE s.status = 'active'
    ORDER BY s.student_name ASC
");

$students_list = [];
while($students_query && $s = $students_query->fetch_assoc()) { $students_list[] = $s; }

// Fetch Fee History with College & Course
$fees = $conn->query("
    SELECT f.*, s.student_name, s.college, c.course_name, u.username as collector_name 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users u ON f.collected_by = u.id 
    ORDER BY f.id DESC
");
?>

<div class="animate-up">
    <?php if (isAdmin()): ?>
    <!-- Premium HUD Header -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-primary">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px; letter-spacing: 1px;">Total Revenue</div>
                <div class="h4 fw-bold mb-0 text-dark">₹<?php echo number_format($total_revenue); ?></div>
                <div class="text-muted small mt-1" style="font-size: 11px;"><i class="fas fa-wallet me-1"></i>Combined overall income</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-info">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px; letter-spacing: 1px;">Course Collection</div>
                <div class="h4 fw-bold mb-0 text-info">₹<?php echo number_format($total_collected); ?></div>
                <div class="progress mt-2" style="height: 3px; background: rgba(0,0,0,0.05);">
                    <div class="progress-bar bg-info" style="width: <?php echo ($total_expected > 0 ? ($total_collected/$total_expected)*100 : 0); ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-danger">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px; letter-spacing: 1px;">Fees Receivable</div>
                <div class="h4 fw-bold mb-0 text-danger">₹<?php echo number_format($pending); ?></div>
                <div class="text-muted small mt-1" style="font-size: 11px;"><i class="fas fa-clock me-1"></i>Main course balance</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-success">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px; letter-spacing: 1px;">Today's Income</div>
                <div class="h4 fw-bold mb-0 text-success">₹<?php echo number_format($today_amount); ?></div>
                <div class="text-muted small mt-1" style="font-size: 11px;"><i class="fas fa-receipt me-1"></i><?php echo $today_count; ?> Deposits today</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php echo $message; ?>

    <div class="row g-4">
        <!-- Collection Form -->
        <div class="col-lg-4">
            <div class="card glass-card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="icon-box bg-primary-gradient text-white me-3 shadow-sm" style="width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-plus-circle h5 mb-0"></i>
                        </div>
                        <h5 class="fw-bold mb-0 text-dark">Collect Payment</h5>
                    </div>
                    
                    <form method="POST" id="feeCollectionForm">
                        <input type="hidden" name="action" value="collect">
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">Student</label>
                            <select name="student_id" id="student_select" class="form-select select2-enable" onchange="updateBalanceInfo(this)" required>
                                <option value="">-- Select Student --</option>
                                <?php foreach($students_list as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" 
                                        data-total="<?php echo $s['total_fees']; ?>" 
                                        data-paid="<?php echo $s['total_paid'] ?? 0; ?>"
                                        data-monthly="<?php echo $s['monthly_fee']; ?>"
                                        data-reg="<?php echo $s['registration_fee']; ?>"
                                        data-course="<?php echo htmlspecialchars($s['course_name']); ?>"
                                        data-college="<?php echo htmlspecialchars($s['college']); ?>"
                                        <?php echo ($prefill_student == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['student_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Enhanced Balance Info -->
                        <div id="balance_info" class="p-3 mb-4 rounded-4 bg-light border border-dashed border-primary d-none">
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <span class="badge bg-white text-primary border shadow-sm px-3 py-1 mb-2 rounded-pill" id="disp_course_info">Course: N/A</span>
                                </div>
                                <div class="col-6 border-end">
                                    <small class="text-muted d-block">Course Fees</small>
                                    <span class="fw-bold text-dark h5 mb-0" id="disp_total">₹0</span>
                                </div>
                                <div class="col-6 ps-3">
                                    <small class="text-muted d-block">Remaining</small>
                                    <span class="fw-bold text-danger h5 mb-0" id="disp_rem">₹0</span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted text-uppercase">Payment Purpose</label>
                                <select name="fee_type" id="fee_type_select" class="form-select rounded-3" onchange="updateAmount(this.value)" required>
                                    <option value="monthly">Monthly Tuition Installment</option>
                                    <option value="full_payment">Full One-Time Course Payment</option>
                                    <option value="registration">Admission / Registration Fee</option>
                                    <option value="exam">Examination & Certification Fee</option>
                                    <option value="other">Misc / Other Charges</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted text-uppercase">Amount (₹)</label>
                                <input type="number" name="amount" id="amount_input" class="form-control rounded-3 fw-bold text-primary h5" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Payment Channel</label>
                            <div class="d-flex gap-2">
                                <input type="radio" class="btn-check" name="payment_mode" value="cash" id="mode_cash" onchange="toggleUTR('cash')" checked>
                                <label class="btn btn-outline-secondary flex-grow-1 rounded-3 py-2" for="mode_cash"><i class="fas fa-money-bill-wave me-2"></i>Cash</label>

                                <input type="radio" class="btn-check" name="payment_mode" value="online" id="mode_online" onchange="toggleUTR('online')">
                                <label class="btn btn-outline-secondary flex-grow-1 rounded-3 py-2" for="mode_online"><i class="fas fa-mobile-alt me-2"></i>UPI/Online</label>
                            </div>
                        </div>

                        <div class="mb-4 d-none" id="utr_container">
                            <label class="form-label small fw-bold text-muted text-uppercase">Reference / UTR Number</label>
                            <input type="text" name="utr_number" class="form-control rounded-3" placeholder="Enter transaction ID">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-lg HUD-button">
                            <i class="fas fa-file-invoice-dollar me-2"></i> RECORD PAYMENT
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Advanced Transactions Table -->
        <div class="col-lg-8">
            <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white sticky-top">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list-ul me-2 text-primary"></i> Payment Ledger</h5>
                        <div class="position-relative" style="width: 250px;">
                            <i class="fas fa-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="ledgerSearch" class="form-control ps-5 rounded-pill form-control-sm" placeholder="Search records...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="ledgerTable">
                            <thead class="bg-light">
                                <tr class="small text-muted text-uppercase">
                                    <th class="ps-4 py-3">Transaction</th>
                                    <th class="py-3">Student & Institution</th>
                                    <th class="py-3">Type</th>
                                    <th class="py-3">Method</th>
                                    <th class="pe-4 py-3 text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($fees): ?>
                                    <?php while($row = $fees->fetch_assoc()): ?>
                                    <tr class="ledger-row">
                                        <td class="ps-4">
                                            <div class="fw-bold text-primary">#<?php echo $row['id']; ?></div>
                                            <small class="text-muted" style="font-size: 10px;"><?php echo date('d M, h:i A', strtotime($row['date_collected'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                            <div class="text-muted small" style="font-size: 11px;">
                                                <span class="badge bg-light text-dark border-0 p-0 me-2"><i class="fas fa-university me-1 text-primary"></i><?php echo htmlspecialchars($row['college'] ?? 'N/A'); ?></span>
                                                <span class="badge bg-light text-dark border-0 p-0"><i class="fas fa-book-open me-1 text-info"></i><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill <?php 
                                                echo match($row['fee_type']) {
                                                    'monthly' => 'bg-success-subtle text-success',
                                                    'registration' => 'bg-primary-subtle text-primary',
                                                    'exam' => 'bg-warning-subtle text-warning',
                                                    default => 'bg-secondary-subtle text-secondary'
                                                };
                                            ?> px-3 py-1"><?php echo ucfirst($row['fee_type']); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <?php if($row['payment_mode'] == 'cash'): ?>
                                                        <i class="fas fa-money-bill text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-globe text-primary"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small fw-bold text-uppercase"><?php echo $row['payment_mode']; ?></div>
                                            </div>
                                            <?php if($row['utr_number']): ?><div class="text-muted" style="font-size: 9px;"><?php echo $row['utr_number']; ?></div><?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="fw-bold text-dark h6 mb-1">₹<?php echo number_format($row['amount']); ?></div>
                                            <a href="invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-link btn-sm text-primary p-0 text-decoration-none" target="_blank" style="font-size: 10px;"><i class="fas fa-print me-1"></i>Receipt</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No payments found or database error.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .HUD-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; }
    .HUD-alert { border-left: 4px solid var(--first-color); border-radius: 8px; font-weight: 600; background: #fff; }
    .HUD-button { font-weight: 600; letter-spacing: 0.5px; border-radius: 8px; }
    .ledger-row { border-bottom: 1px solid var(--border-color); }
    .ledger-row:hover { background-color: #f8fafc !important; }
    .icon-box { background: #f8fafc; color: var(--first-color); border: 1px solid #e2e8f0; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Advanced Search for Ledger
    const ledgerSearch = document.getElementById('ledgerSearch');
    const table = document.getElementById('ledgerTable');
    const rows = table.getElementsByClassName('ledger-row');

    ledgerSearch.addEventListener('keyup', function() {
        const filter = ledgerSearch.value.toLowerCase();
        for (let i = 0; i < rows.length; i++) {
            const text = rows[i].innerText.toLowerCase();
            rows[i].style.display = text.includes(filter) ? "" : "none";
        }
    });

    // Auto-run for prefilled student
    const select = document.getElementById('student_select');
    if (select && select.value) {
        updateBalanceInfo(select);
    }
});

function updateBalanceInfo(select) {
    const info = document.getElementById('balance_info');
    if (!select.value) {
        info.classList.add('d-none');
        return;
    }
    
    const option = select.options[select.selectedIndex];
    const total = parseFloat(option.getAttribute('data-total')) || 0;
    const paid = parseFloat(option.getAttribute('data-paid')) || 0;
    const remaining = total - paid;
    const course = option.getAttribute('data-course');
    const college = option.getAttribute('data-college');
    
    document.getElementById('disp_total').innerText = '₹' + total.toLocaleString();
    document.getElementById('disp_rem').innerText = '₹' + remaining.toLocaleString();
    document.getElementById('disp_course_info').innerText = 'Course: ' + course + ' (' + college + ')';
    info.classList.remove('d-none');
    
    // Also update amount if a fee type is already selected
    const feeType = document.getElementById('fee_type_select').value;
    updateAmount(feeType);
}

function updateAmount(feeType) {
    const studentSelect = document.getElementById('student_select');
    const amountInput = document.getElementById('amount_input');
    
    if (!studentSelect.value) return;
    
    const selectedOption = studentSelect.options[studentSelect.selectedIndex];
    
    if (feeType === 'monthly') {
        amountInput.value = selectedOption.getAttribute('data-monthly') || "";
    } else if (feeType === 'registration') {
        amountInput.value = selectedOption.getAttribute('data-reg') || "";
    } else {
        // For other types, leave it blank
        if (feeType === 'exam') amountInput.value = "";
    }
}

function toggleUTR(val) {
    const utr = document.getElementById('utr_container');
    if (val === 'cash') {
        utr.classList.add('d-none');
    } else {
        utr.classList.remove('d-none');
    }
}
</script>

<?php include '../includes/footer.php'; ?>

