<?php
require_once '../includes/auth.php';
checkAccess(['super_admin', 'admin']);
include '../includes/header.php';

$bid    = (int)($_SESSION['branch_id'] ?? 0);
$where  = isSuperAdmin() ? '1=1' : "u.branch_id = $bid";

$logs = $conn->query("
    SELECT a.*, u.username, u.role, u.employee_id, b.branch_name
    FROM activity_logs a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE $where
    ORDER BY a.id DESC
    LIMIT 500
");
?>
<div class="animate-up">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-history me-2 text-primary"></i>Activity Audit Log</h2>
        <p class="text-muted mb-0 small">Real-time trail of all user actions <?php echo !isSuperAdmin() ? 'for your branch.' : 'across all branches.'; ?></p>
    </div>
    <?php if(isSuperAdmin()): ?>
    <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-halved me-1"></i>Super Admin View</span>
    <?php endif; ?>
</div>

<div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="p-3 border-bottom bg-white d-flex gap-2">
            <input type="text" id="logSearch" class="form-control form-control-sm rounded-pill" placeholder="🔍 Search logs..." style="max-width:280px;">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4 py-3">#</th>
                        <th class="py-3">Timestamp</th>
                        <th class="py-3">User</th>
                        <th class="py-3">Role</th>
                        <?php if(isSuperAdmin()): ?><th class="py-3">Branch</th><?php endif; ?>
                        <th class="py-3">Action</th>
                        <th class="py-3">Details</th>
                        <th class="pe-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody id="logTable">
                <?php if($logs && $logs->num_rows > 0): while($row = $logs->fetch_assoc()): ?>
                <tr class="log-row">
                    <td class="ps-4 small text-muted"><?php echo $row['id']; ?></td>
                    <td class="small">
                        <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($row['created_at'])); ?></div>
                        <div class="text-muted" style="font-size:10px;"><?php echo date('h:i:s A', strtotime($row['created_at'])); ?></div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['username']); ?></div>
                        <?php if($row['employee_id']): ?><code style="font-size:0.68rem;color:var(--first-color);"><?php echo htmlspecialchars($row['employee_id']); ?></code><?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $rc = $row['role']; $bg = ['super_admin'=>'bg-danger','admin'=>'bg-warning text-dark','employee'=>'bg-info text-dark'][$rc] ?? 'bg-secondary';
                        echo "<span class='badge $bg rounded-pill px-2'>".strtoupper(str_replace('_',' ',$rc))."</span>";
                        ?>
                    </td>
                    <?php if(isSuperAdmin()): ?>
                    <td class="small text-muted"><?php echo htmlspecialchars($row['branch_name'] ?? '—'); ?></td>
                    <?php endif; ?>
                    <td><span class="badge bg-primary rounded-pill px-3" style="font-size:0.7rem;"><?php echo htmlspecialchars($row['action']); ?></span></td>
                    <td class="small text-muted" style="max-width:250px;"><?php echo htmlspecialchars($row['details']); ?></td>
                    <td class="pe-4 text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($row['ip_address'] ?? ''); ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center py-5 text-muted">No activity logs yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<script>
document.getElementById('logSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.log-row').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none');
});
</script>
<?php include '../includes/footer.php'; ?>
