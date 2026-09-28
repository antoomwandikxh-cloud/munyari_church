<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    
    $old = <<<'EOT'
                    foreach ($dept_members_arr as $dm) {
                        $rank = (int)($dm['role_rank'] ?? 99);
                        if ($rank > 0 && $rank < 99) {
                            if (!$highest_leader) {
                                $highest_leader = $dm;
                            } elseif (!$second_leader) {
                                $second_leader = $dm;
                                break;
                            }
                        }
                    }
EOT;
    $old = str_replace("\r", "", $old);
    
    $new = <<<'EOT'
                    foreach ($dept_members_arr as $dm) {
                        $rank = (int)($dm['role_rank'] ?? 99);
                        if ($rank > 0 && $rank <= 2) { // ONLY RANK 1 (Chair) or RANK 2 (Vice)
                            if (!$highest_leader) {
                                $highest_leader = $dm;
                            } elseif (!$second_leader) {
                                $second_leader = $dm;
                                break;
                            }
                        }
                    }
EOT;
    
    $content = str_replace("\r", "", $content);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($f, $content);
        echo "Replaced rank logic in $f\n";
    } else {
        echo "Could not find text in $f\n";
    }
}
?>
