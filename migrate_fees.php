<?php
include 'includes/db.php';
$sql = "ALTER TABLE fees 
        ADD COLUMN payment_mode ENUM('cash', 'online', 'upi', 'cheque') NOT NULL DEFAULT 'cash', 
        ADD COLUMN utr_number VARCHAR(100) DEFAULT NULL";

if ($conn->query($sql)) {
    echo "Columns added successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>
