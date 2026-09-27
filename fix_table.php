<?php
$file = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$content = file_get_contents($file);

// Find the line that has "<!-- Members List -->"
$lines = explode("\n", $content);
$target_line_idx = -1;
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '<!-- Members List -->') !== false && strpos($lines[$i], 'Village Members') !== false) {
        $target_line_idx = $i;
        break;
    }
}

if ($target_line_idx >= 0) {
    echo "Found on line " . ($target_line_idx + 1) . "\n";
    $fixed_block = <<<'BLOCK'
            <!-- Members List -->
            <div class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 style="margin:0; color:var(--text-main); font-size:1.1rem;">Village Members (<?= htmlspecialchars($member['church_village']) ?>)</h2>
                    <a href="print_village_members.php?village=<?= urlencode($member['church_village']) ?>" target="_blank" class="btn-primary" style="background:#10b981; padding:8px 16px; font-size:0.85rem; text-decoration:none; display:inline-block;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Official List
                    </a>
                </div>
                <div class="table-responsive">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:var(--bg-lighter); color:var(--text-main);">
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">#</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Photo</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Name</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Phone</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Department</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Role(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $v = $conn->real_escape_string($member['church_village']);
                            $vm_q = $conn->query("
                                SELECT id, first_name, last_name, phone, department, church_role, profile_picture, is_village_leader,
                                CASE
                                    WHEN is_village_leader = 1 THEN 1
                                    WHEN church_role IS NOT NULL AND church_role != '' AND LOWER(church_role) != 'member' THEN 2
                                    ELSE 99
                                END AS sort_rank
                                FROM members
                                WHERE is_approved = 1 AND church_village = '$v'
                                ORDER BY sort_rank ASC, first_name ASC
                            ");
                            if ($vm_q && $vm_q->num_rows > 0) {
                                $vi = 1;
                                while ($vm = $vm_q->fetch_assoc()) {
                                    $bg_style = '';
                                    if ($vm['is_village_leader'] == 1) {
                                        $bg_style = 'background:rgba(59, 130, 246, 0.15);';
                                    } elseif ($vi % 2 === 0) {
                                        $bg_style = 'background:var(--bg-lighter);';
                                    }
                                    
                                    $row_fw = ($vm['is_village_leader'] == 1) ? '700' : '400';
                                    $pic_src = 'uploads/' . basename($vm['profile_picture'] ?? 'default_avatar.png');
                                    $badge = '';
                                    if ($vm['is_village_leader'] == 1) {
                                        $badge = '<span style="background:#1e3a8a;color:#fff;font-size:9px;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;margin-left:5px;">Leader</span>';
                                    } elseif (!empty($vm['church_role']) && strtolower(trim($vm['church_role'])) !== 'member') {
                                        $badge = '<span style="background:#10b981;color:#fff;font-size:9px;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;margin-left:5px;">Role</span>';
                                    }
                                    echo "<tr style='$bg_style transition:background 0.2s;'>";
                                    echo "<td style='padding:8px; border-bottom:1px solid var(--border-color); font-size:12px;'>$vi</td>";
                                    echo "<td style='padding:6px 8px; border-bottom:1px solid var(--border-color);'><img src='" . htmlspecialchars($pic_src) . "' onerror=\"this.src='uploads/default_avatar.png'\" style='width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);display:block;'></td>";
                                    echo "<td style='padding:8px; border-bottom:1px solid var(--border-color); font-weight:$row_fw; color:var(--text-main);'>" . htmlspecialchars(ucfirst($vm['first_name']) . ' ' . ucfirst($vm['last_name'])) . $badge . "</td>";
                                    echo "<td style='padding:8px; border-bottom:1px solid var(--border-color); font-size:12px; color:var(--text-main);'>" . htmlspecialchars($vm['phone'] ?? '') . "</td>";
                                    echo "<td style='padding:8px; border-bottom:1px solid var(--border-color); font-size:12px; color:var(--text-main);'>" . htmlspecialchars($vm['department'] ?? '') . "</td>";
                                    echo "<td style='padding:8px; border-bottom:1px solid var(--border-color); font-size:12px; color:var(--text-main);'>" . htmlspecialchars($vm['church_role'] ?? '') . "</td>";
                                    echo "</tr>";
                                    $vi++;
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center;padding:20px;color:var(--text-muted);'>No members found in this village.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
BLOCK;
    $lines[$target_line_idx] = str_replace("\r", "", $fixed_block); // Ensure clean line endings
    file_put_contents($file, implode("\n", $lines));
    echo "Replaced the malformed single-line table block with properly styled multi-line block.\n";
} else {
    echo "Could not find target line.\n";
}
?>
