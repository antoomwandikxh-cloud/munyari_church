<?php
$f = "print_village_members.php";
$c = file_get_contents($f);
$c = str_replace("width: 26px; height: 26px;", "width: 30px; height: 30px;", $c);
$c = str_replace("width:26px;height:26px;", "width: 30px; height: 30px;", $c);
file_put_contents($f, $c);
echo "Fixed: $f\n";
?>
