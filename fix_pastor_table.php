<?php
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);

// 1. Fix header
$content = preg_replace(
    '/<tr><th>Profile<\/th><th>Name<\/th><th>Username<\/th><th>Phone<\/th><th>Address<\/th><th>Department<\/th><th>Role<\/th><th>Status<\/th><th>Actions<\/th><\/tr>/',
    '<tr><th>Profile</th><th>Name</th><th>Username</th><th>Phone</th><th>Address</th><th>Church Village</th><th>Role</th><th>Status</th><th>Actions</th></tr>',
    $content
);

// 2. Fix data cell - show church_village instead of department
$content = str_replace(
    '<td><span class="badge" style="background:var(--border-color);color:var(--text-main);"><?= htmlspecialchars($m[\'department\'] ?? \'General Church\') ?></span></td>
                                <td style="white-space:normal;">',
    '<td><span class="badge" style="background:rgba(37,99,235,0.1);color:#2563eb;font-weight:600;"><?= htmlspecialchars($m[\'church_village\'] ?? \'-\') ?></span></td>
                                <td style="white-space:normal;">',
    $content
);

file_put_contents($file, $content);
echo "Updated pastor_dashboard.php\n";
echo "Header match: " . preg_match('/<th>Church Village<\/th>/', $content) . "\n";
echo "Village cell match: " . preg_match('/church_village/', $content) . "\n";
?>
