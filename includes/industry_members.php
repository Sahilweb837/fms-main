<?php
/**
 * SHARED INDUSTRY MEMBERS (CRUD) TEMPLATE
 * Include from each industry folder's members.php
 * Required: $btype_key, $entity_label, $labels (array of field labels)
 */
require_once __DIR__ . '/auth.php';
if (!isSuperAdmin() && $_SESSION['business_type'] !== $btype_key) {
    header("Location: ../index.php"); exit();
}
include __DIR__ . '/header.php';

$msg = "";
$bid = (int)($_SESSION['branch_id'] ?? 0);
$bwhere = getBranchWhere('s');

// ── ADD ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $entity_id = generateEntityId($conn, $btype_key);
    $name    = trim($_POST['student_name']);
    $father  = trim($_POST['father_name'] ?? '');
    $contact = trim($_POST['contact']);
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $college = trim($_POST['college'] ?? '');
    $cid     = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $fees    = (float)($_POST['total_fees'] ?? 0);
    $dur     = $_POST['duration'] ?? '30_days';
    $f1      = trim($_POST['industry_field_1'] ?? '');
    $f2      = trim($_POST['industry_field_2'] ?? '');
    $ref     = trim($_POST['industry_ref'] ?? '');
    $gender  = $_POST['gender'] ?? null;
    $dob     = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $target_bid = $bid ?: null;

    $stmt = $conn->prepare("INSERT INTO students (entity_id, student_name, father_name, contact, email, address, college, branch_id, course_id, total_fees, duration, industry_field_1, industry_field_2, industry_ref, gender, dob, added_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssssiiidsssssi", $entity_id, $name, $father, $contact, $email, $address, $college, $target_bid, $cid, $fees, $dur, $f1, $f2, $ref, $gender, $dob, $_SESSION['user_id']);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], "Add {$entity_label}", "Registered {$entity_label}: $name ($entity_id)");
        $msg = "<div class='alert alert-success border-0 rounded-3 shadow-sm animate-up'><i class='fas fa-check-circle me-2'></i><strong>$name</strong> registered! ID: <code style='background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:4px;'>$entity_id</code></div>";
    } else {
        $msg = "<div class='alert alert-danger border-0 rounded-3'>Error: ".$conn->error."</div>";
    }
}

// ── EDIT ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'edit') {
    $sid    = (int)$_POST['student_id'];
    $name   = trim($_POST['student_name']);
    $father = trim($_POST['father_name'] ?? '');
    $contact= trim($_POST['contact']);
    $email  = trim($_POST['email'] ?? '');
    $college= trim($_POST['college'] ?? '');
    $cid    = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $fees   = (float)($_POST['total_fees'] ?? 0);
    $dur    = $_POST['duration'] ?? '30_days';
    $status = $_POST['status'] ?? 'active';
    $f1     = trim($_POST['industry_field_1'] ?? '');
    $f2     = trim($_POST['industry_field_2'] ?? '');
    $ref    = trim($_POST['industry_ref'] ?? '');

    $stmt = $conn->prepare("UPDATE students SET student_name=?,father_name=?,contact=?,email=?,college=?,course_id=?,total_fees=?,duration=?,status=?,industry_field_1=?,industry_field_2=?,industry_ref=? WHERE id=?");
    $stmt->bind_param("sssssidsssssi", $name,$father,$contact,$email,$college,$cid,$fees,$dur,$status,$f1,$f2,$ref,$sid);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], "Edit {$entity_label}", "Updated: $name (ID $sid)");
        $msg = "<div class='alert alert-success border-0 rounded-3 shadow-sm animate-up'><i class='fas fa-sync me-2'></i>Record updated.</div>";
    } else {
        $msg = "<div class='alert alert-danger border-0 rounded-3'>Error updating record.</div>";
    }
}

// ── DELETE ─────────────────────────────────────────────────────────
if (isset($_GET['delete']) && isAdmin()) {
    $sid = (int)$_GET['delete'];
    $r = $conn->query("SELECT student_name FROM students WHERE id=$sid")->fetch_assoc();
    if ($r && $conn->query("DELETE FROM students WHERE id=$sid")) {
        logActivity($conn, $_SESSION['user_id'], "Delete {$entity_label}", "Deleted: {$r['student_name']} (ID $sid)");
        $msg = "<div class='alert alert-success border-0 rounded-3 animate-up'><i class='fas fa-trash me-2'></i>Record removed.</div>";
    }
}

