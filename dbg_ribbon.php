<?php
require 'db_connect.php';

// Simulate the sort function
function get_print_sort_order($role, $dept) {
    $r = strtolower(trim($role));
    $d = strtolower(trim($dept));
    if (empty($r) || $r === 'member') return [99, $dept, ''];
    if (strpos($r,'pastor') !== false) return [0, $dept, $r];
    if ($d === 'elders') return [1, $dept, $r];
    if ($d === 'womens ministry') return [2, $dept, $r];
    if ($d === 'youths') return [3, $dept, $r];
    if ($d === 'sunday school') return [4, $dept, $r];
    return [8, $dept, $r];
}

$res = $conn->query("SELECT church_role, department FROM members WHERE is_approved=1 ORDER BY first_name LIMIT 20");
while ($m = $res->fetch_assoc()) {
    $s = get_print_sort_order($m['church_role'] ?? '', $m['department'] ?? '');
    echo "GRP:{$s[0]} | Dept:{$m['department']} | Role:{$m['church_role']}\n";
}
?>
