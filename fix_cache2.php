<?php
$files = ['register_page.php', 'admin_dashboard.php', 'pastor_dashboard.php'];
$v = time();
foreach ($files as $file) {
    $c = file_get_contents($file);
    // Add ?v=timestamp to script.js to break cache
    $c = preg_replace('/src="script\.js\?v=\d+"/i', 'src="script.js?v=' . $v . '"', $c);
    file_put_contents($file, $c);
    echo "Cache busted $file\n";
}
?>
