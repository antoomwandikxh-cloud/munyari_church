<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

// The old cell in the Manage Members table (identical in both files)
$old = '<td style="white-space:normal;"><?php foreach(array_map(\'trim\', explode(\',\', $m[\'church_role\'] ?? \'Member\')) as $role_part) { if(trim($role_part)===\'\') continue; echo \'<span class="badge" style="background:var(--border-color);color:var(--text-main);margin:2px 2px 2px 0;display:inline-block;white-space:nowrap;">\'.htmlspecialchars($role_part).\'</span>\'; } ?></td>';

// New cell — uses the already-injected render_role_pills() helper
$new = '<td style="white-space:normal;"><?= render_role_pills($m[\'church_role\'] ?? \'\') ?></td>';

foreach ($files as $file) {
    $c = file_get_contents($file);
    $count = 0;
    $c = str_replace($old, $new, $c, $count);
    file_put_contents($file, $c);
    echo "Done: $file (replaced: $count)\n";
}
?>
