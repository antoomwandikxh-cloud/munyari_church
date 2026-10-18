<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_badges.php';

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

$notification_message = '';
$notification_created_at = '';
$notification_focus_message = '';
$notification_found = false;
$lookup_stmt = $conn->prepare("SELECT message, created_at FROM notifications WHERE id = ? AND user_id = ? AND user_type = ? LIMIT 1");
if ($lookup_stmt) {
    $lookup_stmt->bind_param("iis", $notif_id, $user_id, $user_type);
    $lookup_stmt->execute();
    $lookup_stmt->bind_result($notification_message, $notification_created_at);
    $notification_found = $lookup_stmt->fetch();
    $lookup_stmt->close();
}

unset($_SESSION['notification_tab_glow'], $_SESSION['notification_row_focus'], $_SESSION['fine_notification_highlight']);
if ($notification_found) {
    $redirect_tab = notification_target_tab($notification_message, $user_type, 'dashboard');
    $notification_focus_message = notification_focus_message_with_media_id($conn, $notification_message, $notification_created_at);
    $_SESSION['notification_tab_glow'] = $redirect_tab;
    $_SESSION['notification_row_focus'] = [
        'tab' => $redirect_tab,
        'message' => $notification_focus_message
    ];
}

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND user_type = ?");
if ($stmt) {
    $stmt->bind_param("iis", $notif_id, $user_id, $user_type);
    $stmt->execute();
    $stmt->close();
}

if ($user_type === 'member') {
    $destination = 'member_dashboard.php?tab=' . urlencode($redirect_tab);
    if ($redirect_tab === 'leader_chat') {
        $department_by_group = [
            'youths' => 'Youths',
            'womens_ministry' => 'Womens Ministry',
            'elders' => 'Elders',
            'sunday_school' => 'Sunday School'
        ];
        if (isset($department_by_group[$redirect_group])) {
            $_SESSION['dashboard_dept'] = $department_by_group[$redirect_group];
        }
    }
    header("Location: $destination");
} elseif ($user_type === 'admin' || $user_type === 'pastor') {
    $destination = ($user_type === 'admin' ? 'admin_dashboard.php' : 'pastor_dashboard.php') . '?tab=' . urlencode($redirect_tab);
    $valid_leader_groups = ['youths', 'womens_ministry', 'elders', 'sunday_school', 'general_church', 'building_construction', 'worship_leaders'];
    if ($redirect_tab === 'general_leadership' && in_array($redirect_group, $valid_leader_groups, true)) {
        $destination .= '&leader_group=' . urlencode($redirect_group);
    }
    header("Location: $destination");
}
exit();
?>
