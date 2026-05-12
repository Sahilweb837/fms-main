<?php
require_once 'includes/db.php';

$username = 'sahilsandhu';
$plain    = '12345';
$hash     = password_hash($plain, PASSWORD_DEFAULT);
$role     = 'super_admin';

// Try update first, then insert
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$existing = $check->get_result();

if ($existing->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE users SET password=?, plain_password=?, role=? WHERE username=?");
    $stmt->bind_param("ssss", $hash, $plain, $role, $username);
    $stmt->execute();
    echo "Updated user '$username' — Password: $plain | Role: $role\n";
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, password, plain_password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hash, $plain, $role);
    $stmt->execute();
    echo "Created user '$username' — ID: {$conn->insert_id} | Password: $plain | Role: $role\n";
}

if ($conn->error) {
    echo "Error: " . $conn->error . "\n";
}
?>
