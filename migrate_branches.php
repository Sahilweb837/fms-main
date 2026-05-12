<?php
require_once 'includes/db.php';

echo "Adding logo_url and description to branches table...\n";

$query1 = "ALTER TABLE branches ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER email";
$query2 = "ALTER TABLE branches ADD COLUMN description TEXT DEFAULT NULL AFTER logo_url";

if ($conn->query($query1)) {
    echo "Successfully added logo_url column.\n";
} else {
    echo "Error adding logo_url column: " . $conn->error . "\n";
}

if ($conn->query($query2)) {
    echo "Successfully added description column.\n";
} else {
    echo "Error adding description column: " . $conn->error . "\n";
}

echo "Migration complete.\n";
?>
