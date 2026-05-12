<?php
require_once 'includes/db.php';
$res = $conn->query("DESCRIBE branches");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
echo "\n--- users ---\n";
$res = $conn->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
