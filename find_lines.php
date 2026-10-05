<?php
$file = 'pastor_dashboard.php';
$lines = file($file);

// Find line with Address th in the printout thead (around line 1935-1937)
for ($i = 1928; $i < 1945; $i++) {
    if (strpos($lines[$i], '>Address<') !== false && strpos($lines[$i-5], 'padding:7px 8px') !== false) {
        echo "Found Address at line " . ($i+1) . ": " . trim($lines[$i]) . "\n";
    }
    if (strpos($lines[$i], '>Department<') !== false && strpos($lines[$i-5], 'padding:7px 8px') !== false) {
        echo "Found Department at line " . ($i+1) . ": " . trim($lines[$i]) . "\n";
    }
}
?>
