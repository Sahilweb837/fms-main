<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "fees_management";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$res = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($res->num_rows == 0) {
    echo "Database '$dbname' does not exist.\n";
} else {
    echo "Database '$dbname' exists.\n";
    $conn->select_db($dbname);
    $res = $conn->query("SHOW TABLES");
    echo "Tables in '$dbname':\n";
    while ($row = $res->fetch_array()) {
        echo "- " . $row[0] . "\n";
    }
}
?>
