<?php
/**
 * SHARED PAYMENTS TEMPLATE
 * Required: $btype_key, $entity_label
 */
require_once __DIR__ . '/auth.php';
// Removed strict business_type check to allow cross-module access
// if (!isSuperAdmin() && $_SESSION['business_type'] !== $btype_key) {
//     header("Location: ../index.php"); exit();
// }
include __DIR__ . '/header.php';

$msg = "";
$bid = (int)($_SESSION['branch_id'] ?? 0);
$bwhere = getBranchWhere('s');
$prefill = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// ── COLLECT PAYMENT ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'collect') {
    $sid      = (int)$_POST['student_id'];
    $fee_type = $_POST['fee_type'];
    $amount   = (float)$_POST['amount'];
    $mode     = $_POST['payment_mode'];
    $utr      = !empty($_POST['utr_number']) ? $_POST['utr_number'] : null;
    $notes    = trim($_POST['notes'] ?? '');

    // Monthly duplicate check
    if ($fee_type == 'monthly') {
        $dup = $conn->prepare("SELECT id FROM fees WHERE student_id=? AND fee_type='monthly' AND MONTH(date_collected)=MONTH(CURDATE()) AND YEAR(date_collected)=YEAR(CURDATE())");
        $dup->bind_param("i", $sid);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $msg = "<div class='alert alert-warning border-0 rounded-3'><i class='fas fa-exclamation-triangle me-2'></i>Monthly payment already recorded for this month.</div>";
        }
    }

    if (!$msg) {
        $stmt = $conn->prepare("INSERT INTO fees (student_id, fee_type, amount, status, collected_by, payment_mode, utr_number, notes) VALUES (?, ?, ?, 'paid', ?, ?, ?, ?)");
        $stmt->bind_param("issdiss", $sid, $fee_type, $amount, $_SESSION['user_id'], $mode, $utr, $notes);
        if ($stmt->execute()) {
            $fid = $conn->insert_id;
            $sn  = $conn->query("SELECT student_name, entity_id FROM students WHERE id=$sid")->fetch_assoc();
            $nm  = $sn ? $sn['student_name'] : 'Unknown';
            $eid = $sn ? $sn['entity_id'] : '';
            logActivity($conn, $_SESSION['user_id'], "Collect Payment", "₹$amount from $nm ($eid) — $fee_type via $mode.");
            $msg = "<div class='alert alert-success border-0 rounded-3 shadow-sm animate-up'>
                <i class='fas fa-check-circle me-2'></i>₹".number_format($amount)." received from <strong>$nm</strong>!
                <a href='../pages/invoice.php?id=$fid' class='btn btn-sm btn-success rounded-pill ms-3 px-3' target='_blank'><i class='fas fa-print me-1'></i>Invoice</a>
            </div>";
        } else {
            $msg = "<div class='alert alert-danger border-0 rounded-3'>Error: ".$conn->error."</div>";
        }
    }
}

// ── VERIFY UTR (ADMIN ONLY) ───────────────────────────────────────
if (isset($_GET['verify_utr']) && isAdmin()) {
    $verify_id = (int)$_GET['verify_utr'];
    $conn->query("UPDATE fees SET is_verified = 1 WHERE id = $verify_id");
    $msg = "<div class='alert alert-success border-0 rounded-3 shadow-sm animate-up'><i class='fas fa-check-circle me-2'></i>Transaction UTR marked as Verified!</div>";
}

// ── STATS (admin only) ────────────────────────────────────────────
$stats = ['total' => 0, 'today' => 0, 'today_count' => 0, 'pending' => 0];
if (isAdmin()) {
    $sq = $conn->query("SELECT SUM(f.amount) as t, SUM(CASE WHEN DATE(f.date_collected)=CURDATE() THEN f.amount ELSE 0 END) as td, COUNT(CASE WHEN DATE(f.date_collected)=CURDATE() THEN 1 END) as tdc FROM fees f JOIN students s ON f.student_id=s.id WHERE f.status='paid' $bwhere");
    if ($sq && $sr = $sq->fetch_assoc()) {
        $stats['total']       = $sr['t'] ?? 0;
        $stats['today']       = $sr['td'] ?? 0;
        $stats['today_count'] = $sr['tdc'] ?? 0;
    }
}

