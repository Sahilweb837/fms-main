<?php
require_once 'includes/db.php';

$sql = "ALTER TABLE fees MODIFY COLUMN fee_type ENUM('monthly', 'registration', 'exam', 'full_payment', 'other') NOT NULL";
if ($conn->query($sql)) {
    echo "SUCCESS: fee_type enum updated.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
