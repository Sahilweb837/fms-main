<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
$btype = $_SESSION['business_type'] ?? 'other';
$role  = $_SESSION['role'] ?? 'employee';

// Industry-aware labels and icons
$industry_config = [
    'school'     => ['label' => 'School ERP',   'icon' => 'fa-school',       'entity' => 'Students',   'color' => '#ff7b00'],
    'college'    => ['label' => 'College ERP',  'icon' => 'fa-university',   'entity' => 'Students',   'color' => '#ff7b00'],
    'restaurant' => ['label' => 'Restaurant',   'icon' => 'fa-utensils',     'entity' => 'Orders',     'color' => '#ff7b00'],
    'hotel'      => ['label' => 'Hotel Mgmt',   'icon' => 'fa-hotel',        'entity' => 'Guests',     'color' => '#ff7b00'],
    'shop'       => ['label' => 'Shop POS',     'icon' => 'fa-store',        'entity' => 'Customers',  'color' => '#ff7b00'],
    'dispensary' => ['label' => 'Clinic Mgmt',  'icon' => 'fa-clinic-medical','entity' => 'Patients',  'color' => '#ff7b00'],
    'inventory'  => ['label' => 'Inventory',    'icon' => 'fa-boxes-stacked','entity' => 'Clients',   'color' => '#ff7b00'],
    'company'    => ['label' => 'Company ERP',  'icon' => 'fa-building',     'entity' => 'Employees', 'color' => '#ff7b00'],
    'it_institution' => ['label' => 'IT Institute', 'icon' => 'fa-laptop-code',  'entity' => 'Students',  'color' => '#ff7b00'],
    'other'      => ['label' => 'FMS Pro',      'icon' => 'fa-briefcase',    'entity' => 'Clients',   'color' => '#ff7b00'],
];

$icfg = $industry_config[$btype] ?? $industry_config['other'];

// Determine path prefix
$prefix = APP_URL . '/';

// User initials for avatar
$uname = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'U';
$initials = strtoupper(substr($uname, 0, 1));
if (strpos($uname, ' ') !== false) {
    $parts = explode(' ', $uname);
    $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}
