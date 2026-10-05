<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);
$c = str_replace("\$village = \$_GET['village'] ?? '';", "\$village = trim(\$_GET['village'] ?? '');", $c);
$c = str_replace("church_village = '\$safe_village'", "TRIM(church_village) = '\$safe_village'", $c);
file_put_contents($f, $c);
echo "Trimmed village in print_village_members.php\n";
?>
