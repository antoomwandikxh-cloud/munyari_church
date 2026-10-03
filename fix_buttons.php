<?php
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);

// Find the <td> that contains the buttons
// We will replace it with a flex container
$pattern = '/<td>\s*<\?php if \(!empty\(\$m\[\'is_pastor\'\]\)\):\s*\?>.*?<\?php endif; \?>\s*<\/td>/s';

$content = preg_replace_callback($pattern, function($matches) {
    // Wrap the inner contents in a flex div
    $inner = $matches[0];
    // Replace the td tag opening with td containing a flex div
    $inner = str_replace('<td>', '<td style="white-space:nowrap;">' . "\n" . '                                    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">', $inner);
    // Replace the closing td with div closing
    $inner = str_replace('</td>', '</div>' . "\n" . '                                </td>', $inner);
    
    // Also remove the inline margin-right:5px and margin-bottom:4px from the links since flex gap handles it
    $inner = preg_replace('/margin-right:5px;?/', '', $inner);
    $inner = preg_replace('/margin-bottom:4px;?/', '', $inner);
    
    return $inner;
}, $content);

file_put_contents($file, $content);
echo "Updated buttons in $file\n";
?>
