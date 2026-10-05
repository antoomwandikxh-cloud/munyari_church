<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// 1. Replace avatar placeholder with default avatar image
$old_lcard = '    <div class="lcard">
        <?php if ($l):
            $pb = img_b64($l[\'profile_picture\'] ?? \'\');
        ?>
        <?php if ($pb): ?>
            <img src="<?= $pb ?>" alt="">
        <?php else: ?>
            <div class="avatar-ph">dY` </div>
        <?php endif; ?>
        <div class="ln"><?= htmlspecialchars($l[\'first_name\'] . \' \' . $l[\'last_name\']) ?></div>
        <div class="lv" style="color:<?= $vc ?>;"><?= $v ?> - Village Leader</div>
        <?php else: ?>
        <div class="avatar-ph" style="color:#aaa;">dY` </div>
        <div class="ln" style="color:#aaa;">No Leader Yet</div>
        <div class="lv" style="color:<?= $vc ?>;"><?= $v ?> Village</div>
        <?php endif; ?>
    </div>';

// Wait, the emoji is weirdly encoded. I will use regex to replace it.
// Replace <div class="avatar-ph">...</div> with <img src="<?= img_b64('default_avatar.png') ?>" alt="">
$content = preg_replace('/<div class="avatar-ph".*?<\/div>/s', '<img src="<?= img_b64(\'default_avatar.png\') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">', $content);


// 2. Swap Phone and Department in Table Headers
$content = str_replace(
'                <th>Department</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Phone</th>',
'                <th>Phone</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Department</th>',
$content
);


// 3. Swap Phone and Department in Table Body
$old_tds = '                <td><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></td>
                <td><span class="badge" style="<?= $is_pastor ? \'background:rgba(245,158,11,0.15); color:#d97706;\' : \'background:rgba(37,99,235,0.1); color:#1e3a8a;\' ?>"><?= htmlspecialchars($row[\'church_role\'] ?: ($is_pastor ? \'Pastor\' : \'Member\')) ?></span></td>
                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_c ?>22; color:<?= $dsr_c ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:#94a3b8; font-size:0.85rem;">—</span><?php endif; ?></td>
                <td style="color:#555; font-size:0.85rem;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>';

$new_tds = '                <td style="color:#555; font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>
                <td><span class="badge" style="<?= $is_pastor ? \'background:rgba(245,158,11,0.15); color:#d97706;\' : \'background:rgba(37,99,235,0.1); color:#1e3a8a;\' ?>"><?= htmlspecialchars($row[\'church_role\'] ?: ($is_pastor ? \'Pastor\' : \'Member\')) ?></span></td>
                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_c ?>22; color:<?= $dsr_c ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:#94a3b8; font-size:0.85rem;">—</span><?php endif; ?></td>
                <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></span></td>';

$content = str_replace($old_tds, $new_tds, $content);

file_put_contents($file, $content);
echo "Fixed print_all_villages.php\n";
?>
