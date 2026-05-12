<?php
require_once 'includes/db.php';
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not Set') . "<br>";
if (!isset($_SESSION['test_val'])) {
    $_SESSION['test_val'] = rand(1000, 9999);
    echo "Set test_val to: " . $_SESSION['test_val'];
} else {
    echo "test_val is already: " . $_SESSION['test_val'];
}
?>
