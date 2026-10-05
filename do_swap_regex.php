<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// Replace avatar placeholder emoji
$content = preg_replace('/<div class="ph">.*?<\/div>/s', '<img src="<?= img_b64(\'default_avatar.png\') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">', $content);


// Replace Headers
$content = preg_replace(
    '/(<th>Full Name<\/th>\s*)<th>Department<\/th>(\s*<th>Church Role<\/th>\s*<th>Chosen Service<\/th>\s*)<th>Phone<\/th>/s',
    '$1<th>Phone</th>$2<th>Department</th>',
    $content
);

// Replace TDs
$content = preg_replace(
    '/(<\/span>\s*<\?php endif; \?>\s*<\/td>\s*)<td><\?= htmlspecialchars\(\$row\[\'department\'\] \?: \'General Church\'\) \?><\/td>(\s*<td><\?= htmlspecialchars\(\$row\[\'church_role\'\] \?: \'Member\'\) \?><\/td>\s*<td>\s*<\?php if \(\$dsr\): \?>\s*<span class="badge" style="background:<\?= \$dsr_c \?>22;color:<\?= \$dsr_c \?>;"><\?= htmlspecialchars\(\$dsr\) \?><\/span>\s*<\?php else: \?>\s*<span style="color:#bbb;">.*?<\/span>\s*<\?php endif; \?>\s*<\/td>\s*)<td style="color:#666;"><\?= htmlspecialchars\(\$row\[\'phone\'\] \?\? \'.*?\'\) \?><\/td>/s',
    '$1<td style="color:#666; font-weight:600;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>$2<td><span class="badge" style="background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:4px; font-weight:600; font-size:0.75rem;"><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></span></td>',
    $content
);

file_put_contents($file, $content);
echo "Done\n";
?>
