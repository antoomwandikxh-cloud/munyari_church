<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    
    $old = <<<'EOT'
                    $leader_role = 'Leader';
                    if ($highest_leader && !empty($highest_leader['church_role'])) {
                        $role_parts = explode(',', $highest_leader['church_role']);
                        foreach ($role_parts as $r) {
                            $tr = trim($r);
                            if (strtolower($tr) !== 'member' && $tr !== '' && stripos($tr, 'village leader') === false) {
                                $leader_role = $tr;
                                break;
                            }
                        }
                    }
EOT;
    $old = str_replace("\r", "", $old);
    
    $new = <<<'EOT'
                    $leader_role = 'Leader';
                    if ($highest_leader && !empty($highest_leader['church_role'])) {
                        $best = get_best_role_from_string($highest_leader['church_role']);
                        if (strtolower($best) !== 'member') {
                            $leader_role = role_display_label($best, $dept, $highest_leader['gender'] ?? null);
                        }
                    }
EOT;
    
    $content = str_replace("\r", "", $content);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($f, $content);
        echo "Replaced in $f\n";
    } else {
        echo "Could not find text in $f\n";
    }
}
?>
