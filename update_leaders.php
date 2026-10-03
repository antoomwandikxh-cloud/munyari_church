<?php
$file = 'print_all_leaders.php';
$content = file_get_contents($file);

$content = str_replace(
    '\'name\'      => ucfirst($leader[\'first_name\']).\' \'.ucfirst($leader[\'last_name\']),',
    '\'name\'      => strtoupper($leader[\'first_name\'] . \' \' . $leader[\'last_name\']),',
    $content
);

$content = str_replace(
    '$pastor_name    = $pastor ? ucfirst($pastor[\'first_name\']).\' \'.ucfirst($pastor[\'last_name\']) : \'N/A\';',
    '$pastor_name    = $pastor ? strtoupper($pastor[\'first_name\'] . \' \' . $pastor[\'last_name\']) : \'N/A\';',
    $content
);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
