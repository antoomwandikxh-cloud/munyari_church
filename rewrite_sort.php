<?php
$new_func = '                    function get_print_sort_order($role_raw, $dept) {
                        $d = strtolower(trim($dept ?? \'\'));
                        // Split roles by comma so multi-role members get their BEST rank
                        $role_parts = array_map(\'trim\', explode(\',\', strtolower($role_raw ?? \'\')));
                        $best = [99, 99];

                        foreach ($role_parts as $r) {
                            $r = trim($r);
                            if ($r === \'\') continue;

                            // --- PASTOR ---
                            if (strpos($r, \'general church\') !== false || strpos($r, \'pastor\') !== false) {
                                $rank = [0, 0];
                            }
                            // --- ELDERS ---
                            elseif (strpos($d, \'elder\') !== false || strpos($r, \'elder\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [1, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [1, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [1, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [1, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [1, 4];
                                else                                                                                 $rank = [1, 5];
                            }
                            // --- WOMEN\'S MINISTRY ---
                            elseif (strpos($d, \'women\') !== false || strpos($r, \'women\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [2, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [2, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [2, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [2, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [2, 4];
                                else                                                                                 $rank = [2, 5];
                            }
                            // --- YOUTHS ---
                            elseif (strpos($d, \'youth\') !== false || strpos($r, \'youth\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [3, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [3, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [3, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [3, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [3, 4];
                                elseif (strpos($r, \'mama youth\') !== false || strpos($r, \'baba youth\') !== false) $rank = [3, 5];
                                else                                                                                 $rank = [3, 6];
                            }
                            // --- SUNDAY SCHOOL ---
                            elseif (strpos($d, \'sunday\') !== false || strpos($r, \'sunday school\') !== false) {
                                if (strpos($r, \'patron\') !== false && strpos($r, \'vice\') === false)               $rank = [4, 0];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'patron\') !== false)           $rank = [4, 1];
                                elseif (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [4, 2];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))                $rank = [4, 3];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [4, 4];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [4, 5];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [4, 6];
                                elseif (strpos($r, \'teacher\') !== false)                                           $rank = [5, 0];
                                else                                                                                 $rank = [6, 0];
                            }
                            // --- BUILDING ---
                            elseif (strpos($r, \'building\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [7, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [7, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [7, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [7, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [7, 4];
                                else                                                                                 $rank = [7, 5];
                            }
                            // --- OTHER NAMED ROLES ---
                            elseif ($r !== \'member\' && $r !== \'\') {
                                $rank = [8, 0];
                            }
                            // --- PLAIN MEMBER ---
                            else {
                                $rank = [99, 0];
                            }

                            // Keep the best (lowest) rank found across all roles
                            if ($rank[0] < $best[0] || ($rank[0] === $best[0] && $rank[1] < $best[1])) {
                                $best = $rank;
                            }
                        }
                        return $best;
                    }';

$old_func_pattern = '/function get_print_sort_order\(\$role_raw, \$dept\) \{.*?\}/s';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    $content = preg_replace($old_func_pattern, $new_func, $content, 1);
    file_put_contents($file, $content);
    $ok = strpos($content, 'Split roles by comma') !== false;
    echo "$file: " . ($ok ? "OK" : "FAILED") . "\n";
}
?>
