<?php
require_once 'includes/db.php';

$sql = "ALTER TABLE attendance ADD COLUMN method ENUM('manual', 'biometric') NOT NULL DEFAULT 'manual' AFTER status";
if ($conn->query($sql)) {
    echo "SUCCESS: attendance table updated with method column.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
