<?php
session_start();
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['username'])) {
    $username = $conn->real_escape_string(trim($_GET['username']));
    
    if (!preg_match('/^[A-Za-z0-9_.]+$/', $username)) {
        echo json_encode(['exists' => false, 'error' => 'Invalid format']);
        exit;
    }
    
    // Check all tables (case-sensitive BINARY)
    $member_exclude_sql = isset($_SESSION['member_id']) ? " AND id != " . (int)$_SESSION['member_id'] : "";
    $dup_member = $conn->query("SELECT id FROM members WHERE BINARY username = '$username'$member_exclude_sql")->num_rows;
    $dup_pastor = $conn->query("SELECT id FROM pastors WHERE BINARY username = '$username'")->num_rows;
    $dup_admin  = $conn->query("SELECT id FROM admins WHERE BINARY username = '$username'")->num_rows;
    
    if ($dup_member > 0 || $dup_pastor > 0 || $dup_admin > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'No username provided']);
}
?>
