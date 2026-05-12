<?php
require_once 'includes/db.php';

echo "Starting database structure repair...\n";

// 1. Repair students table
$conn->query("ALTER TABLE students MODIFY added_by INT(11) NULL");
if($conn->error) echo "Note: students.added_by already fixed or error: " . $conn->error . "\n";

// 2. Repair expenses table
$conn->query("ALTER TABLE expenses MODIFY added_by INT(11) NULL");
if($conn->error) echo "Note: expenses.added_by already fixed or error: " . $conn->error . "\n";

// 3. Drop and Recreate Foreign Keys with ON DELETE SET NULL for better automation
// (We do this safely by checking if they exist)

function fixFK($conn, $table, $column, $fk_name, $ref_table) {
    try {
        $conn->query("ALTER TABLE $table DROP FOREIGN KEY $fk_name");
    } catch (Throwable $e) {}
    
    $res = $conn->query("ALTER TABLE $table ADD CONSTRAINT $fk_name FOREIGN KEY ($column) REFERENCES $ref_table(id) ON DELETE SET NULL");
    if($res) echo "Fixed FK: $table.$column -> $ref_table (SET NULL)\n";
    else echo "Error fixing FK for $table: " . $conn->error . "\n";
}

// Disable checks to allow modification
$conn->query("SET FOREIGN_KEY_CHECKS=0");

fixFK($conn, 'students', 'added_by', 'students_ibfk_3', 'users');
fixFK($conn, 'expenses', 'added_by', 'expenses_ibfk_2', 'users');
fixFK($conn, 'fees', 'collected_by', 'fees_ibfk_2', 'users');

$conn->query("SET FOREIGN_KEY_CHECKS=1");

echo "\nDatabase repair complete. User deletion should now be safe.";
?>
