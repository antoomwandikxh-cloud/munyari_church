<?php
$file = 'print_volunteers.php';
$content = file_get_contents($file);

$content = preg_replace(
    '/<td style="font-weight:600;">\s*<\?= htmlspecialchars\(ucfirst\(\$p\[\'first_name\'\]\) \. \' \' \. ucfirst\(\$p\[\'last_name\'\]\)\) \?>\s*<\?php if \(\$is_past\):\s*\?><span class="badge" style="background:#fef08a;color:#92400e;">Pastor<\/span><\?php endif; \?>\s*<\/td>/s',
    '<td style="font-weight:<?= $is_past ? \'900\' : \'600\' ?>; text-transform:uppercase;">
                    <?= htmlspecialchars($p[\'first_name\'] . \' \' . $p[\'last_name\']) ?>
                    <?php if ($is_past): ?><span style="color:#2563eb; font-weight:900;">(PASTOR)</span><?php endif; ?>
                </td>',
    $content
);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
