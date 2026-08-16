<?php
// Prevent output-before-headers issues on strict hosts
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();
require_once __DIR__ . '/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $account_type     = trim($_POST['account_type'] ?? 'member');
    $first_name_raw   = trim($_POST['first_name'] ?? '');
    $phone_raw        = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Sanitize
    $first_name = $conn->real_escape_string($first_name_raw);
    $phone      = $conn->real_escape_string($phone_raw);

    // Basic required field check
    if (empty($first_name_raw) || empty($password)) {
        header("Location: register_page.php?error=" . urlencode("First Name and Password are required"));
        exit();
    }

    // Name validation
    if (!preg_match('/^[A-Za-z\s]+$/', $first_name_raw)) {
        header("Location: register_page.php?error=" . urlencode("First name can only contain letters"));
        exit();
    }

    $last_name_raw = trim($_POST['last_name'] ?? '');
    if (!empty($last_name_raw) && !preg_match('/^[A-Za-z\s]+$/', $last_name_raw)) {
        header("Location: register_page.php?error=" . urlencode("Last name can only contain letters"));
        exit();
    }
    $last_name = $conn->real_escape_string($last_name_raw);

    // Password length
    if (strlen($password) < 6) {
        header("Location: register_page.php?error=" . urlencode("Password must be at least 6 characters"));
        exit();
    }

    // Phone format
    if (!preg_match('/^\d{10}$/', $phone_raw)) {
        header("Location: register_page.php?error=" . urlencode("Phone number must be exactly 10 digits"));
        exit();
    }

    // Password match
    if ($password !== $confirm_password) {
        header("Location: register_page.php?error=" . urlencode("Passwords do not match"));
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ── PASTOR REGISTRATION ────────────────────────────────────────────────────
    if ($account_type === 'pastor') {
        $role       = $conn->real_escape_string(trim($_POST['role'] ?? 'Pastor'));
        if (empty($role)) $role = 'Pastor';
        $gender     = $conn->real_escape_string(trim($_POST['gender'] ?? 'Male'));
        $department = ($gender === 'Female') ? 'Womens Ministry' : 'Elders';

        // Duplicate phone check
        $chk = $conn->query("SELECT id FROM pastors WHERE phone = '$phone' UNION SELECT id FROM members WHERE phone = '$phone'");
        if ($chk && $chk->num_rows > 0) {
            header("Location: register_page.php?error=" . urlencode("The number is already registered"));
            exit();
        }

        $sql = "INSERT INTO pastors (first_name, last_name, phone, role, password, gender, department, is_approved)
                VALUES ('$first_name', '$last_name', '$phone', '$role', '$hashed_password', '$gender', '$department', 0)";

    // ── MEMBER REGISTRATION ────────────────────────────────────────────────────
    } else {
        $address    = $conn->real_escape_string(trim($_POST['address'] ?? ''));
        $department = $conn->real_escape_string(trim($_POST['department'] ?? 'Youths'));
        $gender     = $conn->real_escape_string(trim($_POST['gender'] ?? 'Male'));

        // Duplicate phone check
        $chk = $conn->query("SELECT id FROM members WHERE phone = '$phone' UNION SELECT id FROM pastors WHERE phone = '$phone'");
        if ($chk && $chk->num_rows > 0) {
            header("Location: register_page.php?error=" . urlencode("The number is already registered"));
            exit();
        }

        $sql = "INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved)
                VALUES ('$first_name', '$last_name', '$phone', '$address', '$department', '$gender', '$hashed_password', 0)";
    }

    // ── Execute insert ─────────────────────────────────────────────────────────
    if ($conn->query($sql) === TRUE) {
        $new_id = $conn->insert_id;

        if ($account_type === 'member') {
            $member_name = trim($first_name_raw . ' ' . $last_name_raw);
            $notif_msg   = $conn->real_escape_string("New member registration: $member_name in $department department is pending approval.");

            $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors) {
                while ($p = $pastors->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
                }
            }
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) {
                while ($a = $admins->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
                }
            }
            header("Location: login.php?success=member");
            exit();

        } elseif ($account_type === 'pastor') {
            $pastor_name = trim($first_name_raw . ' ' . $last_name_raw);
            $notif_msg   = $conn->real_escape_string("New pastor registration: $pastor_name is pending admin approval.");

            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) {
                while ($a = $admins->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
                }
            }
            header("Location: login.php?success=pastor");
            exit();
        }

    } else {
        // Log server-side, redirect with safe message
        error_log("Register insert failed: " . $conn->error);
        header("Location: register_page.php?error=" . urlencode("Registration failed. Please try again."));
        exit();
    }
}
?>