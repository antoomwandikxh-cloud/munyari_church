<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

$pattern = '/<td>\s*<\?php if \(!empty\(\$m\[\'is_pastor\'\]\)\):\s*\?>.*?<\?php endif; \?>\s*<\/td>/s';

$content = preg_replace_callback($pattern, function($matches) {
    $inner = $matches[0];
    $inner = str_replace('<td>', '<td style="white-space:nowrap;">' . "\n" . '                                    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">', $inner);
    $inner = str_replace('</td>', '</div>' . "\n" . '                                </td>', $inner);
    $inner = preg_replace('/margin-right:5px;?/', '', $inner);
    $inner = preg_replace('/margin-bottom:4px;?/', '', $inner);
    return $inner;
}, $content);

file_put_contents($file, $content);
echo "Updated buttons in $file\n";
?>
