<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// Replace <div class="avatar-ph">...</div> with default avatar img
$content = preg_replace('/<div class="avatar-ph".*?<\/div>/s', '<img src="<?= img_b64(\'default_avatar.png\') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">', $content);

// Swap Headers
$old_h = '                <th>Department</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Phone</th>';
$new_h = '                <th>Phone</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Department</th>';
$content = str_replace($old_h, $new_h, $content);

// Swap Body TDs
$old_td = '                <td><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></td>
                <td><span class="badge" style="<?= $is_pastor ? \'background:rgba(245,158,11,0.15); color:#d97706;\' : \'background:rgba(37,99,235,0.1); color:#1e3a8a;\' ?>"><?= htmlspecialchars($row[\'church_role\'] ?: ($is_pastor ? \'Pastor\' : \'Member\')) ?></span></td>
                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_c ?>22; color:<?= $dsr_c ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:#94a3b8; font-size:0.85rem;">—</span><?php endif; ?></td>
                <td style="color:#555; font-size:0.85rem;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>';
$new_td = '                <td style="color:#555; font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>
                <td><span class="badge" style="<?= $is_pastor ? \'background:rgba(245,158,11,0.15); color:#d97706;\' : \'background:rgba(37,99,235,0.1); color:#1e3a8a;\' ?>"><?= htmlspecialchars($row[\'church_role\'] ?: ($is_pastor ? \'Pastor\' : \'Member\')) ?></span></td>
                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_c ?>22; color:<?= $dsr_c ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:#94a3b8; font-size:0.85rem;">—</span><?php endif; ?></td>
                <td><span class="badge" style="background:#f1f5f9; color:#475569; padding:4px 8px; border-radius:4px; font-weight:600;"><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></span></td>';
$content = str_replace($old_td, $new_td, $content);

file_put_contents($file, $content);
echo "Done\n";
?>
