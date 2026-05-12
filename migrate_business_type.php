<?php
require_once 'includes/db.php';

$sql = "ALTER TABLE branches ADD COLUMN business_type ENUM('school', 'college', 'company', 'shop', 'hotel', 'restaurant', 'dispensary', 'inventory', 'other') DEFAULT 'other' AFTER branch_name";

if ($conn->query($sql)) {
    echo "Successfully added business_type to branches table.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
