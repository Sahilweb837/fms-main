<?php
require_once 'includes/db.php';

$migrations = [
    // Check if business_type exists in branches
    "branches.business_type" => [
        "check" => "SHOW COLUMNS FROM branches LIKE 'business_type'",
        "sql"   => "ALTER TABLE branches ADD COLUMN business_type ENUM('school','college','company','shop','hotel','restaurant','dispensary','inventory','other') NOT NULL DEFAULT 'other' AFTER branch_name"
    ],
    // Check if plain_password exists in users
    "users.plain_password" => [
        "check" => "SHOW COLUMNS FROM users LIKE 'plain_password'",
        "sql"   => "ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL AFTER password"
    ],
    // Check if branch_id exists in users
    "users.branch_id" => [
        "check" => "SHOW COLUMNS FROM users LIKE 'branch_id'",
        "sql"   => "ALTER TABLE users ADD COLUMN branch_id INT(11) DEFAULT NULL AFTER role"
    ],
    // Check industry_field_1 in students
    "students.industry_field_1" => [
        "check" => "SHOW COLUMNS FROM students LIKE 'industry_field_1'",
        "sql"   => "ALTER TABLE students ADD COLUMN industry_field_1 VARCHAR(100) DEFAULT NULL AFTER status"
    ],
    // Check industry_field_2 in students
    "students.industry_field_2" => [
        "check" => "SHOW COLUMNS FROM students LIKE 'industry_field_2'",
        "sql"   => "ALTER TABLE students ADD COLUMN industry_field_2 VARCHAR(100) DEFAULT NULL AFTER industry_field_1"
    ],
    // Check industry_ref in students
    "students.industry_ref" => [
        "check" => "SHOW COLUMNS FROM students LIKE 'industry_ref'",
        "sql"   => "ALTER TABLE students ADD COLUMN industry_ref VARCHAR(255) DEFAULT NULL AFTER industry_field_2"
    ],
    // Check total_fees in students
    "students.total_fees" => [
        "check" => "SHOW COLUMNS FROM students LIKE 'total_fees'",
        "sql"   => "ALTER TABLE students ADD COLUMN total_fees DECIMAL(10,2) DEFAULT 0.00 AFTER course_id"
    ],
    // Check branch_id in students
    "students.branch_id" => [
        "check" => "SHOW COLUMNS FROM students LIKE 'branch_id'",
        "sql"   => "ALTER TABLE students ADD COLUMN branch_id INT(11) DEFAULT NULL AFTER college"
    ],
    // Check method column in fees (was payment_mode in some versions)
    "fees.method" => [
        "check" => "SHOW COLUMNS FROM fees LIKE 'method'",
        "sql"   => "ALTER TABLE fees ADD COLUMN method ENUM('cash','online','upi','cheque','card') NOT NULL DEFAULT 'cash' AFTER collected_by"
    ],
];

echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#0f0;} .ok{color:#0f0;} .skip{color:#aaa;} .err{color:#f44;}</style>";
echo "<h2 style='color:#fff'>🔧 Database Migration Runner</h2><hr style='border-color:#333'>";

foreach ($migrations as $name => $m) {
    $res = $conn->query($m['check']);
    if ($res && $res->num_rows === 0) {
        if ($conn->query($m['sql'])) {
            echo "<div class='ok'>✅ ADDED: $name</div>";
        } else {
            echo "<div class='err'>❌ ERROR on $name: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='skip'>⏭ SKIP: $name (already exists)</div>";
    }
}

echo "<hr style='border-color:#333'><div style='color:#fff;'>✔ Migration complete. <a href='index.php' style='color:#0af'>Go to Login →</a></div>";
?>
