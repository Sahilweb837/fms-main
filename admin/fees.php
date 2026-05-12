<?php
require_once '../includes/auth.php';
checkAccess(['super_admin']);
include '../includes/header.php';

$fees = $conn->query("
    SELECT f.*, s.student_name, b.branch_name, b.business_type 
    FROM fees f
    JOIN students s ON f.student_id = s.id
    LEFT JOIN branches b ON s.branch_id = b.id
    ORDER BY f.id DESC
");
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-file-invoice-dollar text-success me-2"></i>Global Revenue Ledger</h2>
            <p class="text-muted mb-0">Track all financial collections across the entire network.</p>
        </div>
    </div>

    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4 py-3">Transaction Info</th>
                            <th class="py-3">Branch Center</th>
                            <th class="py-3">Type</th>
                            <th class="py-3">Amount & Mode</th>
                            <th class="pe-4 py-3 text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($fees && $fees->num_rows > 0): ?>
                            <?php while($row = $fees->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <div class="text-muted small">Date: <?php echo date('d M Y, h:i A', strtotime($row['date_collected'])); ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fas fa-building text-primary me-1"></i><?php echo htmlspecialchars($row['branch_name'] ?? 'Unassigned'); ?>
                                    </div>
                                    <span class="badge bg-light text-muted border rounded-pill px-2 mt-1" style="font-size: 9px; letter-spacing: 1px;">
                                        <?php echo strtoupper($row['business_type'] ?? 'OTHER'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3">
                                        <?php echo ucfirst(str_replace('_', ' ', $row['fee_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">₹<?php echo number_format($row['amount'], 2); ?></div>
                                    <div class="text-muted small"><i class="fas fa-money-check-alt me-1"></i><?php echo ucfirst($row['method']); ?></div>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php 
                                        $s = $row['status'];
                                        $badge = 'bg-warning text-dark';
                                        if($s == 'paid') $badge = 'bg-success text-white';
                                        else if($s == 'unpaid') $badge = 'bg-danger text-white';
                                        echo "<span class='badge $badge rounded-pill px-3'>" . strtoupper($s) . "</span>";
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No financial transactions recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
