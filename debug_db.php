<?php
include 'includes/db.php';
$res = $conn->query("DESCRIBE fees");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
