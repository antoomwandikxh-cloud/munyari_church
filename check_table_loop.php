<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // In both dashboards, the loop over members starts with:
    // <?php $i = 1; while($vm = $v_members->fetch_assoc()):
    // Then after the while loop:
    // <?php foreach ($v_pastor_rows as $pr):
    
    $start_pos = strpos($content, '<tbody>');
    if ($start_pos !== false) {
        echo "Found tbody in $file\n";
    }
}
?>
