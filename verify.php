<?php
$f = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$c = file_get_contents($f);
echo "1. Photo column present: " . (strpos($c, 'sort_rank ASC, first_name ASC') !== false ? "YES" : "NO") . "\n";
echo "2. Profile pic img tag present: " . (strpos($c, "width:38px;height:38px;border-radius:50%") !== false ? "YES" : "NO") . "\n";
echo "3. Church Village Panel label: " . (strpos($c, 'Church Village Panel') !== false ? "YES" : "NO") . "\n";
echo "4. Old link near desired_roles removed: ";
// Check there's only 0 instances of that section before Sunay School
$pre = substr($c, 0, strpos($c, 'Sunday School'));
echo (strpos($pre, 'manage_church_village') === false ? "YES (removed)" : "STILL THERE") . "\n";
echo "5. Old black @media print style: " . (strpos($c, '.print-table { position: absolute; left: 0; top: 0;') !== false ? "STILL PRESENT" : "REMOVED") . "\n";

$fp = "C:\\xampp\\htdocs\\munyari_church\\print_village_members.php";
$cp = file_get_contents($fp);
echo "6. Pastor photo removed from print: " . (strpos($cp, 'pastor_pic_b64') === false ? "YES (removed)" : "STILL PRESENT") . "\n";
?>
