<?php
require_once 'includes/db.php';

$sql = "ALTER TABLE students 
        ADD COLUMN industry_field_1 VARCHAR(100) DEFAULT NULL COMMENT 'Class/Semester/Category',
        ADD COLUMN industry_field_2 VARCHAR(100) DEFAULT NULL COMMENT 'Section/Year/SKU',
        ADD COLUMN industry_ref VARCHAR(100) DEFAULT NULL COMMENT 'Ref Person/Doctor/Father'";

if ($conn->query($sql)) {
    echo "Successfully added industry-specific columns to students table.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
