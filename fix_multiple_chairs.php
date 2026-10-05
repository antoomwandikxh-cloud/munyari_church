<?php
$old_td = '                                        <?php elseif ($is_dept_leader): 
                                            $leader_label = null;
                                            $r_parts = explode(\',\', strtolower($pm[\'church_role\'] ?? \'\'));
                                            
                                            // Only display brackets for topmost leaders (chairperson/chairman/chairlady or patron)
                                            // Make sure NOT to include vice chairs
                                            foreach($r_parts as $rp) {
                                                $rp = trim($rp);
                                                if (strpos($rp, \'vice\') === false && (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false)) {
                                                    $leader_label = strtoupper($rp);
                                                    break;
                                                }
                                            }
                                            
                                            if ($leader_label):
                                        ?>';

$new_td = '                                        <?php elseif ($is_dept_leader): 
                                            $leader_labels = [];
                                            $r_parts = explode(\',\', strtolower($pm[\'church_role\'] ?? \'\'));
                                            $user_dept = strtolower($pm[\'department\'] ?? \'\');
                                            
                                            // Collect ALL top leadership roles
                                            foreach($r_parts as $rp) {
                                                $rp = trim($rp);
                                                if (strpos($rp, \'vice\') === false && (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false)) {
                                                    $leader_labels[] = strtoupper($rp);
                                                }
                                            }
                                            
                                            // Sort them so the one matching their main department comes FIRST
                                            usort($leader_labels, function($a, $b) use ($user_dept) {
                                                if (!$user_dept) return 0;
                                                // Check if role contains a word from the department (e.g. "women" or "youth")
                                                $dept_words = explode(\' \', str_replace(\' ministry\', \'\', str_replace(\' department\', \'\', $user_dept)));
                                                $a_match = 0; $b_match = 0;
                                                foreach ($dept_words as $w) {
                                                    if (strlen($w) > 3 && strpos(strtolower($a), $w) !== false) $a_match = 1;
                                                    if (strlen($w) > 3 && strpos(strtolower($b), $w) !== false) $b_match = 1;
                                                }
                                                return $b_match - $a_match; // Higher match comes first
                                            });
                                            
                                            if (!empty($leader_labels)):
                                                $leader_label = implode(\', \', $leader_labels);
                                        ?>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_td);
    if ($count > 0) {
        $content = str_replace($old_td, $new_td, $content);
        file_put_contents($file, $content);
        echo "Updated $file ($count matches)\n";
    } else {
        echo "Could not find target in $file\n";
    }
}
?>
