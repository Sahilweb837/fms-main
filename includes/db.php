<?php
// Secure and standardized session initialization
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

if (!defined('APP_URL')) {
    $script = $_SERVER['SCRIPT_NAME'];
    $dir = str_replace('\\', '/', dirname($script));
    $base_dir = preg_replace('/(\/admin|\/school|\/college|\/it_institution|\/dispensary|\/hotel|\/shop|\/restaurant|\/inventory|\/company|\/pages|\/includes|\/staff)$/', '', $dir);
    $base_dir = rtrim($base_dir, '/');
    
    // Use absolute path to avoid HTTP/HTTPS protocol mismatches caused by Cloudways proxies
    define('APP_URL', $base_dir);
}
$host = "localhost";
$user = "mhqhxuaasp";
$pass = "4m3xU8bTVq";
$dbname = "mhqhxuaasp";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to log activity
function logActivity($conn, $user_id, $action, $details = "") {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Verify user still exists before logging (avoids FK constraint failure)
        $check = $conn->query("SELECT id FROM users WHERE id = " . (int)$user_id);
        if (!$check || $check->num_rows === 0) return;
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Silently fail — logging should never crash the app
    }
}
?>
