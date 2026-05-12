<?php
require_once 'includes/db.php';

$sql = "ALTER TABLE fees ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER utr_number;";

if ($conn->query($sql)) {
    echo "Successfully added `is_verified` column to `fees` table.";
} else {
    echo "Error or already exists: " . $conn->error;
}
?>
