<?php
require_once '../includes/auth.php';
checkAccess(['super_admin']);
include '../includes/header.php';

$students = $conn->query("
    SELECT s.*, b.branch_name, b.business_type 
    FROM students s
    LEFT JOIN branches b ON s.branch_id = b.id
    ORDER BY s.id DESC
");
?>

<div class="animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-users-viewfinder text-primary me-2"></i>Global Clients / Students Directory</h2>
            <p class="text-muted mb-0">Complete overview of all registered entities across the entire network.</p>
        </div>
    </div>

    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4 py-3">Entity Name</th>
                            <th class="py-3">Branch Center</th>
                            <th class="py-3">Contact</th>
                            <th class="py-3">Industry Field</th>
                            <th class="pe-4 py-3 text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($students && $students->num_rows > 0): ?>
                            <?php while($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <div class="text-muted small">Ref: <?php echo htmlspecialchars($row['father_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fas fa-building text-primary me-1"></i><?php echo htmlspecialchars($row['branch_name'] ?? 'Unassigned'); ?>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary rounded-pill px-2 mt-1" style="font-size: 8px; letter-spacing: 1.5px;">
                                        <?php echo strtoupper($row['business_type'] ?? 'OTHER'); ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($row['contact']); ?></td>
                                <td>
                                    <div class="small text-dark"><?php echo htmlspecialchars($row['industry_field_1'] ?? '—'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['industry_field_2'] ?? '—'); ?></div>
                                </td>
                                <td class="pe-4 text-end">
                                    <span class="badge <?php echo $row['status'] == 'active' ? 'bg-success bg-opacity-10 text-success border border-success' : 'bg-secondary bg-opacity-10 text-muted border border-secondary'; ?> rounded-pill px-3">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No records found across any branch.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
