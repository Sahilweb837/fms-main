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
    "ALTER TABLE fees ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(50) DEFAULT 'cash' AFTER collected_by",
    "ALTER TABLE fees ADD COLUMN IF NOT EXISTS utr_number VARCHAR(100) DEFAULT NULL AFTER payment_mode"
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
