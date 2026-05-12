<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "fees_management";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$queries = [
    "ALTER TABLE students MODIFY COLUMN duration ENUM('30_days', '45_days', '3_months', '6_months', '1_year') NOT NULL DEFAULT '30_days'",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS total_fees DECIMAL(10,2) DEFAULT 0.00 AFTER course_id"
];

foreach ($queries as $query) {
    if ($conn->query($query)) {
        echo "Success: $query\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

$conn->close();
?>
