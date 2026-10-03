<?php
$file = 'print_village_members.php';
$content = file_get_contents($file);

$content = str_replace(
    '$pastor_name = $pastor ? ucfirst($pastor[\'first_name\']) . \' \' . ucfirst($pastor[\'last_name\']) : \'Not Assigned\';',
    '$pastor_name = $pastor ? strtoupper($pastor[\'first_name\'] . \' \' . $pastor[\'last_name\']) : \'Not Assigned\';',
    $content
);

$content = str_replace(
    '<?= $leader ? htmlspecialchars(ucfirst($leader[\'first_name\']) . \' \' . ucfirst($leader[\'last_name\'])) : \'NOT ASSIGNED\' ?>',
    '<span style="text-transform:uppercase;"><?= $leader ? htmlspecialchars($leader[\'first_name\'] . \' \' . $leader[\'last_name\']) : \'NOT ASSIGNED\' ?></span>',
    $content
);

$content = preg_replace(
    '/<td style="font-weight:600;">\s*<\?= htmlspecialchars\(ucfirst\(\$m\[\'first_name\'\]\) \. \' \' \. ucfirst\(\$m\[\'last_name\'\]\)\) \?>\s*<\/td>/',
    '<td style="font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($m[\'first_name\'] . \' \' . $m[\'last_name\']) ?></td>',
    $content
);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
