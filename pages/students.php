<?php
require_once '../includes/auth.php';
include '../includes/header.php';

$message = "";

// Determine industry context
$btype = $_SESSION['business_type'] ?? 'other';
$industry_labels = [
    'school'      => ['entity'=>'Student',  'field1_label'=>'Class',        'field2_label'=>'Section',    'ref_label'=>'Father / Guardian', 'inst_label'=>'School Name'],
    'college'     => ['entity'=>'Student',  'field1_label'=>'Semester',     'field2_label'=>'Year',       'ref_label'=>'Father / Guardian', 'inst_label'=>'College / University'],
    'dispensary'  => ['entity'=>'Patient',  'field1_label'=>'Patient Type', 'field2_label'=>'Patient ID', 'ref_label'=>'Referred By (Doctor)', 'inst_label'=>'Referred From (Clinic)'],
    'shop'        => ['entity'=>'Customer', 'field1_label'=>'Category',     'field2_label'=>'Item SKU',   'ref_label'=>'Sales Person',       'inst_label'=>'Source / Lead'],
    'hotel'       => ['entity'=>'Guest',    'field1_label'=>'Room Type',    'field2_label'=>'Room No.',   'ref_label'=>'Booking Agent',      'inst_label'=>'Check-in Source'],
    'restaurant'  => ['entity'=>'Customer', 'field1_label'=>'Table / Order','field2_label'=>'Order ID',  'ref_label'=>'Served By',          'inst_label'=>'Reservation Source'],
    'inventory'   => ['entity'=>'Client',   'field1_label'=>'Category',     'field2_label'=>'Item Code',  'ref_label'=>'Account Manager',    'inst_label'=>'Company / Org'],
    'company'     => ['entity'=>'Employee', 'field1_label'=>'Department',   'field2_label'=>'Employee ID','ref_label'=>'Reporting Manager',  'inst_label'=>'Company Branch'],
    'other'       => ['entity'=>'Client',   'field1_label'=>'Category',     'field2_label'=>'Reference',  'ref_label'=>'Contact Person',     'inst_label'=>'Organization'],
];
$lbl = $industry_labels[$btype] ?? $industry_labels['other'];

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = $_POST['student_name'];
    $father = $_POST['father_name'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $college = $_POST['college'];
    $course_id = $_POST['course_id'];
    $total_fees = $_POST['total_fees'];
    $duration = $_POST['duration'];
    $f1 = $_POST['industry_field_1'] ?? '';
    $f2 = $_POST['industry_field_2'] ?? '';
    $ref = $_POST['industry_ref'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO students (student_name, father_name, contact, email, college, course_id, total_fees, duration, industry_field_1, industry_field_2, industry_ref, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssidsssi", $name, $father, $contact, $email, $college, $course_id, $total_fees, $duration, $f1, $f2, $ref, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $sid = $conn->insert_id;
        logActivity($conn, $_SESSION['user_id'], "Add {$lbl['entity']}", "Added {$lbl['entity']}: $name (ID: $sid). Total: ₹$total_fees");
        $message = "<div class='alert alert-success border-0 shadow HUD-alert animate-up'><i class='fas fa-check-circle me-2'></i> {$lbl['entity']} <strong>$name</strong> registered successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger border-0 shadow HUD-alert animate-up'>Error: " . $conn->error . "</div>";
    }
}

// Handle Edit Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $sid = $_POST['student_id'];
    $name = $_POST['student_name'];
    $father = $_POST['father_name'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $college = $_POST['college'];
    $course_id = $_POST['course_id'];
    $total_fees = $_POST['total_fees'];
    $duration = $_POST['duration'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE students SET student_name=?, father_name=?, contact=?, email=?, college=?, course_id=?, total_fees=?, duration=?, status=? WHERE id=?");
    $stmt->bind_param("sssssiddsi", $name, $father, $contact, $email, $college, $course_id, $total_fees, $duration, $status, $sid);
    
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], "Edit Student", "Updated student: $name (ID: $sid).");
        $message = "<div class='alert alert-success border-0 shadow HUD-alert animate-up'><i class='fas fa-sync-alt me-2'></i> Profile updated successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger border-0 shadow HUD-alert animate-up'>Error updating student record.</div>";
    }
}

// Handle Delete Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $sid = $_POST['student_id'];
    $stmt = $conn->prepare("SELECT student_name FROM students WHERE id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $name = ($res && $row = $res->fetch_assoc()) ? ($row['student_name'] ?? 'Unknown') : 'Unknown';

    if ($conn->query("DELETE FROM students WHERE id = $sid")) {
        logActivity($conn, $_SESSION['user_id'], "Delete Student", "Deleted student: $name (ID: $sid).");
        $message = "<div class='alert alert-success border-0 shadow HUD-alert animate-up'><i class='fas fa-trash-alt me-2'></i> Student record removed.</div>";
    }
}

