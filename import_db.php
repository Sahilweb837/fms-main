<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "fees_management";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop existing database if exists to start fresh
$conn->query("DROP DATABASE IF EXISTS `$dbname`");
$conn->query("CREATE DATABASE `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db($dbname);

$sql = file_get_contents('db.sql');

// Remove USE statement if it conflicts or just let it be if it matches
// The db.sql already has CREATE DATABASE IF NOT EXISTS `fees_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; USE `fees_management`;

if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Database schema imported successfully from db.sql\n";
} else {
    echo "Error importing schema: " . $conn->error . "\n";
}

$conn->close();
?>
