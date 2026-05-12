<?php
require_once 'includes/db.php';

// Find actual FK constraint names
$result = $conn->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'fees_management' AND TABLE_NAME = 'students' AND REFERENCED_TABLE_NAME IS NOT NULL
");
echo "=== students FK constraints ===\n";
while ($row = $result->fetch_assoc()) {
    echo $row['CONSTRAINT_NAME'] . " -> " . $row['COLUMN_NAME'] . " REF " . $row['REFERENCED_TABLE_NAME'] . "(" . $row['REFERENCED_COLUMN_NAME'] . ")\n";
}

$result2 = $conn->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'fees_management' AND TABLE_NAME = 'fees' AND REFERENCED_TABLE_NAME IS NOT NULL
");
echo "\n=== fees FK constraints ===\n";
while ($row = $result2->fetch_assoc()) {
    echo $row['CONSTRAINT_NAME'] . " -> " . $row['COLUMN_NAME'] . " REF " . $row['REFERENCED_TABLE_NAME'] . "(" . $row['REFERENCED_COLUMN_NAME'] . ")\n";
}
?>
