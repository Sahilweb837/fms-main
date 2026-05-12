<?php
require_once '../includes/auth.php';
include '../includes/header.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$course_filter = isset($_GET['course_id']) ? $_GET['course_id'] : '';

// Fetch Courses for Filter
$courses = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");

// Fetch Attendance Stats for the selected date
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leaves
    FROM attendance 
    WHERE attendance_date = '$date'
");
$stats = ($stats_query && $row = $stats_query->fetch_assoc()) ? $row : ['total' => 0, 'present' => 0, 'absent' => 0, 'leaves' => 0];

// Fetch Students with Attendance Status
$query = "
    SELECT s.id, s.student_name, s.father_name, s.contact, c.course_name, 
           a.status as attendance_status, a.attendance_time, a.method
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = '$date'
    WHERE s.status = 'active'
";
if ($course_filter) {
    $query .= " AND s.course_id = " . intval($course_filter);
}
$query .= " ORDER BY s.student_name ASC";
$students = $conn->query($query);
?>

<div class="animate-up overflow-hidden">
    <!-- Header & Summary -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-4">
            <h2 class="fw-bold text-dark">Attendance Tracker</h2>
            <p class="text-muted">Register for <span class="text-primary fw-bold"><?php echo date('d M Y', strtotime($date)); ?></span></p>
        </div>
        <div class="col-md-8">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <div class="p-3 glass-card bg-success-subtle border-success border-opacity-10 text-center rounded-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Present</div>
                        <div class="h4 fw-bold text-success mb-0" id="stat-present"><?php echo $stats['present'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 glass-card bg-danger-subtle border-danger border-opacity-10 text-center rounded-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Absent</div>
                        <div class="h4 fw-bold text-danger mb-0" id="stat-absent"><?php echo $stats['absent'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 glass-card bg-warning-subtle border-warning border-opacity-10 text-center rounded-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Leave</div>
                        <div class="h4 fw-bold text-warning mb-0" id="stat-leaves"><?php echo $stats['leaves'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 glass-card bg-primary-subtle border-primary border-opacity-10 text-center rounded-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Total</div>
                        <div class="h4 fw-bold text-primary mb-0"><?php echo $stats['total'] ?? 0; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card glass-card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form class="row g-3" method="GET">
                <div class="col-md-2">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-calendar text-muted"></i></span>
                        <input type="date" name="date" class="form-control border-start-0 rounded-end-pill" value="<?php echo $date; ?>" onchange="this.form.submit()">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-book text-muted"></i></span>
                        <select name="course_id" class="form-select border-start-0 rounded-end-pill" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php 
                            if ($courses):
                                $courses->data_seek(0);
                                while($c = $courses->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $course_filter == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name']); ?>
                                    </option>
                                <?php endwhile; 
                            endif; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-fingerprint text-muted"></i></span>
                        <select id="attendanceMethod" class="form-select border-start-0 rounded-end-pill">
                            <option value="manual">Manual</option>
                            <option value="biometric">Biometric</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="position-relative">
                        <i class="fas fa-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="attendanceSearch" class="form-control ps-5 rounded-pill" placeholder="Search students...">
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div id="attendance-msg" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Attendance Table -->
    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="attendanceTable">
                    <thead class="bg-light">
                        <tr style="border-bottom: 2px solid #eee;">
                            <th class="ps-4 py-3">Student Profile</th>
                            <th class="py-3">Course</th>
                            <th class="py-3">Check-in</th>
                            <th class="py-3 text-center" style="width: 250px;">Mark Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceBody">
                        <?php 
                        $count = 0;
                        if ($students):
                            while($row = $students->fetch_assoc()): 
                                $count++;
                                $hiddenClass = ($count > 15) ? 'lazy-row d-none' : '';
                            ?>
                            <tr class="student-row <?php echo $hiddenClass; ?>" data-id="<?php echo $row['id']; ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary-gradient text-white rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; font-weight: 600;">
                                            <?php echo strtoupper(substr($row['student_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                            <div class="text-muted small"><i class="fas fa-phone me-1" style="font-size: 10px;"></i><?php echo $row['contact']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-medium"><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="attendance-time-<?php echo $row['id']; ?>">
                                    <?php if($row['attendance_status'] == 'present'): ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($row['attendance_time'])); ?> <?php if($row['method'] == 'biometric'): ?><i class="fas fa-fingerprint ms-1 text-primary" title="Biometric"></i><?php endif; ?></span>
                                    <?php elseif($row['attendance_status'] == 'leave'): ?>
                                        <span class="text-warning small fw-bold"><i class="fas fa-sign-out-alt me-1"></i>On Leave</span>
                                    <?php else: ?>
                                        <span class="text-muted small">--:--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="btn-group p-1 bg-light rounded-pill border shadow-sm attendance-group" data-student-id="<?php echo $row['id']; ?>">
                                        <button type="button" 
                                            title="Present"
                                            class="btn btn-sm rounded-pill px-3 mark-btn <?php echo $row['attendance_status'] == 'present' ? 'btn-success' : 'btn-light'; ?>" 
                                            onclick="markAttendance(<?php echo $row['id']; ?>, 'present')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" 
                                            title="Absent"
                                            class="btn btn-sm rounded-pill px-3 mark-btn <?php echo $row['attendance_status'] == 'absent' ? 'btn-danger' : 'btn-light'; ?>" 
                                            onclick="markAttendance(<?php echo $row['id']; ?>, 'absent')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <button type="button" 
                                            title="Leave"
                                            class="btn btn-sm rounded-pill px-3 mark-btn <?php echo $row['attendance_status'] == 'leave' ? 'btn-warning text-white' : 'btn-light'; ?>" 
                                            onclick="markAttendance(<?php echo $row['id']; ?>, 'leave')">
                                            <i class="fas fa-calendar-minus"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; 
                        else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No students found or database error.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($count > 15): ?>
            <div class="p-3 text-center bg-light border-top" id="loadMoreContainer">
                <button class="btn btn-primary rounded-pill px-4 btn-sm" onclick="loadMore()">Load More Students <i class="fas fa-chevron-down ms-2"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .mark-btn { transition: all 0.2s ease; border: none !important; }
    .mark-btn:hover { transform: scale(1.1); }
    .student-row { transition: background 0.2s; border-bottom: 1px solid #f8f9fa; }
    .student-row:hover { background-color: #fffaf0 !important; }
    .avatar-sm { font-size: 14px; }
    .table-responsive { scrollbar-width: thin; }
    /* Fix overflow */
    body { overflow-x: hidden; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Custom Search
    const searchInput = document.getElementById('attendanceSearch');
    const table = document.getElementById('attendanceTable');
    const rows = table.getElementsByClassName('student-row');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        let visibleCount = 0;
        for (let i = 0; i < rows.length; i++) {
            const name = rows[i].querySelector('.fw-bold').innerText.toLowerCase();
            const contact = rows[i].querySelector('.text-muted').innerText.toLowerCase();
            if (name.indexOf(filter) > -1 || contact.indexOf(filter) > -1) {
                rows[i].classList.remove('d-none');
                visibleCount++;
            } else {
                rows[i].classList.add('d-none');
            }
        }
        
        // Hide load more if searching
        const loadMore = document.getElementById('loadMoreContainer');
        if (loadMore) {
            loadMore.style.display = filter ? "none" : "block";
        }
    });
});

function loadMore() {
    const lazyRows = document.querySelectorAll('.lazy-row');
    let loaded = 0;
    lazyRows.forEach(row => {
        if (row.classList.contains('d-none') && loaded < 15) {
            row.classList.remove('d-none', 'lazy-row');
            loaded++;
        }
    });
    
    if (document.querySelectorAll('.lazy-row').length === 0) {
        document.getElementById('loadMoreContainer').style.display = "none";
    }
}

function markAttendance(studentId, status) {
    const date = "<?php echo $date; ?>";
    const time = new Date().toLocaleTimeString('en-GB', { hour12: false });
    
    // Optimistic UI update
    const group = document.querySelector(`.attendance-group[data-student-id="${studentId}"]`);
    const btns = group.querySelectorAll('.mark-btn');
    
    // Save current state for rollback
    const prevState = {
        p: btns[0].className,
        a: btns[1].className,
        l: btns[2].className
    };

    // Update Buttons
    btns[0].className = 'btn btn-sm rounded-pill px-3 mark-btn ' + (status === 'present' ? 'btn-success' : 'btn-light');
    btns[1].className = 'btn btn-sm rounded-pill px-3 mark-btn ' + (status === 'absent' ? 'btn-danger' : 'btn-light');
    btns[2].className = 'btn btn-sm rounded-pill px-3 mark-btn ' + (status === 'leave' ? 'btn-warning text-white' : 'btn-light');

    fetch('save_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `student_id=${studentId}&status=${status}&date=${date}&time=${time}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update Time Cell
            const timeCell = document.querySelector(`.attendance-time-${studentId}`);
            if(status === 'present') {
                timeCell.innerHTML = `<span class="text-success small fw-bold"><i class="fas fa-clock me-1"></i>${data.formatted_time}</span>`;
            } else if(status === 'leave') {
                timeCell.innerHTML = `<span class="text-warning small fw-bold"><i class="fas fa-sign-out-alt me-1"></i>On Leave</span>`;
            } else {
                timeCell.innerHTML = `<span class="text-muted small">--:--</span>`;
            }

            // Update Stats (Partial refresh would be better, but let's just update numbers)
            updateLocalStats();
            
            showToast('Attendance updated', 'success');
        } else {
            // Rollback
            btns[0].className = prevState.p;
            btns[1].className = prevState.a;
            btns[2].className = prevState.l;
            showToast('Error: ' + data.message, 'danger');
        }
    })
    .catch(err => {
        btns[0].className = prevState.p;
        btns[1].className = prevState.a;
        btns[2].className = prevState.l;
        showToast('Connection error', 'danger');
    });
}

function updateLocalStats() {
    // This is a simple way to update the summary boxes without a full page reload
    let p = 0, a = 0, l = 0;
    document.querySelectorAll('.attendance-group').forEach(group => {
        const btns = group.querySelectorAll('.mark-btn');
        if (btns[0].classList.contains('btn-success')) p++;
        if (btns[1].classList.contains('btn-danger')) a++;
        if (btns[2].classList.contains('btn-warning')) l++;
    });
    
    document.getElementById('stat-present').innerText = p;
    document.getElementById('stat-absent').innerText = a;
    document.getElementById('stat-leave').innerText = l;
}

function showToast(msg, type) {
    const container = document.getElementById('attendance-msg');
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} shadow-lg border-0 rounded-4 animate-up mb-2 py-2 px-3 small`;
    toast.style.minWidth = "200px";
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i> ${msg}`;
    container.appendChild(toast);
    setTimeout(() => { 
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}
</script>

<?php include '../includes/footer.php'; ?>

