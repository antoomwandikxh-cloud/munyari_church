<?php
$old_main = '                                        <?php if (!empty($pm[\'is_pastor\'])): ?>
                                            <span style="color:#2563eb; font-weight:900;">(PASTOR)</span>';

$new_main = '                                        <?php if (!empty($pm[\'is_pastor\'])): ?>
                                            <br><span style="color:#2563eb; font-size:0.55rem; font-weight:900;">(PASTOR)</span>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_main);
    if ($count > 0) {
        $content = str_replace($old_main, $new_main, $content);
        file_put_contents($file, $content);
        echo "Updated main printout Pastor style in $file ($count matches)\n";
    }
}
?>
