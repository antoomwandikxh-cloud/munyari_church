<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);
$content = str_replace(
    "if (preg_match('/^(women chairlady|women chairperson|women chairman|women ministry chairlady|women ministry chairperson|womens ministry chairlady|womens ministry chairperson)$/", 
    "if (preg_match('/^(women.*(chairlady|chairperson|chairman)|.*ministry.*(chairlady|chairperson|chairman))$/",
    $content
);
file_put_contents($file, $content);
echo "Broadened women match in admin\n";
?>
