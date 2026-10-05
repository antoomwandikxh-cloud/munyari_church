<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// Replace avatar placeholder emoji
$content = preg_replace('/<div class="ph">.*?<\/div>/s', '<img src="<?= img_b64(\'default_avatar.png\') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">', $content);


// Replace Headers
$old_h = '                <th>Department</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Phone</th>';
$new_h = '                <th>Phone</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Department</th>';
$content = str_replace($old_h, $new_h, $content);

// Replace TDs
$old_td = '                <td><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></td>
                <td><?= htmlspecialchars($row[\'church_role\'] ?: \'Member\') ?></td>
                <td>
                    <?php if ($dsr): ?>
                        <span class="badge" style="background:<?= $dsr_c ?>22;color:<?= $dsr_c ?>;"><?= htmlspecialchars($dsr) ?></span>
                    <?php else: ?>
                        <span style="color:#bbb;">—</span>
                    <?php endif; ?>
                </td>
                <td style="color:#666;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>';

$new_td = '                <td style="color:#666;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>
                <td><?= htmlspecialchars($row[\'church_role\'] ?: \'Member\') ?></td>
                <td>
                    <?php if ($dsr): ?>
                        <span class="badge" style="background:<?= $dsr_c ?>22;color:<?= $dsr_c ?>;"><?= htmlspecialchars($dsr) ?></span>
                    <?php else: ?>
                        <span style="color:#bbb;">—</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></td>';

$content = str_replace($old_td, $new_td, $content);

file_put_contents($file, $content);
echo "Done\n";
?>
