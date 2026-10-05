<?php
$file = 'print_volunteers.php';
$c = file_get_contents($file);

$c = str_replace('<title>E.A.P.C Munyari - Church Service Volunteers</title>', '<title>E.A.P.C Munyari - Role Members List</title>', $c);
$c = str_replace('<h2>Church Service Volunteers</h2>', '<h2>Church Service Roles List</h2>', $c);

file_put_contents($file, $c);
echo "Cleaned up Volunteers string in $file\n";
?>
