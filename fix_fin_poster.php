<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // 1. Update the SQL to also fetch poster_pic
    $old_sql = "SELECT fr.*, COALESCE(fr.recorded_by_name, CONCAT(m.first_name, ' ', m.last_name)) AS poster_name FROM financial_records fr LEFT JOIN members m ON fr.recorded_by = m.id WHERE fr.department = '\$d_esc' ORDER BY fr.recorded_at DESC LIMIT 30";
    $new_sql = "SELECT fr.*, COALESCE(fr.recorded_by_name, CONCAT(m.first_name, ' ', m.last_name)) AS poster_name, COALESCE(m.profile_picture, 'default_avatar.png') AS poster_pic FROM financial_records fr LEFT JOIN members m ON fr.recorded_by = m.id WHERE fr.department = '\$d_esc' ORDER BY fr.recorded_at DESC LIMIT 30";
    $content = str_replace($old_sql, $new_sql, $content);
    
    // 2. Update the Posted By cell to include photo
    $old_cell = '                                <td><span style="font-weight:600; color:var(--text-main);"><?= htmlspecialchars($fr[\'poster_name\'] ?: \'Unknown\') ?></span></td>';
    $new_cell = '                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <img src="uploads/<?= htmlspecialchars($fr[\'poster_pic\'] ?? \'default_avatar.png\') ?>"
                                             style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);cursor:zoom-in;flex-shrink:0;"
                                             onclick="viewProfileImage(this.src)">
                                        <span style="font-weight:600;color:var(--text-main);"><?= htmlspecialchars($fr[\'poster_name\'] ?: \'Unknown\') ?></span>
                                    </div>
                                </td>';
    $content = str_replace($old_cell, $new_cell, $content);
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
