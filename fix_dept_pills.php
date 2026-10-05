<?php
// The old plain-text role td (identical in both files)
$old = '                                                <td><?= htmlspecialchars($final_role_str) ?></td>';

// New pill-based td — reuse render_role_pills() with $final_role_str
$new = '                                                <td><?= render_role_pills($final_role_str) ?></td>';

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    $count = 0;
    $c = str_replace($old, $new, $c, $count);
    file_put_contents($file, $c);
    echo "Done: $file (replaced: $count)\n";
}
?>
