<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    // Remove old badge remnants left from earlier broken injections
    $c = preg_replace('/\s*\/\/ --- ADDED: Visual Activated Indicator ---[\s\S]*?\/\/ -----------------------------------------/', '', $c);
    // Also remove dangling currBadge lines that appear after closing brace of seqUnlock
    $c = preg_replace('/\n(\s*)currBadge\.innerHTML = [\'"].*?[\'"];\n\1currBadge\.style\.color = [\'"].*?[\'"];/', '', $c);
    file_put_contents($file, $c);
    echo "Cleaned $file\n";
}
?>