// ── FETCH DATA ─────────────────────────────────────────────────────
$students = $conn->query("SELECT s.*, c.course_name, u.username as added_by_name,
    (SELECT SUM(amount) FROM fees WHERE student_id=s.id AND status='paid') as total_paid
    FROM students s
    LEFT JOIN courses c ON s.course_id=c.id
    LEFT JOIN users u ON s.added_by=u.id
    WHERE 1=1 $bwhere ORDER BY s.id DESC");

// For inline edit — pre-load edit target
$edit_student = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_student = $conn->query("SELECT * FROM students WHERE id=$eid $bwhere")->fetch_assoc();
}

// Courses for dropdown (branch-specific + global)
$courses = $conn->query("SELECT * FROM courses WHERE is_active=1 AND (branch_id=$bid OR branch_id IS NULL) ORDER BY course_name");
?>

<div class="animate-up">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1"><i class="fas fa-users me-2 text-primary"></i><?php echo $entity_label; ?>s Registry</h2>
        <p class="text-muted mb-0 small">Manage all <?php echo strtolower($entity_label); ?> records for your branch.</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-2"></i>Register <?php echo $entity_label; ?>
    </button>
</div>

<?php echo $msg; ?>

<!-- Stats Row -->
<?php
$tot = $conn->query("SELECT COUNT(*) as c FROM students s WHERE 1=1 $bwhere")->fetch_assoc()['c'] ?? 0;
$act = $conn->query("SELECT COUNT(*) as c FROM students s WHERE s.status='active' $bwhere")->fetch_assoc()['c'] ?? 0;
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="metric-card p-3 text-center">
            <h4 class="fw-bold mb-0 text-primary"><?php echo $tot; ?></h4>
            <div class="small text-muted mt-1">Total</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card p-3 text-center">
            <h4 class="fw-bold mb-0 text-success"><?php echo $act; ?></h4>
            <div class="small text-muted mt-1">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card p-3 text-center">
            <h4 class="fw-bold mb-0 text-warning"><?php echo $tot - $act; ?></h4>
            <div class="small text-muted mt-1">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card p-3 text-center">
            <a href="payments.php" class="btn btn-primary btn-sm rounded-pill px-3 w-100"><i class="fas fa-wallet me-1"></i>Payments</a>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="p-3 border-bottom d-flex gap-2 bg-white">
            <input type="text" id="searchBox" class="form-control form-control-sm rounded-pill" placeholder="🔍 Search <?php echo strtolower($entity_label); ?>s..." style="max-width:280px;">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="membersTable">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4 py-3"><?php echo $entity_label; ?> ID</th>
                        <th class="py-3">Name</th>
                        <th class="py-3"><?php echo $labels['field1_label']; ?></th>
                        <th class="py-3">Contact</th>
                        <th class="py-3"><?php echo $labels['inst_label']; ?></th>
                        <th class="py-3">Fees</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($students && $students->num_rows > 0): while($s = $students->fetch_assoc()): ?>
                <tr class="member-row">
                    <td class="ps-4">
                        <code style="color:var(--first-color);font-size:0.75rem;"><?php echo htmlspecialchars($s['entity_id'] ?? '#'.$s['id']); ?></code>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['student_name']); ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($s['father_name'] ?? ''); ?></div>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($s['industry_field_1'] ?? '—'); ?></td>
                    <td class="small"><?php echo htmlspecialchars($s['contact']); ?></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($s['college'] ?? '—'); ?></td>
                    <td>
                        <?php if(isAdmin()): ?>
                        <div class="small fw-bold text-success">₹<?php echo number_format($s['total_paid'] ?? 0); ?> <span class="text-muted fw-normal">/ ₹<?php echo number_format($s['total_fees']); ?></span></div>
                        <?php else: echo '<span class="text-muted small">—</span>'; endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $s['status']=='active'?'bg-success-subtle text-success':'bg-secondary-subtle text-secondary'; ?> rounded-pill px-3">
                            <?php echo ucfirst($s['status']); ?>
                        </span>
                    </td>
                    <td class="pe-4 text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="payments.php?student_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-success rounded-pill px-2" title="Collect Payment"><i class="fas fa-wallet"></i></a>
                            <a href="?edit=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php if(isAdmin()): ?>
                            <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="return confirm('Delete this record?')" title="Delete"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x d-block mb-2 opacity-30"></i>
                    No <?php echo strtolower($entity_label); ?>s registered. <a href="#" data-bs-toggle="modal" data-bs-target="#addModal">Register first one</a>.
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header border-0 p-4 pb-2">
            <div>
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Register New <?php echo $entity_label; ?></h5>
                <small class="text-muted">A unique ID (<?php echo strtoupper(substr($btype_key,0,3)); ?>-<?php echo date('Y'); ?>-XXXX) will be auto-generated.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?php echo $entity_label; ?> Name <span class="text-danger">*</span></label>
                    <input type="text" name="student_name" class="form-control" placeholder="Full name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['ref_label']; ?></label>
                    <input type="text" name="father_name" class="form-control" placeholder="<?php echo $labels['ref_label']; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact / Phone <span class="text-danger">*</span></label>
                    <input type="text" name="contact" class="form-control" placeholder="Mobile number" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['inst_label']; ?></label>
                    <input type="text" name="college" class="form-control" placeholder="<?php echo $labels['inst_label']; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">— Select —</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['field1_label']; ?></label>
                    <input type="text" name="industry_field_1" class="form-control" placeholder="<?php echo $labels['field1_label']; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['field2_label']; ?></label>
                    <input type="text" name="industry_field_2" class="form-control" placeholder="<?php echo $labels['field2_label']; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo in_array($btype_key,['restaurant','hotel','shop']) ? 'Service / Plan' : 'Course / Program'; ?></label>
                    <select name="course_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php if($courses): while($c=$courses->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" data-fee="<?php echo $c['total_fee']; ?>" data-monthly="<?php echo $c['monthly_fee']; ?>">
                            <?php echo htmlspecialchars($c['course_name']); ?> <?php echo $c['total_fee']>0?'(₹'.number_format($c['total_fee']).')':''; ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Fee (₹)</label>
                    <input type="number" step="0.01" name="total_fees" id="total_fees_inp" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Duration / Plan</label>
                    <select name="duration" class="form-select">
                        <option value="30_days">30 Days</option>
                        <option value="45_days">45 Days</option>
                        <option value="3_months">3 Months</option>
                        <option value="6_months">6 Months</option>
                        <option value="1_year">1 Year</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Full address (optional)"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 shadow"><i class="fas fa-save me-2"></i>Register</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if($edit_student): ?>
