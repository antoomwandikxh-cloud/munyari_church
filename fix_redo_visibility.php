<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Replace the opening if condition for the global buttons block
    $old_condition = '<?php if (isset($has_any_roles) && $has_any_roles): ?>';
    $new_condition = '<?php if ((isset($has_any_roles) && $has_any_roles) || !empty($_SESSION[\'global_role_undo_backup\'])): ?>';
    
    $count = substr_count($content, $old_condition);
    if ($count > 0) {
        $content = str_replace($old_condition, $new_condition, $content);
        file_put_contents($file, $content);
        echo "Fixed condition in $file ($count matches)\n";
    } else {
        echo "Condition not found in $file\n";
    }
}
?>
