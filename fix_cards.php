<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$old = <<<'EOT'
        <div class="content-card" style="margin-top:24px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Leadership Profiles</p>
                    <h2 style="margin:4px 0 0;color:var(--text-main);">Church Leaders Overview</h2>
                </div>
            </div>
            <?php foreach ($leader_sections as $section_key => $section): ?>
                <div style="margin-top:18px;">
                    <h3 style="margin:0 0 12px;color:var(--text-main);font-size:1.05rem;font-weight:850;border-left:4px solid <?= htmlspecialchars($section['accent']) ?>;padding-left:10px;"><?= htmlspecialchars($section['title']) ?></h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <?php
                        $shown = [];
                        $has_section_leaders = false;
                        foreach ($section['roles'] as $role_name):
                            foreach ($leaders as $leader):
                                foreach (dashboard_member_roles_detailed($leader['church_role'] ?? '') as $role):
                                    if ($role['norm'] !== $role_name || !dashboard_role_matches_leader_group($role, $leader['department'] ?? '', $section_key)) continue;
                                    $show_key = $leader['id'] . ':' . $section_key . ':' . $role_name;
                                    if (isset($shown[$show_key])) continue;
                                    $shown[$show_key] = true;
                                    $has_section_leaders = true;
                                    $role_label = $section['labels'][$role_name] ?? role_display_label(clean_role_display($role['norm']), $leader['department'] ?? '', $leader['gender'] ?? '');
                        ?>
                            <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                                <img src="uploads/<?= htmlspecialchars($leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($role_label) ?>" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid <?= htmlspecialchars($section['accent']) ?>;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                                <div style="min-width:0;">
                                    <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']) ?></p>
                                    <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($role_label) ?></p>
                                </div>
                            </div>
EOT;

$new = <<<'EOT'
        <div class="content-card" style="margin-top:24px;" id="leadersOverviewSection">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Leadership Profiles</p>
                    <h2 style="margin:4px 0 0;color:var(--text-main);">Church Leaders Overview</h2>
                </div>
            </div>
            <?php foreach ($leader_sections as $section_key => $section): ?>
                <div style="margin-top:18px;">
                    <h3 style="margin:0 0 12px;color:var(--text-main);font-size:1.05rem;font-weight:850;border-left:4px solid <?= htmlspecialchars($section['accent']) ?>;padding-left:10px;"><?= htmlspecialchars($section['title']) ?></h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <?php
                        $shown = [];
                        $has_section_leaders = false;
                        foreach ($section['roles'] as $role_name):
                            foreach ($leaders as $leader):
                                foreach (dashboard_member_roles_detailed($leader['church_role'] ?? '') as $role):
                                    if ($role['norm'] !== $role_name || !dashboard_role_matches_leader_group($role, $leader['department'] ?? '', $section_key)) continue;
                                    $show_key = $leader['id'] . ':' . $section_key . ':' . $role_name;
                                    if (isset($shown[$show_key])) continue;
                                    $shown[$show_key] = true;
                                    $has_section_leaders = true;
                                    $role_label = $section['labels'][$role_name] ?? role_display_label(clean_role_display($role['norm']), $leader['department'] ?? '', $leader['gender'] ?? '');
                        ?>
                            <div class="leader-print-card"
                                 data-section="<?= htmlspecialchars($section['title']) ?>"
                                 data-section-color="<?= htmlspecialchars($section['accent']) ?>"
                                 data-name="<?= htmlspecialchars(ucfirst($leader['first_name']) . ' ' . ucfirst($leader['last_name'])) ?>"
                                 data-role="<?= htmlspecialchars($role_label) ?>"
                                 data-pic="uploads/<?= htmlspecialchars($leader['profile_picture'] ?? 'default_avatar.png') ?>"
                                 style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                                <img src="uploads/<?= htmlspecialchars($leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($role_label) ?>" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid <?= htmlspecialchars($section['accent']) ?>;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                                <div style="min-width:0;">
                                    <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']) ?></p>
                                    <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($role_label) ?></p>
                                </div>
                            </div>
EOT;

foreach ($files as $f) {
    $content = file_get_contents($f);
    $content = str_replace("\r", "", $content);
    $old_normalized = str_replace("\r", "", $old);
    if (strpos($content, $old_normalized) !== false) {
        $content = str_replace($old_normalized, $new, $content);
        file_put_contents($f, $content);
        echo "Updated leader cards in $f\n";
    } else {
        echo "Could not find leader cards in $f\n";
    }
}
?>
