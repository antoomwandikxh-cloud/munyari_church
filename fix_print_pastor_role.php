<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

$old = "                <td><?= htmlspecialchars(\$row['church_role'] ?: 'Member') ?></td>";
$new = "                <td><span class=\"badge\" style=\"<?= \$is_pastor ? 'background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:12px; font-weight:700; font-size:0.75rem;' : 'background:#e0e7ff; color:#1e3a8a; padding:3px 8px; border-radius:12px; font-weight:700; font-size:0.75rem;' ?>\"><?= htmlspecialchars(\$is_pastor ? 'Pastor' : (\$row['church_role'] ?: 'Member')) ?></span></td>";

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed print_all_villages.php\n";
?>
