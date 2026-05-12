<?php
require_once 'includes/db.php';
if ($conn->query("ALTER TABLE users ADD COLUMN employee_id VARCHAR(50) DEFAULT NULL AFTER id")) {
    echo "Added employee_id\n";
} else {
    echo "Error or already exists: " . $conn->error . "\n";
}
