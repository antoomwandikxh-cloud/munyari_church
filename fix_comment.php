<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    // Fix the broken <?php ... ?> that appeared inside an already-open PHP block
    $content = str_replace(
        '<?php // (group ribbons display only in printout, not on screen) ?>',
        '// (group ribbons display only in printout, not on screen)',
        $content
    );
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
?>
