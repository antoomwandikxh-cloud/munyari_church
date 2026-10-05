<?php
require 'db_connect.php';

function get_print_sort_order($role, $dept) {
    $r = strtolower(trim($role ?? ''));
    $d = strtolower(trim($dept ?? ''));
    
    // Pastors
    if (strpos($r,'pastor') !== false) return [0, $dept, $r];
    
    // No role = plain member
    if (empty($r) || $r === 'member') return [99, $dept, $r];
    
    // Dept-specific role
    if ($d === 'elders') return [1, $dept, $r];
    if ($d === 'womens ministry') return [2, $dept, $r];
    if ($d === 'youths') return [3, $dept, $r];
    if ($d === 'sunday school') {
        if (strpos($r,'teacher') !== false) return [5, $dept, $r];
        if (strpos($r,'patron') !== false || strpos($r,'prefect') !== false) return [6, $dept, $r];
        return [4, $dept, $r];
    }
    if ($d === 'building') return [7, $dept, $r];
    
    return [8, $dept, $r];
}

// Get all members
$res = $conn->query("SELECT first_name, last_name, church_role, department, is_approved FROM members WHERE is_approved=1");
$all = [];
while ($m = $res->fetch_assoc()) $all[] = $m;

// Get pastors
$pres = $conn->query("SELECT first_name, last_name, role AS church_role, department, is_approved, 1 AS is_pastor FROM pastors WHERE is_approved=1");
if ($pres) while ($p = $pres->fetch_assoc()) $all[] = $p;

// Sort same way dashboard does
usort($all, function($a, $b) {
    $sa = get_print_sort_order($a['church_role'] ?? '', $a['department'] ?? '');
    $sb = get_print_sort_order($b['church_role'] ?? '', $b['department'] ?? '');
    if ($sa[0] !== $sb[0]) return $sa[0] <=> $sb[0];
    return strcmp($sa[2], $sb[2]);
});

$last = -1;
foreach ($all as $m) {
    $s = get_print_sort_order($m['church_role'] ?? '', $m['department'] ?? '');
    $grp = $s[0];
    if ($grp !== $last) {
        echo "=== RIBBON: GRP $grp ===\n";
        $last = $grp;
    }
    echo "  {$m['first_name']} {$m['last_name']} | {$m['department']} | {$m['church_role']}\n";
}
?>
