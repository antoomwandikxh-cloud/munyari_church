<?php
require 'db_connect.php';
$village = 'Akoritho';
$safe_village = $conn->real_escape_string(trim($village));
$leader_q = $conn->query("SELECT * FROM members WHERE TRIM(church_village) = '$safe_village' AND is_village_leader = 1 AND is_approved = 1 LIMIT 1");
$leader = $leader_q ? $leader_q->fetch_assoc() : null;

$l_pic_b64 = '';
if ($leader && !empty($leader['profile_picture'])) {
    $lp = 'uploads/' . basename($leader['profile_picture']);
    if (file_exists($lp)) {
        $ext = strtolower(pathinfo($lp, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
        $l_pic_b64 = "data:$mime;base64," . base64_encode(file_get_contents($lp));
    }
}

echo "l_pic_b64 length: " . strlen($l_pic_b64) . "\n";
echo "First 80 chars: " . substr($l_pic_b64, 0, 80) . "\n";
?>
