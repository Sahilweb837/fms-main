<?php
require_once '../includes/db.php';

if (isset($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], "Logout", "User logged out of the system.");
}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header("Location: ../index.php");
exit();
?>
