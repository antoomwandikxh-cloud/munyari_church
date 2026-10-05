<?php
$new_func = '                    function get_print_sort_order($role_raw, $dept) {
                        $d = strtolower(trim($dept ?? \'\'));
                        $role_parts = array_map(\'trim\', explode(\',\', strtolower($role_raw ?? \'\')));
                        $best = [99, 99];
                        foreach ($role_parts as $r) {
                            $r = trim($r);
                            if ($r === \'\') continue;
                            if (strpos($r, \'general church\') !== false || strpos($r, \'pastor\') !== false) { $rank = [0, 0]; }
                            elseif (strpos($d, \'elder\') !== false || strpos($r, \'elder\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [1, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [1, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [1, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [1, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [1, 4];
                                else                                                                                 $rank = [1, 5];
                            }
                            elseif (strpos($d, \'women\') !== false || strpos($r, \'women\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [2, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [2, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [2, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [2, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [2, 4];
                                else                                                                                 $rank = [2, 5];
                            }
                            elseif (strpos($d, \'youth\') !== false || strpos($r, \'youth\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [3, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [3, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [3, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [3, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [3, 4];
                                elseif (strpos($r, \'mama youth\') !== false || strpos($r, \'baba youth\') !== false) $rank = [3, 5];
                                else                                                                                 $rank = [3, 6];
                            }
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
                            elseif (strpos($r, \'building\') !== false) {
                                if (preg_match(\'/chair(man|person|lady)/\', $r) && strpos($r, \'vice\') === false) $rank = [7, 0];
                                elseif (strpos($r, \'vice\') !== false && preg_match(\'/chair/\', $r))               $rank = [7, 1];
                                elseif (strpos($r, \'secretary\') !== false && strpos($r, \'vice\') === false)        $rank = [7, 2];
                                elseif (strpos($r, \'vice\') !== false && strpos($r, \'secretary\') !== false)        $rank = [7, 3];
                                elseif (strpos($r, \'treasurer\') !== false)                                         $rank = [7, 4];
                                else                                                                                 $rank = [7, 5];
                            }
                            elseif ($r !== \'member\' && $r !== \'\') { $rank = [8, 0]; }
                            else { $rank = [99, 0]; }
                            if ($rank[0] < $best[0] || ($rank[0] === $best[0] && $rank[1] < $best[1])) { $best = $rank; }
                        }
                        return $best;
                    }';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $lines = file($file);
    $start_idx = -1;
    $end_idx = -1;
    for ($i = 0; $i < count($lines); $i++) {
        if (strpos($lines[$i], 'function get_print_sort_order($role_raw, $dept)') !== false) {
            $start_idx = $i;
        }
        if ($start_idx !== -1 && $i > $start_idx && strpos($lines[$i], '$members->data_seek(0);') !== false) {
            // Found the line just after the end of the block we want to replace
            // Go backwards from here to find the first closing brace '}' that ends the if(!function_exists) or the function itself
            $end_idx = $i - 1;
            while(trim($lines[$end_idx]) !== '}') {
                $end_idx--;
            }
            break;
        }
    }
    
    if ($start_idx !== -1 && $end_idx !== -1) {
        $before = array_slice($lines, 0, $start_idx);
        $after = array_slice($lines, $end_idx + 1);
        $new_lines = array_merge($before, [$new_func . "\n", str_pad('', 16, ' ') . "}\n"], $after);
        file_put_contents($file, implode("", $new_lines));
        echo "Fixed $file from line " . ($start_idx+1) . " to " . ($end_idx+1) . "\n";
    } else {
        echo "Could not find start/end in $file\n";
    }
}
?>
