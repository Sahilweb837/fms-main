<?php
require_once 'includes/db.php';

$sql = "ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL AFTER password";

if ($conn->query($sql)) {
    echo "Successfully added plain_password to users table.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
