<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

// Revert status cell back to the simple badge
$content = preg_replace(
    '/<td>\s*<\?php if \(\$m\[\'is_approved\'\] == 1\): \?>\s*<span style="color:#10b981;font-weight:bold;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;"><svg width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="#10b981"\/><\/svg>Active<\/span>\s*<\?php elseif \(\$m\[\'is_approved\'\] == -1\): \?>\s*<span style="color:#ef4444;font-weight:bold;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;"><svg width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="#ef4444"\/><\/svg>Deactivated<\/span>\s*<\?php else: \?>\s*<span style="color:#f59e0b;font-weight:bold;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;"><svg width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="#f59e0b"\/><\/svg>Pending<\/span>\s*<\?php endif; \?>\s*<\/td>/s',
    '<td><span class="badge <?= $m[\'is_approved\'] ? \'approved\' : \'pending\' ?>"><?= $m[\'is_approved\'] ? \'Active\' : \'Pending\' ?></span></td>',
    $content
);

file_put_contents($file, $content);
echo "Reverted status cell in admin_dashboard.php\n";
?>
