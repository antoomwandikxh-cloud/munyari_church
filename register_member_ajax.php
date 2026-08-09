<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Only pastors and admins can use this
if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$fn = $conn->real_escape_string(trim($_POST['first_name'] ?? ''));
$ln = $conn->real_escape_string(trim($_POST['last_name'] ?? ''));
$ph = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
$ad = $conn->real_escape_string(trim($_POST['address'] ?? ''));
$dp = $conn->real_escape_string(trim($_POST['department'] ?? 'None'));
$gn = $conn->real_escape_string(trim($_POST['gender'] ?? 'Male'));
$pw_raw = trim($_POST['password'] ?? '');

if (!$fn || !$ln || !$ph || !$pw_raw) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit();
}

// Check duplicate phone
$dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
if ($dup > 0) {
    echo json_encode(['success' => false, 'message' => 'A member with that phone number already exists.']);
    exit();
}

$pw = password_hash($pw_raw, PASSWORD_DEFAULT);
$conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','$dp','$gn','$pw',1,NOW())");
$new_id = $conn->insert_id;
$creator = isset($_SESSION['pastor_id']) ? 'pastor' : 'admin';
$msg = $conn->real_escape_string("Your account has been created and approved by the $creator.");
$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($new_id, 'member', '$msg')");

echo json_encode([
    'success' => true,
    'message' => "Member registered successfully!",
    'member' => [
        'id'         => $new_id,
        'first_name' => htmlspecialchars($fn),
        'last_name'  => htmlspecialchars($ln),
        'phone'      => htmlspecialchars($ph),
        'address'    => htmlspecialchars($ad),
        'department' => htmlspecialchars($dp),
    ]
]);
