<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

// 1. Add Village header to printout table
$content = str_replace(
    '<th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Address</th>',
    '<th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Address</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Village</th>',
    $content
);

// Update colspan for group headers
$content = str_replace('<td colspan="8"', '<td colspan="9"', $content);

// 2. Add village data and bold pastor name
$pattern = '/<td style="padding:5px 8px;border:1px solid #ddd;font-weight:600;">(<\?= htmlspecialchars\(\$pm\[\'first_name\'\]\.\' \'\.\$pm\[\'last_name\'\]\) \?>)<\/td>\s*<td style="padding:5px 8px;border:1px solid #ddd;">(<\?= htmlspecialchars\(\$pm\[\'phone\'\] \?\? \'-\'\) \?>)<\/td>\s*<td style="padding:5px 8px;border:1px solid #ddd;">(<\?= htmlspecialchars\(\$pm\[\'address\'\] \?\? \'-\'\) \?>)<\/td>/s';

$replacement = <<<PHP
<td style="padding:5px 8px;border:1px solid #ddd;font-weight:<?= !empty(\$pm['is_pastor']) ? '900;color:#1e3a8a;text-transform:uppercase;font-size:0.85rem;' : '600;' ?>;">
                                        <?= htmlspecialchars(\$pm['first_name'].' '.\$pm['last_name']) ?>
                                        <?= !empty(\$pm['is_pastor']) ? ' <span style="color:#dc2626;">(PASTOR)</span>' : '' ?>
                                    </td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;">\$2</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;">\$3</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;"><?= htmlspecialchars(\$pm['church_village'] ?? '-') ?></td>
PHP;

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);
echo "Updated print layout in $file\n";
?>
