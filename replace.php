<?php
$content = file_get_contents('pastor_dashboard.php');
$old = <<<'EOT'
                    // Find top leader (chairman/chairperson/chairlady) for this dept
                    $dept_leader = null;
                    $lq = $conn->query("SELECT * FROM members WHERE is_approved=1 AND department='$dept_esc'
                        AND LOWER(church_role) REGEXP '(^|, *)(youth |women |elder |sunday school )?(chairman|chairperson|chairlady)( *,|$)'
                        LIMIT 1");
                    if ($lq && $lq->num_rows > 0) $dept_leader = $lq->fetch_assoc();

                    // Find vice leader
                    $vice_leader = null;
                    $vq = $conn->query("SELECT * FROM members WHERE is_approved=1 AND department='$dept_esc'
                        AND LOWER(church_role) REGEXP '(^|, *)vice (youth |women |elder |sunday school )?(chairman|chairperson|chairlady)( *,|$)'
                        LIMIT 1");
                    if ($vq && $vq->num_rows > 0) $vice_leader = $vq->fetch_assoc();

                    $leader_name = $dept_leader ? strtoupper(trim($dept_leader['first_name'].' '.$dept_leader['last_name'])) : 'N/A';
                    $leader_pic  = $dept_leader ? ($dept_leader['profile_picture'] ?? 'default_avatar.png') : 'default_avatar.png';
                    
                    $vice_name = $vice_leader ? strtoupper(trim($vice_leader['first_name'].' '.$vice_leader['last_name'])) : 'N/A';
                    
                    $raw_role = $dept_leader ? ($dept_leader['church_role'] ?? 'Chairperson') : 'Chairperson';
                    $role_parts = explode(',', $raw_role);
                    $leader_role = 'Chairperson';
                    foreach ($role_parts as $r) {
                        if (preg_match('/chairman|chairperson|chairlady/i', $r)) {
                            $leader_role = trim($r);
                            break;
                        }
                    }
EOT;

$old = str_replace("\r", "", $old);
$content = str_replace("\r", "", $content);

$new_repl = <<<'EOT'
                    // Find top leaders dynamically based on rank (1 = Chair, 2 = Vice, 3 = Sec, etc.)
                    $highest_leader = null;
                    $second_leader = null;
                    
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

                    $leader_name = $highest_leader ? strtoupper(trim($highest_leader['first_name'].' '.$highest_leader['last_name'])) : 'N/A';
                    $leader_pic  = $highest_leader ? ($highest_leader['profile_picture'] ?? 'default_avatar.png') : 'default_avatar.png';
                    
                    $vice_name = $second_leader ? strtoupper(trim($second_leader['first_name'].' '.$second_leader['last_name'])) : 'N/A';
                    
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

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new_repl, $content);
    file_put_contents('pastor_dashboard.php', $content);
    echo "Success replacing in pastor_dashboard.php\n";
} else {
    echo "Could not find old text in pastor_dashboard.php\n";
}

$content_admin = file_get_contents('admin_dashboard.php');
$content_admin = str_replace("\r", "", $content_admin);
if (strpos($content_admin, $old) !== false) {
    $content_admin = str_replace($old, $new_repl, $content_admin);
    file_put_contents('admin_dashboard.php', $content_admin);
    echo "Success replacing in admin_dashboard.php\n";
} else {
    echo "Could not find old text in admin_dashboard.php\n";
}

?>
