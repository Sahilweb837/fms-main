<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FMS — Database Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
.setup-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2.5rem; max-width: 700px; width: 100%; }
pre { background: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 1rem; font-size: 0.8rem; max-height: 300px; overflow-y: auto; }
.step { border-left: 3px solid #3b82f6; padding: 0.75rem 1rem; margin-bottom: 1rem; background: rgba(59,130,246,0.08); border-radius: 0 10px 10px 0; }
</style>
</head>
<body>
<div class="setup-card">
    <h2 class="fw-bold mb-2" style="color:#f8fafc;">⚙️ FMS Enterprise — Database Setup</h2>
    <p class="text-muted mb-4">This script will create all required tables and the default Super Admin account.</p>

<?php
$host = "localhost"; $user = "root"; $pass = ""; $dbname = "fees_management";

// Create DB if not exists
$conn0 = new mysqli($host, $user, $pass);
if ($conn0->connect_error) {
    echo "<div class='alert alert-danger rounded-3'>❌ Cannot connect to MySQL: ".$conn0->connect_error."</div>";
    exit();
}
$conn0->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn0->close();

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo "<div class='alert alert-danger rounded-3'>❌ Cannot select database.</div>"; exit();
}

$sql_file = __DIR__ . '/db.sql';
if (!file_exists($sql_file)) {
    echo "<div class='alert alert-danger rounded-3'>❌ db.sql not found.</div>"; exit();
}

$sql  = file_get_contents($sql_file);
// Split on semicolon; filter empty
$queries = array_filter(array_map('trim', explode(';', $sql)));
$errors  = []; $ok = 0;

foreach ($queries as $q) {
    if (empty($q) || strpos(strtoupper($q), 'SET') === 0 && strlen($q) < 50) continue;
    if (!$conn->query($q)) {
        $errors[] = "<code>".htmlspecialchars(substr($q,0,120))."...</code><br><small class='text-danger'>".$conn->error."</small>";
    } else {
        $ok++;
    }
}

echo "<div class='step'><strong>✅ Database:</strong> <code>$dbname</code> ready.</div>";
echo "<div class='step'><strong>✅ Executed:</strong> $ok SQL statements successfully.</div>";

if ($errors) {
    echo "<div class='alert alert-warning rounded-3 mt-2'><strong>".count($errors)." warnings (may be normal for DROP TABLE):</strong><ul class='mt-2 mb-0'>";
    foreach ($errors as $e) echo "<li class='mb-1'>$e</li>";
    echo "</ul></div>";
}

// Check super admin
$sa = $conn->query("SELECT id, username, employee_id FROM users WHERE role='super_admin' LIMIT 1");
if ($sa && $row = $sa->fetch_assoc()) {
    echo "<div class='step' style='border-color:#10b981;background:rgba(16,185,129,0.08);'><strong>✅ Super Admin Account:</strong><br>Username: <code>superadmin</code> &nbsp;|&nbsp; Password: <code>admin123</code> &nbsp;|&nbsp; ID: <code>{$row['employee_id']}</code></div>";
} else {
    echo "<div class='alert alert-danger rounded-3'>⚠️ Super Admin not found. Check db.sql INSERT statement.</div>";
}
?>

    <div class="mt-4 p-3 rounded-3" style="background:rgba(255,255,255,0.05);">
        <h6 class="text-warning fw-bold">🚀 Next Steps:</h6>
        <ol class="text-muted small mb-0">
            <li>Go to <a href="index.php" class="text-info">Login Portal</a></li>
            <li>Select <strong style="color:#ffd700;">Central Admin</strong> tile</li>
            <li>Login: <code>superadmin</code> / <code>admin123</code></li>
            <li>Create a <strong>Branch</strong> (e.g. "City School" → School type)</li>
            <li>Create an <strong>Admin</strong> for that branch → they get a <code>SCH-ADM-XXX</code> ID</li>
            <li>That Admin logs in via the <strong>School</strong> tile and manages their branch</li>
        </ol>
    </div>

    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-primary rounded-pill px-5 fw-bold">Go to Login Portal →</a>
        <div class="text-muted mt-3" style="font-size:0.75rem;">⚠️ Delete this setup.php file after first use.</div>
    </div>
</div>
</body>
</html>
