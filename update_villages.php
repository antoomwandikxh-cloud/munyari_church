<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// 1. Replace ucfirst() with strtoupper()
$content = str_replace(
    '<?= htmlspecialchars(ucfirst($row[\'first_name\']) . \' \' . ucfirst($row[\'last_name\'])) ?>',
    '<span style="text-transform:uppercase;"><?= htmlspecialchars($row[\'first_name\'] . \' \' . $row[\'last_name\']) ?></span>',
    $content
);

// 2. Change the Pastor badge to (PASTOR) in blue
$content = preg_replace(
    '/<\?php elseif \(\$is_pastor\):\s*\?>\s*<span class="badge" style="background:#fef08a;color:#92400e;">Pastor<\/span>/s',
    '<?php elseif ($is_pastor): ?>
                        <span style="color:#2563eb; font-weight:900;">(PASTOR)</span>',
    $content
);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
