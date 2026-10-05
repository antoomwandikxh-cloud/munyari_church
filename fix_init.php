<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Add initialization for UI last dept
    $old_ui_init = "\$ui_last_grp = -1;";
    $new_ui_init = "\$ui_last_grp = -1;\n                            \$ui_last_dept = '';";
    $c = str_replace($old_ui_init, $new_ui_init, $c);

    // Add initialization for Print last dept
    $old_print_init = "\$last_grp = -1;";
    $new_print_init = "\$last_grp = -1;\n                                    \$last_dept = '';";
    $c = str_replace($old_print_init, $new_print_init, $c);

    file_put_contents($file, $c);
    echo "Added init to $file\n";
}
?>
