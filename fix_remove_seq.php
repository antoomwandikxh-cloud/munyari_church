<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // In admin_dashboard.php the function ends with // -----------------------------------------
    $c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?\/\/ -----------------------------------------\s*\}/m', '/* seqUnlock now in script.js */', $c);
    
    // In pastor_dashboard.php it might not have the badge stuff
    $c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?(?:if \(\!filled && next\.tagName === \'INPUT\'\) next\.value = \'\';|if \(\!filled\) next\.value = \'\';)\s*\}/m', '/* seqUnlock now in script.js */', $c);
    
    file_put_contents($file, $c);
    echo "Removed seqUnlock from $file\n";
}
?>
