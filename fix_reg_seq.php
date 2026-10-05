<?php
$c = file_get_contents('register_page.php');

// Remove the inline seqUnlock function completely from register_page.php
$c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?\/\/ -----------------------------------------\s*\}/m', '/* seqUnlock now in script.js */', $c);

file_put_contents('register_page.php', $c);
echo "Removed seqUnlock from register_page.php\n";
?>
