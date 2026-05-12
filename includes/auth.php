<?php
require_once __DIR__ . '/db.php';

// Prevent Varnish and browser caching on Cloudways
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// ─── Redirect to login if not authenticated ───────────────────────
if (!isset($_SESSION['user_id'])) {
    // Determine the correct login portal based on current path
    $is_admin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
    $login_url = $is_admin ? APP_URL . "/admin/login.php" : APP_URL . "/index.php";
    
    header("Location: $login_url");
    exit();
}

// ─── Core session variables ───────────────────────────────────────
$user_id       = $_SESSION['user_id'];
$username      = $_SESSION['username'];
$role          = $_SESSION['role'];
$branch_id     = $_SESSION['branch_id']  ?? null;
$business_type = $_SESSION['business_type'] ?? 'other';

// ─── Trial & Pro Status ───────────────────────────────────────────
$is_pro = true;
$trial_expired = false;
if ($branch_id && $role !== 'super_admin') {
    $br = $conn->query("SELECT created_at, is_pro FROM branches WHERE id = " . (int)$branch_id)->fetch_assoc();
    if ($br) {
        $days_active = (time() - strtotime($br['created_at'])) / (60 * 60 * 24);
        $is_pro = (bool)$br['is_pro'];
        $trial_expired = ($days_active > 30 && !$is_pro);
    }
}

// ─── Role checks ──────────────────────────────────────────────────
function isSuperAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin']);
}

function isEmployee(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

/** True for super_admin OR admin (both can manage users for their scope) */
function canManageUsers(): bool {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin']);
}

function moduleExists(string $module): bool {
    $module = trim($module, '/');
    if ($module === '') return false;
    return is_file(__DIR__ . "/../{$module}/dashboard.php");
}

function getPanelModule(string $business_type = null): string {
    $business_type = $business_type ?: ($_SESSION['business_type'] ?? 'other');
    return moduleExists($business_type) ? $business_type : 'pages';
}

function getDashboardUrl(string $business_type = null): string {
    $module = getPanelModule($business_type);
    return APP_URL . "/{$module}/dashboard.php";
}

function getModuleUrl(string $page, string $business_type = null): string {
    $module = getPanelModule($business_type);
    $page = ltrim($page, '/');

    if ($module === 'pages') {
        if ($page === 'users.php') {
            return APP_URL . "/admin/users.php";
        }

        $fallbacks = [
            'members.php'  => 'students.php',
            'payments.php' => 'fees.php',
        ];
        $page = $fallbacks[$page] ?? $page;
    }

    return APP_URL . "/{$module}/{$page}";
}

// ─── Access gate ──────────────────────────────────────────────────
function checkAccess(array $allowed_roles): void {
    if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
        http_response_code(403);
        $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'javascript:history.back()';
        echo '<!DOCTYPE html><html><head>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            </head><body class="bg-dark d-flex align-items-center justify-content-center" style="min-height:100vh">
            <div class="text-center text-white p-5">
                <i class="fas fa-ban fa-4x text-danger mb-4"></i>
                <h2 class="fw-bold">Access Denied</h2>
                <p class="text-muted">You do not have permission to view this page.</p>
                <a href="' . $back . '" class="btn btn-outline-light rounded-pill px-4">Go Back</a>
            </div></body></html>';
        exit();
    }
}

// ─── Multi-tenant SQL filter ──────────────────────────────────────
/**
 * Returns SQL AND fragment to restrict to current branch.
 * Super admins see ALL. Others see only their branch.
 * @param string $alias  Table alias (e.g. 's' → 's.branch_id')
 */
function getBranchWhere(string $alias = ''): string {
    if (isSuperAdmin()) return '';
    $bid = (int)($_SESSION['branch_id'] ?? 0);
    $col = $alias ? "{$alias}.branch_id" : 'branch_id';
    return $bid > 0 ? " AND {$col} = {$bid}" : '';
}

/**
 * Returns first WHERE condition (WHERE instead of AND).
 */
function getBranchWhereFirst(string $alias = ''): string {
    if (isSuperAdmin()) return '1=1';
    $bid = (int)($_SESSION['branch_id'] ?? 0);
    $col = $alias ? "{$alias}.branch_id" : 'branch_id';
    return $bid > 0 ? "{$col} = {$bid}" : '1=0';
}

// ─── Industry ID generator ────────────────────────────────────────
/**
 * Generate a formatted employee ID based on business type and role.
 * @param mysqli  $conn
 * @param string  $business_type e.g. 'school'
 * @param string  $role          e.g. 'admin' or 'employee'
 * @return string e.g. 'SCH-ADM-001'
 */
function generateEmployeeId(mysqli $conn, string $business_type, string $role): string {
    $prefixes = [
        'school'     => 'SCH',
        'college'    => 'COL',
        'restaurant' => 'REST',
        'hotel'      => 'HTL',
        'shop'       => 'SHP',
        'dispensary' => 'CLN',
        'inventory'  => 'INV',
        'company'    => 'CMP',
        'it_institution' => 'ITI',
        'other'      => 'GEN',
    ];
    $type_prefix = $prefixes[$business_type] ?? 'GEN';
    $role_code   = ($role === 'admin') ? 'ADM' : (($role === 'super_admin') ? 'SUP' : 'EMP');

    // Find next sequential number
    $res = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE employee_id LIKE '{$type_prefix}-{$role_code}-%'");
    $cnt = ($res && $row = $res->fetch_assoc()) ? (int)$row['cnt'] : 0;
    $seq = str_pad($cnt + 1, 3, '0', STR_PAD_LEFT);
    return "{$type_prefix}-{$role_code}-{$seq}";
}

/**
 * Generate a unique entity ID for a student/patient/guest/customer.
 * @param mysqli $conn
 * @param string $business_type
 * @return string e.g. 'SCH-2025-0001', 'HTL-GST-0042'
 */
function generateEntityId(mysqli $conn, string $business_type): string {
    $map = [
        'school'     => 'SCH',
        'college'    => 'COL',
        'restaurant' => 'REST',
        'hotel'      => 'HTL',
        'shop'       => 'SHP',
        'dispensary' => 'CLN',
        'inventory'  => 'INV',
        'company'    => 'CMP',
        'it_institution' => 'ITI',
        'other'      => 'GEN',
    ];
    $prefix = $map[$business_type] ?? 'GEN';
    $year   = date('Y');

    $res = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE entity_id LIKE '{$prefix}-{$year}-%'");
    $cnt = ($res && $row = $res->fetch_assoc()) ? (int)$row['cnt'] : 0;
    $seq = str_pad($cnt + 1, 4, '0', STR_PAD_LEFT);
    return "{$prefix}-{$year}-{$seq}";
}
?>
