<?php
$old_js = "    w.document.write('.no-print{display:none!important;}');";
$new_js = "    w.document.write('.no-print{display:none!important;}');\r\n    w.document.write('.screen-name{display:none!important;}');\r\n    w.document.write('.print-only-name{display:inline!important;}');";

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_js);
    if ($count > 0) {
        $content = str_replace($old_js, $new_js, $content);
        file_put_contents($file, $content);
        echo "Updated JS in $file ($count matches)\n";
    } else {
        echo "Could not find JS in $file\n";
    }
}
?>
