<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

// 1. Total Students
$res = $conn->query("SELECT COUNT(*) as total FROM students");
$total_students = ($res && $row = $res->fetch_assoc()) ? $row['total'] : 0;

// 2. Total Revenue
$res = $conn->query("SELECT SUM(amount) as total FROM fees WHERE status='paid'");
$total_revenue = ($res && $row = $res->fetch_assoc()) ? ($row['total'] ?? 0) : 0;

// 3. Today's Attendance
$today = date('Y-m-d');
$res = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE attendance_date = '$today' AND status='present'");
$present_today = ($res && $row = $res->fetch_assoc()) ? $row['total'] : 0;

// 4. Active Courses
$res = $conn->query("SELECT COUNT(*) as total FROM courses");
$total_courses = ($res && $row = $res->fetch_assoc()) ? $row['total'] : 0;

echo json_encode([
    'students' => $total_students,
    'revenue' => isAdmin() ? number_format($total_revenue, 2) : "Restricted",
    'attendance' => $present_today,
    'courses' => $total_courses
]);
?>
