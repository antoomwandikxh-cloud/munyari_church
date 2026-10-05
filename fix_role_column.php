<?php
$old_td = '                                                <td><?= htmlspecialchars($dm[\'church_role\'] ?? \'Member\') ?></td>';

$new_td = '                                                <?php
                                                    $raw_roles = explode(\',\', $dm[\'church_role\'] ?? \'Member\');
                                                    $display_roles = [];
                                                    $curr_dept = strtolower($dept);
                                                    
                                                    foreach ($raw_roles as $rr) {
                                                        $rr = trim($rr);
                                                        if (empty($rr)) continue;
                                                        if (strtolower($rr) === \'member\') { $display_roles[] = $rr; continue; }
                                                        
                                                        $rr_l = strtolower($rr);
                                                        $keep = true;
                                                        
                                                        // If it explicitly belongs to another major department, hide it!
                                                        if (strpos($rr_l, \'building\') !== false && strpos($curr_dept, \'building\') === false) $keep = false;
                                                        if ((strpos($rr_l, \'youth\') !== false || strpos($rr_l, \'youths\') !== false) && strpos($curr_dept, \'youth\') === false) $keep = false;
                                                        if ((strpos($rr_l, \'women\') !== false || strpos($rr_l, \'womens\') !== false) && strpos($curr_dept, \'women\') === false) $keep = false;
                                                        if (strpos($rr_l, \'elder\') !== false && strpos($curr_dept, \'elder\') === false) $keep = false;
                                                        if (strpos($rr_l, \'sunday\') !== false && strpos($curr_dept, \'sunday\') === false) $keep = false;
                                                        if (strpos($rr_l, \'village leader\') !== false) $keep = false; // Never show village leader in dept table
                                                        
                                                        if ($keep) {
                                                            $display_roles[] = $rr;
                                                        }
                                                    }
                                                    
                                                    $final_role_str = !empty($display_roles) ? implode(\', \', $display_roles) : \'Member\';
                                                ?>
                                                <td><?= htmlspecialchars($final_role_str) ?></td>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_td);
    if ($count > 0) {
        $content = str_replace($old_td, $new_td, $content);
        file_put_contents($file, $content);
        echo "Updated Role column filtering in $file ($count matches)\n";
    } else {
        echo "Could not find target in $file\n";
    }
}
?>
