<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "fees_management";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

// Read SQL file
$sql = file_get_contents('db.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    do {
        /* store first result set */
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Database initialized successfully.\n";
} else {
    echo "Error initializing database: " . $conn->error . "\n";
}

$conn->close();
?>