$role_label = ucwords(str_replace('_', ' ', $role));
?>
<!-- Sidebar -->
<div class="sidebar-wrapper" id="sidebar-wrapper">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-icon">
                <i class="fas <?php echo $icfg['icon']; ?>"></i>
            </div>
            <div>
                <div class="brand-title">Net<span>Coder</span></div>
                <div class="brand-subtitle"><?php echo $icfg['label']; ?></div>
            </div>
        </div>
    </div>

    <!-- User Profile Card -->
    <div class="sidebar-profile">
        <div class="profile-avatar"><?php echo $initials; ?></div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
            <div class="profile-role">
                <span class="role-dot"></span>
                <?php echo $role_label; ?>
            </div>
        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="sidebar-nav">

        <?php /* ── SUPER ADMIN LINKS ── */ if ($role === 'super_admin'): ?>
        <div class="nav-section-label">Global Control</div>
        <a href="<?php echo $prefix; ?>admin/index.php" class="nav-link-item <?php echo ($current_page == 'index.php' && $current_dir == 'admin') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-shield-halved"></i></div>
            <span>Admin Dashboard</span>
        </a>
        <a href="<?php echo $prefix; ?>admin/branches.php" class="nav-link-item <?php echo ($current_page == 'branches.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-building"></i></div>
            <span>Manage Branches</span>
        </a>
        <a href="<?php echo $prefix; ?>admin/users.php" class="nav-link-item <?php echo ($current_page == 'users.php' && $current_dir == 'admin') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-users-gear"></i></div>
            <span>System Users</span>
        </a>
        <a href="<?php echo $prefix; ?>admin/students.php" class="nav-link-item <?php echo ($current_page == 'students.php' && $current_dir == 'admin') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-users-viewfinder"></i></div>
            <span>All Records</span>
        </a>
        <a href="<?php echo $prefix; ?>admin/fees.php" class="nav-link-item <?php echo ($current_page == 'fees.php' && $current_dir == 'admin') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
            <span>Global Revenue</span>
        </a>
        <a href="<?php echo $prefix; ?>admin/logs.php" class="nav-link-item <?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-history"></i></div>
            <span>Audit Logs</span>
        </a>

        <?php /* ── BRANCH ADMIN LINKS ── */ elseif ($role === 'admin'): ?>
        <div class="nav-section-label"><?php echo $icfg['label']; ?></div>
        <?php if($btype == 'other'): ?>
        <a href="<?php echo $prefix; ?>pages/dashboard.php" class="nav-link-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-gauge-high"></i></div>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/students.php" class="nav-link-item <?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            <span>Clients</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/fees.php" class="nav-link-item <?php echo ($current_page == 'fees.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div>
            <span>Payments</span>
        </a>
        <div class="nav-section-label">Administration</div>
        <?php $lc = (isset($trial_expired) && $trial_expired) ? 'pro-locked' : ''; ?>
        <a href="<?php echo $prefix; ?>pages/users.php" class="nav-link-item <?php echo ($current_page == 'users.php') ? 'active ' : ''; echo $lc; ?>">
            <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
            <span>My Staff</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/courses.php" class="nav-link-item <?php echo ($current_page == 'courses.php') ? 'active ' : ''; echo $lc; ?>">
            <div class="nav-icon"><i class="fas fa-book-open"></i></div>
            <span>Courses/Services</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/expenses.php" class="nav-link-item <?php echo ($current_page == 'expenses.php') ? 'active ' : ''; echo $lc; ?>">
            <div class="nav-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <span>Expenses</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/attendance.php" class="nav-link-item <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-calendar-check"></i></div>
            <span>Attendance</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/reports.php" class="nav-link-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
            <span>Reports</span>
        </a>
        <?php else: ?>
        <a href="<?php echo $prefix . $btype; ?>/dashboard.php" class="nav-link-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-gauge-high"></i></div>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $prefix . $btype; ?>/members.php" class="nav-link-item <?php echo ($current_page == 'members.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            <span><?php echo $icfg['entity']; ?></span>
        </a>
        <a href="<?php echo $prefix . $btype; ?>/payments.php" class="nav-link-item <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div>
            <span>Payments</span>
        </a>
        <div class="nav-section-label">Administration</div>
        <?php $lc = (isset($trial_expired) && $trial_expired) ? 'pro-locked' : ''; ?>
        <a href="<?php echo $prefix . $btype; ?>/users.php" class="nav-link-item <?php echo ($current_page == 'users.php') ? 'active ' : ''; echo $lc; ?>">
            <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
            <span>My Staff</span>
        </a>
        <a href="<?php echo $prefix . $btype; ?>/courses.php" class="nav-link-item <?php echo ($current_page == 'courses.php') ? 'active ' : ''; echo $lc; ?>">
            <div class="nav-icon"><i class="fas fa-book-open"></i></div>
            <span><?php echo in_array($btype, ['restaurant','hotel']) ? 'Services/Menus' : (($btype == 'shop') ? 'Products' : 'Courses'); ?></span>
        </a>
        <a href="<?php echo $prefix; ?>pages/expenses.php" class="nav-link-item <?php echo ($current_page == 'expenses.php') ? 'active ' : ''; echo $lc; ?>">
            <div class="nav-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <span>Expenses</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/attendance.php" class="nav-link-item <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-calendar-check"></i></div>
            <span>Attendance</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/reports.php" class="nav-link-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
            <span>Reports</span>
        </a>
        <?php endif; ?>

        <?php /* ── EMPLOYEE LINKS ── */ else: ?>
        <div class="nav-section-label">My Panel</div>
        <?php if($btype == 'other'): ?>
        <a href="<?php echo $prefix; ?>pages/dashboard.php" class="nav-link-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-gauge-high"></i></div>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/students.php" class="nav-link-item <?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            <span>Clients</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/fees.php" class="nav-link-item <?php echo ($current_page == 'fees.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div>
            <span>Collect Payment</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/attendance.php" class="nav-link-item <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-calendar-check"></i></div>
            <span>Attendance</span>
        </a>
        <?php else: ?>
        <a href="<?php echo $prefix . $btype; ?>/dashboard.php" class="nav-link-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-gauge-high"></i></div>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $prefix . $btype; ?>/members.php" class="nav-link-item <?php echo ($current_page == 'members.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            <span><?php echo $icfg['entity']; ?></span>
        </a>
        <a href="<?php echo $prefix . $btype; ?>/payments.php" class="nav-link-item <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div>
            <span>Collect Payment</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/attendance.php" class="nav-link-item <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-calendar-check"></i></div>
            <span>Attendance</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="<?php echo $prefix; ?>pages/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
        <div class="sidebar-version">NetCoder ERP v2.0</div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════ */
/* CLEAN WHITE & ORANGE SIDEBAR                               */
/* ═══════════════════════════════════════════════════════════ */
.sidebar-wrapper {
    min-height: 100vh;
    width: var(--sidebar-width, 260px);
    background: #ffffff;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 100;
    border-right: 1px solid #f1f5f9;
    box-shadow: 4px 0 24px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}

/* Brand */
.sidebar-brand {
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
}
.brand-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--primary, #ff7b00);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(255, 123, 0, 0.2);
}
.brand-icon i { color: #fff; font-size: 1.1rem; }
.brand-title {
    font-size: 1.1rem; font-weight: 800; color: #1e293b;
    letter-spacing: -0.02em; line-height: 1;
}
.brand-title span {
    color: var(--primary, #ff7b00);
}
.brand-subtitle {
    font-size: 0.6rem; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 1.5px;
    margin-top: 4px; font-weight: 700;
}

/* User Profile Card */
.sidebar-profile {
    display: flex; align-items: center; gap: 12px;
    padding: 1rem;
    margin: 1rem 0.75rem;
    background: #f8fafc;
    border-radius: 12px;
}
.profile-avatar {
    width: 38px; height: 38px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    color: var(--primary, #ff7b00); font-weight: 800; font-size: 0.85rem;
    flex-shrink: 0;
}
.profile-info { overflow: hidden; }
.profile-name {
    color: #1e293b; font-weight: 600;
    font-size: 0.8rem; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.profile-role {
    color: #64748b; font-size: 0.6rem;
    text-transform: uppercase; letter-spacing: 0.05em;
    font-weight: 700; display: flex; align-items: center; gap: 4px;
    margin-top: 2px;
}
.role-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: #10b981;
}

/* Navigation */
.sidebar-nav {
    flex: 1; overflow-y: auto; padding: 0.5rem 0;
}
.nav-section-label {
    padding: 1.25rem 1.25rem 0.5rem;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #94a3b8;
    font-weight: 700;
}
.nav-link-item {
    display: flex; align-items: center; gap: 12px;
    padding: 0.7rem 1.25rem;
    margin: 2px 0.75rem;
    border-radius: 10px;
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}
.nav-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: #f8fafc;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.nav-icon i { font-size: 0.9rem; }

.nav-link-item:hover {
    color: var(--primary, #ff7b00);
    background: #fff8f1;
}
.nav-link-item:hover .nav-icon {
    color: var(--primary, #ff7b00);
    background: #fff;
}

.nav-link-item.active {
    color: var(--primary, #ff7b00);
    background: #fff8f1;
    font-weight: 700;
}
.nav-link-item.active .nav-icon {
    background: var(--primary, #ff7b00);
    color: #fff;
    box-shadow: 0 4px 10px rgba(255, 123, 0, 0.2);
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1.25rem;
    border-top: 1px solid #f1f5f9;
}
.logout-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 0.75rem;
    border-radius: 10px;
    color: #ef4444;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    background: #fef2f2;
}
.logout-btn:hover {
    background: #fee2e2;
}
.sidebar-version {
    text-align: center;
    color: #94a3b8;
    font-size: 0.6rem;
    margin-top: 1rem;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar-wrapper { 
        width: var(--sidebar-width, 260px); 
        position: fixed; 
        left: calc(-1 * var(--sidebar-width, 260px)); 
        height: 100%;
    }
    .wrapper.toggled .sidebar-wrapper { 
        left: 0; 
    }
}
</style>