// ── ENTITY LIST for dropdown ──────────────────────────────────────
$entities = $conn->query("SELECT s.id, s.entity_id, s.student_name, s.total_fees, c.monthly_fee, c.registration_fee, (SELECT SUM(amount) FROM fees WHERE student_id=s.id AND status='paid') as total_paid FROM students s LEFT JOIN courses c ON s.course_id=c.id WHERE s.status='active' $bwhere ORDER BY s.student_name");
$list = [];
while ($entities && $r = $entities->fetch_assoc()) $list[] = $r;

// ── PAYMENT HISTORY ────────────────────────────────────────────────
$history = $conn->query("SELECT f.*, s.student_name, s.entity_id, u.username as collector FROM fees f JOIN students s ON f.student_id=s.id LEFT JOIN users u ON f.collected_by=u.id WHERE 1=1 $bwhere ORDER BY f.id DESC LIMIT 100");
?>

<div class="animate-up">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-wallet me-2 text-primary"></i>Payment Collection</h2>
        <p class="text-muted mb-0 small">Collect and track all payments for your branch.</p>
    </div>
</div>

<?php if(isAdmin()): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="metric-card p-3 border-start border-4 border-primary">
            <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size:10px;letter-spacing:1px;">Total Revenue</div>
            <div class="h4 fw-bold mb-0">₹<?php echo number_format($stats['total']); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="metric-card p-3 border-start border-4 border-success">
            <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size:10px;letter-spacing:1px;">Today's Collection</div>
            <div class="h4 fw-bold mb-0 text-success">₹<?php echo number_format($stats['today']); ?></div>
            <div class="small text-muted"><?php echo $stats['today_count']; ?> transactions</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-3 border-start border-4 border-info">
            <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size:10px;letter-spacing:1px;">Active <?php echo $entity_label; ?>s</div>
            <div class="h4 fw-bold mb-0 text-info"><?php echo count($list); ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php echo $msg; ?>

