<?php
$f = 'C:\xampp\htdocs\munyari_church\admin_dashboard.php';
$c = file_get_contents($f);
if(strpos($c, "leaderHTML = '<div") !== false) {
    echo "admin_dashboard JS is correct\n";
} else {
    echo "admin_dashboard JS is missing\n";
}
if(strpos($c, "class=\"print-leader-profile\"") !== false) {
    echo "admin_dashboard HTML is correct\n";
} else {
    echo "admin_dashboard HTML is missing\n";
}
?>
