<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    
    // Revert header back: Church Village -> Department, Address label restore
    $content = preg_replace(
        '/<tr><th>Profile<\/th><th>Name<\/th><th>Username<\/th><th>Phone<\/th><th>Address<\/th><th>Church Village<\/th><th>Role<\/th><th>Status<\/th><th>Actions<\/th><\/tr>/',
        '<tr><th>Profile</th><th>Name</th><th>Username</th><th>Phone</th><th>Area</th><th>Department</th><th>Role</th><th>Status</th><th>Actions</th></tr>',
        $content
    );
    
    // Revert data cell: church_village badge -> department badge
    $content = str_replace(
        '<td><span class="badge" style="background:rgba(37,99,235,0.1);color:#2563eb;font-weight:600;"><?= htmlspecialchars($m[\'church_village\'] ?? \'-\') ?></span></td>',
        '<td><span class="badge" style="background:var(--border-color);color:var(--text-main);"><?= htmlspecialchars($m[\'department\'] ?? \'General Church\') ?></span></td>',
        $content
    );
    
    file_put_contents($file, $content);
    echo "Reverted on-screen table in $file\n";
}
?>
