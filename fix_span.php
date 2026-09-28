<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    
    $old = <<<'EOT'
                    if ($highest_leader && !empty($highest_leader['church_role'])) {
                        $best = get_best_role_from_string($highest_leader['church_role']);
                        if (strtolower($best) !== 'member') {
                            $leader_role = role_display_label($best, $dept, $highest_leader['gender'] ?? null);
                        }
                    }
                    
                    $leader_id   = 'deptLeader_'.str_replace(' ','',$dept);

                    if (count($dept_members_arr) > 0):
                    ?>
                        <!-- Hidden dept leader data for JS print -->
                        <span id="<?= $leader_id ?>"
                              data-name="<?= htmlspecialchars($leader_name) ?>"
                              data-vice="<?= htmlspecialchars($vice_name) ?>"
                              data-pic="uploads/<?= htmlspecialchars($leader_pic) ?>"
                              data-role="<?= htmlspecialchars($leader_role) ?>"
                              style="display:none;"></span>
EOT;
    $old = str_replace("\r", "", $old);
    
    $new = <<<'EOT'
                    if ($highest_leader && !empty($highest_leader['church_role'])) {
                        $best = get_best_role_from_string($highest_leader['church_role']);
                        if (strtolower($best) !== 'member') {
                            $leader_role = role_display_label($best, $dept, $highest_leader['gender'] ?? null);
                        }
                    }
                    
                    $vice_pic = $second_leader ? ($second_leader['profile_picture'] ?? 'default_avatar.png') : 'default_avatar.png';
                    
                    if ($d === 'youths') $vice_role = 'Vice Youth Chairperson';
                    elseif ($d === 'womens ministry') $vice_role = 'Vice Women Chairlady';
                    elseif ($d === 'elders') $vice_role = 'Vice Elder Chairman';
                    elseif ($d === 'sunday school') $vice_role = 'Vice Sunday School Patron';
                    else $vice_role = 'Vice Chairperson';

                    if ($second_leader && !empty($second_leader['church_role'])) {
                        $best_vice = get_best_role_from_string($second_leader['church_role']);
                        if (strtolower($best_vice) !== 'member') {
                            $vice_role = role_display_label($best_vice, $dept, $second_leader['gender'] ?? null);
                        }
                    }
                    
                    $leader_id   = 'deptLeader_'.str_replace(' ','',$dept);

                    if (count($dept_members_arr) > 0):
                    ?>
                        <!-- Hidden dept leader data for JS print -->
                        <span id="<?= $leader_id ?>"
                              data-name="<?= htmlspecialchars($leader_name) ?>"
                              data-vice="<?= htmlspecialchars($vice_name) ?>"
                              data-pic="uploads/<?= htmlspecialchars($leader_pic) ?>"
                              data-role="<?= htmlspecialchars($leader_role) ?>"
                              data-vice-pic="uploads/<?= htmlspecialchars($vice_pic) ?>"
                              data-vice-role="<?= htmlspecialchars($vice_role) ?>"
                              style="display:none;"></span>
EOT;
    
    $content = str_replace("\r", "", $content);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($f, $content);
        echo "Replaced span and vice logic in $f\n";
    } else {
        echo "Could not find text in $f\n";
    }
}
?>
