<?php
session_start();
require_once 'db_connect.php';

if (!isset($_GET['id']) || !isset($_GET['redirect'])) {
    header("Location: login.php");
    exit();
}

$notif_id = (int)$_GET['id'];
$redirect_tab = $_GET['redirect'];
$redirect_group = $_GET['group'] ?? '';
$user_id = null;
$user_type = null;

if (isset($_SESSION['member_id'])) {
    $user_id = (int)$_SESSION['member_id'];
    $user_type = 'member';
} elseif (isset($_SESSION['admin_id'])) {
    $user_id = (int)$_SESSION['admin_id'];
    $user_type = 'admin';
} elseif (isset($_SESSION['pastor_id'])) {
    $user_id = (int)$_SESSION['pastor_id'];
    $user_type = 'pastor';
} else {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND user_type = ?");
if ($stmt) {
    $stmt->bind_param("iis", $notif_id, $user_id, $user_type);
    $stmt->execute();
    $stmt->close();
}

if ($user_type === 'member') {
    header("Location: member_dashboard.php?tab=" . urlencode($redirect_tab));
} elseif ($user_type === 'admin' || $user_type === 'pastor') {
    $destination = ($user_type === 'admin' ? 'admin_dashboard.php' : 'pastor_dashboard.php') . '?tab=' . urlencode($redirect_tab);
    if ($redirect_tab === 'general_leadership' && $redirect_group === 'worship_leaders') {
        $destination .= '&leader_group=worship_leaders';
    }
    header("Location: $destination");
}
exit();
?>
