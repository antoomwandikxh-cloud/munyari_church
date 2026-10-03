<?php
$file = 'print_pastor_reports.php';
$content = file_get_contents($file);

$content = str_replace(
    '<?= htmlspecialchars(ucfirst($m[\'first_name\']) . \' \' . ucfirst($m[\'last_name\'])) ?><?= $is_leader ? \' (LEADER)\' : \'\' ?>',
    '<span style="text-transform:uppercase;"><?= htmlspecialchars($m[\'first_name\'] . \' \' . $m[\'last_name\']) ?></span><?= $is_leader ? \' <span style="color:#2563eb;font-weight:bold;">(LEADER)</span>\' : \'\' ?>',
    $content
);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
