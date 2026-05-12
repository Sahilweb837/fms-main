<?php
include 'includes/db.php';
$sql = "ALTER TABLE fees MODIFY COLUMN fee_type ENUM('monthly', 'registration', 'exam', 'other') NOT NULL";
if ($conn->query($sql)) {
    echo "Fee type updated successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>
