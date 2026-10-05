<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

// The exact img tag in the worshippers table
$old = '<td><img src="uploads/<?= htmlspecialchars($ws[\'profile_picture\'] ?? \'default_avatar.png\') ?>" alt="Profile" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);"></td>';

$new = '<td><img src="uploads/<?= htmlspecialchars($ws[\'profile_picture\'] ?? \'default_avatar.png\') ?>" alt="Profile" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);cursor:zoom-in;" onclick="viewProfileImage(this.src);" title="Click to view"></td>';

foreach ($files as $file) {
    $c = file_get_contents($file);
    $count = 0;
    $c = str_replace($old, $new, $c, $count);
    file_put_contents($file, $c);
    echo "Updated $file (replaced: $count)\n";
}
?>
