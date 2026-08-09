<?php
session_start();
require_once 'db_connect.php';

// Only admins can perform these actions
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['type']) && isset($_GET['action'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    $action = $_GET['action'];
    
    // Determine the table
    $table = ($type === 'pastor') ? 'pastors' : 'members';
    
    if ($action === 'approve') {
        $conn->query("UPDATE $table SET is_approved = 1 WHERE id = $id");
        if ($type === 'member') {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'member', 'Your account has been approved by Admin. Welcome to Munyari Church!')");
        } elseif ($type === 'pastor') {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'pastor', 'Your pastor account has been approved by Admin.')");
        }
        $msg = "approved=1";
    } elseif ($action === 'deactivate') {
        $conn->query("UPDATE $table SET is_approved = 0 WHERE id = $id");
        $msg = "deactivated=1";
    } elseif ($action === 'remove') {
        // First delete any messages associated to avoid foreign key/orphan issues
        if ($table === 'members') {
            $conn->query("DELETE FROM member_messages WHERE member_id = $id");
        } elseif ($table === 'pastors') {
            // If pastor is removed, maybe delete their messages or keep them but set pastor_name to '[Removed]' (We use pastor_name directly in messages so it's fine)
        }
        $conn->query("DELETE FROM $table WHERE id = $id");
        $msg = "removed=1";
    }
}

// Redirect back to the correct tab
$tab = ($type === 'pastor') ? 'pastors' : 'members';
header("Location: admin_dashboard.php?tab=$tab&$msg");
exit();
?>
