<?php
// Secure and standardized session initialization with proxy support
if (session_status() === PHP_SESSION_NONE) {
    // Detect if we are on HTTPS (including via proxy)
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
    );

    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days persistence
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!defined('APP_URL')) {
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
    );
    $protocol = $is_https ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $dir = str_replace('\\', '/', dirname($script));
    $base_dir = preg_replace('/(\/admin|\/school|\/college|\/it_institution|\/dispensary|\/hotel|\/shop|\/restaurant|\/inventory|\/company|\/pages|\/includes|\/staff)$/', '', $dir);
    $base_dir = rtrim($base_dir, '/');
    define('APP_URL', $protocol . "://" . $host . $base_dir);
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