<!-- EDIT MODAL (auto-opens) -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="student_id" value="<?php echo $edit_student['id']; ?>">
        <div class="modal-header border-0 p-4 pb-2">
            <h5 class="modal-title fw-bold"><i class="fas fa-pen me-2 text-primary"></i>Edit <?php echo $entity_label; ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?php echo $entity_label; ?> Name</label>
                    <input type="text" name="student_name" class="form-control" value="<?php echo htmlspecialchars($edit_student['student_name']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['ref_label']; ?></label>
                    <input type="text" name="father_name" class="form-control" value="<?php echo htmlspecialchars($edit_student['father_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($edit_student['contact']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_student['email'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['inst_label']; ?></label>
                    <input type="text" name="college" class="form-control" value="<?php echo htmlspecialchars($edit_student['college'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $edit_student['status']=='active'?'selected':''; ?>>Active</option>
                        <option value="inactive" <?php echo $edit_student['status']=='inactive'?'selected':''; ?>>Inactive</option>
                        <option value="completed" <?php echo $edit_student['status']=='completed'?'selected':''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['field1_label']; ?></label>
                    <input type="text" name="industry_field_1" class="form-control" value="<?php echo htmlspecialchars($edit_student['industry_field_1'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo $labels['field2_label']; ?></label>
                    <input type="text" name="industry_field_2" class="form-control" value="<?php echo htmlspecialchars($edit_student['industry_field_2'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Fee (₹)</label>
                    <input type="number" step="0.01" name="total_fees" class="form-control" value="<?php echo $edit_student['total_fees']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Duration</label>
                    <select name="duration" class="form-select">
                        <?php foreach(['30_days','45_days','3_months','6_months','1_year','custom'] as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $edit_student['duration']==$d?'selected':''; ?>><?php echo str_replace('_',' ',ucfirst($d)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-select">
                        <option value="">— None —</option>
                        <?php
                        $courses2 = $conn->query("SELECT * FROM courses WHERE is_active=1 AND (branch_id=$bid OR branch_id IS NULL) ORDER BY course_name");
                        if($courses2): while($c=$courses2->fetch_assoc()):
                        ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $edit_student['course_id']==$c['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($c['course_name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
            <a href="members.php" class="btn btn-light rounded-pill px-4">Cancel</a>
            <button type="submit" class="btn btn-primary rounded-pill px-5 shadow"><i class="fas fa-save me-2"></i>Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('editModal')).show());
</script>
<?php endif; ?>

<script>
// Auto-fill fee from course selection
document.querySelector('select[name="course_id"]')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const fee = opt.dataset.fee;
    if (fee && fee > 0) document.getElementById('total_fees_inp').value = fee;
});
// Live search
document.getElementById('searchBox').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.member-row').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