$stats_res = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(total_fees) as total_val,
        (SELECT SUM(amount) FROM fees) as total_collected
    FROM students
");
$stats = ($stats_res && $row = $stats_res->fetch_assoc()) ? $row : ['total' => 0, 'active' => 0, 'total_val' => 0, 'total_collected' => 0];

// Handle cases where non-admins might see stats
if (!isAdmin()) {
    $stats['total_val'] = 0;
    $stats['total_collected'] = 0;
}

// Fetch Students with Monthly & One-time Status
$students_query = $conn->query("
    SELECT s.*, c.course_name, u.username as added_by_name,
    (SELECT SUM(amount) FROM fees WHERE student_id = s.id AND fee_type IN ('monthly', 'full_payment')) as total_paid,
    (SELECT COUNT(*) FROM fees WHERE student_id = s.id AND fee_type = 'monthly' AND MONTH(date_collected) = MONTH(CURRENT_DATE()) AND YEAR(date_collected) = YEAR(CURRENT_DATE())) as current_month_paid,
    (SELECT COUNT(*) FROM fees WHERE student_id = s.id AND fee_type = 'exam') as exam_paid,
    (SELECT COUNT(*) FROM fees WHERE student_id = s.id AND fee_type = 'registration') as reg_paid
    FROM students s 
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users u ON s.added_by = u.id 
    ORDER BY s.id DESC
");

$all_students = [];
$paid_count = 0;
$unpaid_count = 0;

while($students_query && $row = $students_query->fetch_assoc()) {
    if($row['current_month_paid'] > 0) $paid_count++; else $unpaid_count++;
    $all_students[] = $row;
}

$courses_res = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");
$course_list = [];
while($courses_res && $c = $courses_res->fetch_assoc()) { $course_list[] = $c; }
?>

<div class="animate-up">
    <!-- Header Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-primary">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px;">Total Enrollment</div>
                <div class="h4 fw-bold mb-0 text-dark"><?php echo number_format($stats['total']); ?></div>
                <div class="small text-success fw-bold" style="font-size: 10px;"><i class="fas fa-users me-1"></i>Students</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-success">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px;">Active Students</div>
                <div class="h4 fw-bold mb-0 text-success"><?php echo number_format($stats['active']); ?></div>
                <div class="progress mt-2" style="height: 3px; background: rgba(0,0,0,0.05);">
                    <div class="progress-bar bg-success" style="width: <?php echo ($stats['total'] > 0 ? ($stats['active']/$stats['total'])*100 : 0); ?>%"></div>
                </div>
            </div>
        </div>
        <?php if(isAdmin()): ?>
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-info">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px;">Monthly Paid (<?php echo date('M'); ?>)</div>
                <div class="h4 fw-bold mb-0 text-info"><?php echo $paid_count; ?></div>
                <div class="small text-muted" style="font-size: 10px;">Students who paid</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="HUD-card p-3 border-start border-4 border-danger">
                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 10px;">Monthly Unpaid</div>
                <div class="h4 fw-bold mb-0 text-danger"><?php echo $unpaid_count; ?></div>
                <div class="small text-muted" style="font-size: 10px;">Pending for <?php echo date('F'); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <?php
                $panel_icons = ['school'=>'fa-school','college'=>'fa-university','dispensary'=>'fa-clinic-medical','shop'=>'fa-store','hotel'=>'fa-hotel','restaurant'=>'fa-utensils','inventory'=>'fa-boxes-stacked','company'=>'fa-building','other'=>'fa-users'];
                $picon = $panel_icons[$btype] ?? 'fa-users';
            ?>
            <h2 class="fw-bold text-dark"><i class="fas <?php echo $picon; ?> text-primary me-2"></i><?php echo $lbl['entity']; ?> Directory</h2>
            <p class="text-muted mb-0">Unified <?php echo strtolower($lbl['entity']); ?> profiles and billing. <span class="badge bg-primary-subtle text-primary rounded-pill px-2 ms-1" style="font-size:10px;"><?php echo strtoupper($btype); ?> PANEL</span></p>
        </div>
        <div class="d-flex gap-2">
            <?php if(isAdmin()): ?>
            <button class="btn btn-outline-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#paymentDashboardModal">
                <i class="fas fa-chart-pie me-2"></i>Status Dashboard
            </button>
            <?php endif; ?>
            <div class="position-relative d-none d-md-block" style="width: 250px;">
                <i class="fas fa-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                <input type="text" id="studentSearch" class="form-control ps-5 rounded-pill shadow-sm border-0 bg-white" placeholder="Search <?php echo strtolower($lbl['entity']); ?>s...">
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-user-plus me-2"></i>New <?php echo $lbl['entity']; ?>
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="studentTable">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4 py-3">Profile</th>
                            <th class="py-3">Institute & Course</th>
                            <th class="py-3">Monthly status</th>
                            <?php if(isAdmin()): ?>
                            <th class="py-3">Financial Status</th>
                            <?php endif; ?>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_students as $row): 
                            $paid = $row['total_paid'] ?? 0;
                            $remaining = $row['total_fees'] - $paid;
                            $percent = $row['total_fees'] > 0 ? ($paid / $row['total_fees']) * 100 : 0;
                            $monthPaid = $row['current_month_paid'] > 0;
                            $examPaid = $row['exam_paid'] > 0;
                            $regPaid = $row['reg_paid'] > 0;
                        ?>
                        <tr class="student-row">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-box bg-primary-gradient text-white rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; font-weight: 600;">
                                        <?php echo strtoupper(substr($row['student_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                        <div class="text-muted small"><i class="fas fa-phone-alt me-1" style="font-size: 10px;"></i><?php echo htmlspecialchars($row['contact']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small mb-1"><i class="fas fa-university me-1 text-primary"></i><?php echo htmlspecialchars($row['college'] ?? 'N/A'); ?></div>
                                <span class="badge bg-light text-dark border-0 p-0 fw-medium"><i class="fas fa-book-open me-1 text-info"></i><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <div class="mb-1">
                                    <?php if($monthPaid): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fw-bold" style="font-size: 9px;"><i class="fas fa-calendar-check me-1"></i>MONTHLY</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1 fw-bold" style="font-size: 9px;"><i class="fas fa-calendar-times me-1"></i>MONTHLY</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-1">
                                    <span class="badge <?php echo $examPaid ? 'bg-info-subtle text-info' : 'bg-light text-muted'; ?> rounded-pill px-2" style="font-size: 8px;" title="Examination Fee">EXAM</span>
                                    <span class="badge <?php echo $regPaid ? 'bg-primary-subtle text-primary' : 'bg-light text-muted'; ?> rounded-pill px-2" style="font-size: 8px;" title="Registration Fee">REG</span>
                                </div>
                            </td>
                            <?php if(isAdmin()): ?>
                            <td style="min-width: 160px;">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-success fw-bold">₹<?php echo number_format($paid); ?></span>
                                    <span class="text-danger fw-bold">₹<?php echo number_format($remaining); ?></span>
                                </div>
                                <div class="progress" style="height: 4px; border-radius: 10px; background-color: rgba(0,0,0,0.05);">
                                    <div class="progress-bar <?php echo $remaining <= 0 ? 'bg-success' : 'bg-primary'; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 9px;">Target: ₹<?php echo number_format($row['total_fees']); ?></div>
                            </td>
                            <?php endif; ?>
                            <td class="pe-4 text-end">
                                <div class="btn-group shadow-sm rounded-pill overflow-hidden border">
                                    <button class="btn btn-white btn-sm px-3 edit-btn" title="Edit Profile"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['student_name']); ?>"
                                        data-father="<?php echo htmlspecialchars($row['father_name']); ?>"
                                        data-contact="<?php echo htmlspecialchars($row['contact']); ?>"
                                        data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                        data-college="<?php echo htmlspecialchars($row['college']); ?>"
                                        data-course="<?php echo $row['course_id']; ?>"
                                        data-fees="<?php echo $row['total_fees']; ?>"
                                        data-duration="<?php echo $row['duration']; ?>"
                                        data-status="<?php echo $row['status']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editStudentModal">
                                        <i class="fas fa-edit text-warning"></i>
                                    </button>
                                    <a href="fees.php?student_id=<?php echo $row['id']; ?>" class="btn btn-white btn-sm px-3 border-start" title="Collect Fees">
                                        <i class="fas fa-wallet text-primary"></i>
                                    </a>
                                    <button class="btn btn-white btn-sm px-3 border-start" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['student_name']); ?>')">
                                        <i class="fas fa-trash-alt text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Status Dashboard Modal -->
<div class="modal fade" id="paymentDashboardModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content glass-card border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-primary-gradient text-white p-4">
        <h5 class="modal-title fw-bold"><i class="fas fa-chart-line me-2"></i>Financial Status Overview - <?php echo date('F Y'); ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="row g-0">
          <div class="col-md-6 border-end">
            <div class="p-4 bg-success-subtle bg-opacity-10">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-success mb-0"><i class="fas fa-check-circle me-2"></i>Monthly Paid (<?php echo $paid_count; ?>)</h6>
                <span class="badge bg-success rounded-pill"><?php echo date('M'); ?></span>
              </div>
              <div class="list-group list-group-flush rounded-4 overflow-hidden shadow-sm" style="max-height: 400px; overflow-y: auto;">
                <?php foreach($all_students as $s): if($s['current_month_paid'] > 0): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-0">
                    <div class="d-flex align-items-center">
                      <div class="avatar-sm bg-success text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 12px;"><?php echo strtoupper(substr($s['student_name'], 0, 1)); ?></div>
                      <div>
                        <div class="fw-bold small"><?php echo htmlspecialchars($s['student_name']); ?></div>
                        <div class="text-muted d-flex gap-1" style="font-size: 9px;">
                            <?php if($s['reg_paid']): ?><span class="text-primary fw-bold">REG ✓</span><?php endif; ?>
                            <?php if($s['exam_paid']): ?><span class="text-info fw-bold">EXAM ✓</span><?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-bold" style="font-size: 10px;">COMPLETED</span>
                  </div>
                <?php endif; endforeach; ?>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-4 bg-danger-subtle bg-opacity-10">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-danger mb-0"><i class="fas fa-clock me-2"></i>Pending Monthly (<?php echo $unpaid_count; ?>)</h6>
                <span class="badge bg-danger rounded-pill">DUE</span>
              </div>
              <div class="list-group list-group-flush rounded-4 overflow-hidden shadow-sm" style="max-height: 400px; overflow-y: auto;">
                <?php foreach($all_students as $s): if($s['current_month_paid'] == 0): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-0">
                    <div class="d-flex align-items-center">
                      <div class="avatar-sm bg-danger text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 12px;"><?php echo strtoupper(substr($s['student_name'], 0, 1)); ?></div>
                      <div>
                        <div class="fw-bold small"><?php echo htmlspecialchars($s['student_name']); ?></div>
                        <div class="text-muted" style="font-size: 10px;"><?php echo htmlspecialchars($s['course_name']); ?></div>
                      </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="fees.php?student_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm" style="font-size: 10px;">Pay Now</a>
                    </div>
                  </div>
                <?php endif; endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 p-4 bg-light">
        <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-dismiss="modal">Close Dashboard</button>
      </div>
    </div>
  </div>
</div>

<!-- Dynamic Industry-Aware Registration Form -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content glass-card border-0 shadow-lg rounded-4">
      <form method="POST" class="HUD-form">
          <input type="hidden" name="action" value="add">
          <div class="modal-header border-0 p-4 pb-2">
            <div>
                <h5 class="modal-title fw-bold text-dark mb-0">
                    <i class="fas fa-user-plus me-2 text-primary"></i>
                    New <?php echo $lbl['entity']; ?> Registration
                </h5>
                <div class="text-muted small mt-1">
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3" style="font-size:10px;">
                        <i class="fas <?php echo $picon; ?> me-1"></i><?php echo strtoupper($btype); ?> FORM
                    </span>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">

            <!-- Section 1: Core Identity -->
            <div class="p-3 mb-3 rounded-3 border" style="background:#f8fafc;">
                <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:1px;"><i class="fas fa-id-card me-2 text-primary"></i>Personal Info</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted"><?php echo $lbl['entity']; ?> Full Name</label>
                        <input type="text" name="student_name" class="form-control rounded-3" placeholder="Enter full name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted"><?php echo $lbl['ref_label']; ?></label>
                        <input type="text" name="father_name" class="form-control rounded-3" placeholder="<?php echo $lbl['ref_label']; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Contact Number</label>
                        <input type="text" name="contact" class="form-control rounded-3" placeholder="Mobile / Phone" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Email Address</label>
                        <input type="email" name="email" class="form-control rounded-3" placeholder="email@domain.com">
                    </div>
                </div>
            </div>

            <!-- Section 2: Industry-Specific Fields -->
            <div class="p-3 mb-3 rounded-3 border" style="background:#f0f7ff;">
                <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:1px;"><i class="fas fa-tags me-2 text-info"></i><?php echo strtoupper($btype); ?> Details</div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted"><?php echo $lbl['inst_label']; ?></label>
                        <input type="text" name="college" class="form-control rounded-3" placeholder="<?php echo $lbl['inst_label']; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted"><?php echo $lbl['field1_label']; ?></label>
                        <input type="text" name="industry_field_1" class="form-control rounded-3" placeholder="Enter <?php echo $lbl['field1_label']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted"><?php echo $lbl['field2_label']; ?></label>
                        <input type="text" name="industry_field_2" class="form-control rounded-3" placeholder="Enter <?php echo $lbl['field2_label']; ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Additional Reference</label>
                        <input type="text" name="industry_ref" class="form-control rounded-3" placeholder="Optional reference info">
                    </div>
                </div>
            </div>

            <!-- Section 3: Billing -->
            <div class="p-3 rounded-3 border" style="background:#fff7ed;">
                <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:1px;"><i class="fas fa-wallet me-2 text-warning"></i>Billing & Plan</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Service / Course</label>
                        <select name="course_id" id="course_select" class="form-select rounded-3" required>
                            <option value="">-- Select --</option>
                            <?php foreach($course_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-total="<?php echo $c['total_fee']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Total Amount (₹)</label>
                        <input type="number" name="total_fees" id="total_fees_input" class="form-control rounded-3 fw-bold text-primary" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Plan Validity</label>
                        <select name="duration" class="form-select rounded-3" required>
                            <option value="30_days">30 Days</option>
                            <option value="45_days">45 Days</option>
                            <option value="3_months">3 Months</option>
                            <option value="6_months">6 Months</option>
                            <option value="1_year">1 Year</option>
                        </select>
                    </div>
                </div>
            </div>

          </div>
          <div class="modal-footer border-0 p-4 pt-2">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 shadow"><i class="fas fa-check me-2"></i>Register <?php echo $lbl['entity']; ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content glass-card border-0 shadow-lg rounded-4">
      <form method="POST">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="student_id" id="edit_id">
          <div class="modal-header border-0 p-4">
            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-edit me-2 text-warning"></i>Profile Update</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4 pt-0">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Full Name</label>
                    <input type="text" name="student_name" id="edit_name" class="form-control rounded-3" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Guardian Name</label>
                    <input type="text" name="father_name" id="edit_father" class="form-control rounded-3" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Primary Contact</label>
                    <input type="text" name="contact" id="edit_contact" class="form-control rounded-3" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control rounded-3">
                </div>
                <div class="col-md-12">
                    <label class="form-label small fw-bold text-muted text-uppercase">Institution</label>
                    <input type="text" name="college" id="edit_college" class="form-control rounded-3" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Enrolled Course</label>
                    <select name="course_id" id="edit_course" class="form-select rounded-3" required>
                        <?php foreach($course_list as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Total Fees</label>
                    <input type="number" name="total_fees" id="edit_fees" class="form-control rounded-3 fw-bold" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Lifecycle</label>
                    <select name="status" id="edit_status" class="form-select rounded-3" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <input type="hidden" name="duration" id="edit_duration">
            </div>
          </div>
          <div class="modal-footer border-0 p-4">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Save Changes</button>
          </div>
      </form>
    </div>
  </div>
</div>

<style>
    .HUD-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; }
    .HUD-alert { border-left: 4px solid var(--first-color); border-radius: 8px; font-weight: 600; background: #fff; }
    .avatar-box { background: #f8fafc; color: #64748b; font-weight: 700; border: 1px solid #e2e8f0; }
    .student-row:hover { background-color: #f8fafc !important; }
    .btn-white { background: #fff; color: #64748b; border: 1px solid #e2e8f0; }
    .btn-white:hover { background: #f8fafc; color: #1a1a1a; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Advanced Search
    const searchInput = document.getElementById('studentSearch');
    const table = document.getElementById('studentTable');
    const rows = table.getElementsByClassName('student-row');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        for (let i = 0; i < rows.length; i++) {
            const text = rows[i].innerText.toLowerCase();
            rows[i].style.display = text.includes(filter) ? "" : "none";
        }
    });

    // Modal populate
    const editBtns = document.querySelectorAll('.edit-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_father').value = this.getAttribute('data-father');
            document.getElementById('edit_contact').value = this.getAttribute('data-contact');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            document.getElementById('edit_college').value = this.getAttribute('data-college');
            document.getElementById('edit_course').value = this.getAttribute('data-course');
            document.getElementById('edit_fees').value = this.getAttribute('data-fees');
            document.getElementById('edit_duration').value = this.getAttribute('data-duration');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
        });
    });

    const courseSelect = document.getElementById('course_select');
    const totalFeesInput = document.getElementById('total_fees_input');
    if (courseSelect && totalFeesInput) {
        courseSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            totalFeesInput.value = opt.getAttribute('data-total') || "";
        });
    }
});

function confirmDelete(id, name) {
    if (confirm(`Confirm permanent deletion of ${name}? All financial and attendance history will be lost.`)) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="${id}">`;
        document.body.appendChild(f);
        f.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>

