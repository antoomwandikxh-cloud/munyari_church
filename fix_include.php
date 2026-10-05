<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);
if (strpos($c, 'role_departments.php') === false) {
    $c = str_replace("require_once 'db_connect.php';", "require_once 'db_connect.php';\nrequire_once 'role_departments.php';", $c);
    file_put_contents($f, $c);
    echo "Added role_departments.php to print_village_members.php\n";
}
?>
