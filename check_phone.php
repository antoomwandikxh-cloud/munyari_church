<?php
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['phone'])) {
    $phone = $conn->real_escape_string(trim($_GET['phone']));
    
    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(['exists' => false, 'error' => 'Invalid format']);
        exit;
    }
    
    // Check both tables
    $dup_member = $conn->query("SELECT id FROM members WHERE phone = '$phone'")->num_rows;
    $dup_pastor = $conn->query("SELECT id FROM pastors WHERE phone = '$phone'")->num_rows;
    
    if ($dup_member > 0 || $dup_pastor > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'No phone provided']);
}
?>