<div class="row g-4">
    <!-- Collection Form -->
    <div class="col-lg-4">
        <div class="card glass-card border-0 rounded-4 shadow-sm sticky-top" style="top:80px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-plus-circle me-2 text-primary"></i>Record Payment</h5>
                <form method="POST" id="payForm">
                    <input type="hidden" name="action" value="collect">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $entity_label; ?> <span class="text-danger">*</span></label>
                        <select name="student_id" id="entity_sel" class="form-select" onchange="updateInfo(this)" required>
                            <option value="">— Select <?php echo $entity_label; ?> —</option>
                            <?php foreach($list as $s): ?>
                            <option value="<?php echo $s['id']; ?>"
                                data-total="<?php echo $s['total_fees']; ?>"
                                data-paid="<?php echo $s['total_paid'] ?? 0; ?>"
                                data-monthly="<?php echo $s['monthly_fee'] ?? 0; ?>"
                                data-reg="<?php echo $s['registration_fee'] ?? 0; ?>"
                                <?php echo $prefill==$s['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars("[{$s['entity_id']}] {$s['student_name']}"); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Balance Info -->
                    <div id="balance_box" class="p-3 mb-3 rounded-3 d-none" style="background:var(--first-color-light);">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Total Fee</span>
                            <span class="fw-bold" id="b_total">₹0</span>
                        </div>
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="text-muted">Paid</span>
                            <span class="fw-bold text-success" id="b_paid">₹0</span>
                        </div>
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="text-muted">Balance Due</span>
                            <span class="fw-bold text-danger" id="b_due">₹0</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Type <span class="text-danger">*</span></label>
                        <select name="fee_type" id="fee_type" class="form-select" onchange="prefillAmount(this.value)" required>
                            <option value="monthly">Monthly Instalment</option>
                            <option value="full_payment">Full Payment (One-Time)</option>
                            <option value="registration">Registration / Admission Fee</option>
                            <option value="service">Service Charge</option>
                            <option value="advance">Advance / Deposit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" id="amount_inp" class="form-control fw-bold" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Mode</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach(['cash'=>'fa-money-bill-wave','online'=>'fa-mobile-alt','upi'=>'fa-qrcode','card'=>'fa-credit-card'] as $m=>$ico): ?>
                            <div>
                                <input type="radio" class="btn-check" name="payment_mode" value="<?php echo $m; ?>" id="pm_<?php echo $m; ?>" onchange="toggleUTR('<?php echo $m; ?>')" <?php echo $m=='cash'?'checked':''; ?>>
                                <label class="btn btn-outline-secondary btn-sm rounded-pill px-3" for="pm_<?php echo $m; ?>"><i class="fas <?php echo $ico; ?> me-1"></i><?php echo ucfirst($m); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3 d-none" id="utr_box">
                        <label class="form-label">Reference / UTR Number</label>
                        <input type="text" name="utr_number" class="form-control" placeholder="Transaction ID / Cheque No.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any remarks..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">
                        <i class="fas fa-file-invoice-dollar me-2"></i>RECORD PAYMENT
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Transaction Ledger -->
    <div class="col-lg-8">
        <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-ul me-2 text-primary"></i>Payment Ledger</h5>
                    <input type="text" id="ledgerSearch" class="form-control form-control-sm rounded-pill" placeholder="Search..." style="max-width:200px;">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="small text-muted text-uppercase">
                                <th class="ps-4 py-3">#</th>
                                <th class="py-3"><?php echo $entity_label; ?></th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Mode</th>
                                <th class="pe-4 py-3 text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($history && $history->num_rows > 0): while($row = $history->fetch_assoc()): ?>
                        <tr class="ledger-row">
                            <td class="ps-4">
                                <div class="fw-bold text-primary small">#<?php echo $row['id']; ?></div>
                                <div class="text-muted" style="font-size:10px;"><?php echo date('d M, h:i A', strtotime($row['date_collected'])); ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                <code style="font-size:0.68rem;color:var(--first-color);"><?php echo htmlspecialchars($row['entity_id'] ?? ''); ?></code>
                            </td>
                            <td>
                                <?php
                                $tbg = ['monthly'=>'bg-success-subtle text-success','registration'=>'bg-primary-subtle text-primary','service'=>'bg-info-subtle text-info','full_payment'=>'bg-dark text-white'];
                                $tb  = $tbg[$row['fee_type']] ?? 'bg-secondary-subtle text-secondary';
                                echo "<span class='badge $tb rounded-pill px-3'>".ucfirst(str_replace('_',' ',$row['fee_type']))."</span>";
                                ?>
                            </td>
                            <td>
                                <span class="small fw-bold text-uppercase"><?php echo $row['payment_mode']; ?></span>
                                <?php if($row['utr_number']): ?>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <code style="font-size:9px;color:#64748b;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo htmlspecialchars($row['utr_number']); ?></code>
                                        <?php if(isset($row['is_verified']) && $row['is_verified']): ?>
                                            <i class="fas fa-check-circle text-success" title="Verified UTR" style="font-size:10px;"></i>
                                        <?php elseif(isAdmin()): ?>
                                            <a href="?verify_utr=<?php echo $row['id']; ?>" class="badge bg-warning text-dark text-decoration-none" style="font-size:8px;">Verify</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="fw-bold text-dark">₹<?php echo number_format($row['amount']); ?></div>
                                <a href="../pages/invoice.php?id=<?php echo $row['id']; ?>" target="_blank" class="small text-primary text-decoration-none" style="font-size:10px;"><i class="fas fa-print me-1"></i>Receipt</a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x d-block mb-2 opacity-30"></i>No payments yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function updateInfo(sel) {
    const box = document.getElementById('balance_box');
    if (!sel.value) { box.classList.add('d-none'); return; }
    const opt = sel.options[sel.selectedIndex];
    const total = parseFloat(opt.dataset.total) || 0;
    const paid  = parseFloat(opt.dataset.paid)  || 0;
    document.getElementById('b_total').textContent = '₹' + total.toLocaleString();
    document.getElementById('b_paid').textContent  = '₹' + paid.toLocaleString();
    document.getElementById('b_due').textContent   = '₹' + (total - paid).toLocaleString();
    box.classList.remove('d-none');
    prefillAmount(document.getElementById('fee_type').value);
}
function prefillAmount(type) {
    const sel = document.getElementById('entity_sel');
    if (!sel.value) return;
    const opt = sel.options[sel.selectedIndex];
    const inp = document.getElementById('amount_inp');
    if (type === 'monthly')      inp.value = opt.dataset.monthly || '';
    else if (type === 'registration') inp.value = opt.dataset.reg || '';
    else if (type !== 'other')   inp.value = '';
}
function toggleUTR(m) {
    document.getElementById('utr_box').classList.toggle('d-none', m === 'cash');
}
document.getElementById('ledgerSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.ledger-row').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none');
});
// Auto-trigger if prefilled
window.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('entity_sel');
    if (sel.value) updateInfo(sel);
});
</script>

<?php include '../includes/footer.php'; ?>
