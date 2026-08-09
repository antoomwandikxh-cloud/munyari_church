<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_type = $_POST['account_type'];
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($first_name) || empty($password)) {
        header("Location: index.php?error=First Name and Password are required");
        exit();
    }
    
    if ($password !== $confirm_password) {
        header("Location: index.php?error=Passwords do not match");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    if ($account_type === 'pastor') {
        $role = $conn->real_escape_string(trim($_POST['role']));
        if (empty($role)) $role = 'Pastor';
        $last_name = isset($_POST['last_name']) ? $conn->real_escape_string(trim($_POST['last_name'])) : '';
        
        $sql = "INSERT INTO pastors (first_name, last_name, phone, role, password, is_approved) 
                VALUES ('$first_name', '$last_name', '$phone', '$role', '$hashed_password', 0)";
    } else {
        $address = $conn->real_escape_string(trim($_POST['address']));
        $last_name = isset($_POST['last_name']) ? $conn->real_escape_string(trim($_POST['last_name'])) : '';
        $department = isset($_POST['department']) ? $conn->real_escape_string(trim($_POST['department'])) : 'Youths';
        $gender = isset($_POST['gender']) ? $conn->real_escape_string(trim($_POST['gender'])) : 'Male';
        
        $sql = "INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved) 
                VALUES ('$first_name', '$last_name', '$phone', '$address', '$department', '$gender', '$hashed_password', 0)";
    }
            
    if ($conn->query($sql) === TRUE) {
        if ($account_type === 'member') {
            $member_name = trim($first_name . ' ' . ($last_name ?? ''));
            $notif_msg = $conn->real_escape_string("New member registration: $member_name in $department department is pending approval.");
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
        } elseif ($account_type === 'pastor') {
            $pastor_name = trim($first_name . ' ' . ($last_name ?? ''));
            $notif_msg = $conn->real_escape_string("New pastor registration: $pastor_name is pending admin approval.");
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) {
                while ($a = $admins->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
                }
            }
        }
        header("Location: index.php?success=1");
        exit();
    } else {
        header("Location: index.php?error=Database error: " . urlencode($conn->error));
        exit();
    }
}
?>
