<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_badges.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

if (isset($_GET['id']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    
    if ($type === 'member') {
        $member_result = $conn->query("SELECT is_approved FROM members WHERE id = $id LIMIT 1");
        $member_before_approval = $member_result ? $member_result->fetch_assoc() : null;
        $was_pending = $member_before_approval && (int)$member_before_approval['is_approved'] === 0;
        $conn->query("UPDATE members SET is_approved = 1 WHERE id = $id");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'member', 'Your account has been approved by Admin. Welcome to Munyari Church!')");
        if ($was_pending) notify_staff_of_department_member_approval($conn, $id);
    } elseif ($type === 'pastor') {
        $conn->query("UPDATE pastors SET is_approved = 1 WHERE id = $id");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'pastor', 'Your pastor account has been approved by Admin.')");
    }
}
header("Location: admin_dashboard.php?tab=members&approved=1");
exit();
?>
