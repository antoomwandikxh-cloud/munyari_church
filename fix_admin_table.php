<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

// 1. Fix header - Department -> Church Village, Area -> Address
$content = str_replace(
    '<thead><tr><th>Profile</th><th>Name</th><th>Username</th><th>Phone</th><th>Area</th><th>Department</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>',
    '<thead><tr><th>Profile</th><th>Name</th><th>Username</th><th>Phone</th><th>Address</th><th>Church Village</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>',
    $content
);

// 2. Fix data cell - show church_village instead of department
$content = str_replace(
    '<td><span class="badge" style="background:var(--border-color);color:var(--text-main);"><?= htmlspecialchars($m[\'department\'] ?? \'General Church\') ?></span></td>',
    '<td><span class="badge" style="background:rgba(37,99,235,0.1);color:#2563eb;font-weight:600;"><?= htmlspecialchars($m[\'church_village\'] ?? \'-\') ?></span></td>',
    $content
);

// 3. Fix status cell - replace simple approved/pending badge with dot + text like pastor
$content = str_replace(
    '<td><span class="badge <?= $m[\'is_approved\'] ? \'approved\' : \'pending\' ?>"><?= $m[\'is_approved\'] ? \'Active\' : \'Pending\' ?></span></td>',
    '<td>
                                <?php if ($m[\'is_approved\'] == 1): ?>
                                    <span style="color:#10b981;font-weight:bold;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;"><svg width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="#10b981"/></svg>Active</span>
                                <?php elseif ($m[\'is_approved\'] == -1): ?>
                                    <span style="color:#ef4444;font-weight:bold;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;"><svg width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="#ef4444"/></svg>Deactivated</span>
                                <?php else: ?>
                                    <span style="color:#f59e0b;font-weight:bold;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;"><svg width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="#f59e0b"/></svg>Pending</span>
                                <?php endif; ?>
                            </td>',
    $content
);

file_put_contents($file, $content);
echo "Updated admin_dashboard.php\n";
?>
