<?php
$old_td = '                                            // Sort them so the one matching their main department comes FIRST
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
                                            });';

$new_td = '                                            // Sort them so the one matching their CURRENT PRINT SECTION comes FIRST
                                            $print_section_name = strtolower($gl ?? \'\');
                                            usort($leader_labels, function($a, $b) use ($print_section_name) {
                                                $a_lower = strtolower($a);
                                                $b_lower = strtolower($b);
                                                
                                                // Strong keyword matching based on current section
                                                $a_match = 0; $b_match = 0;
                                                
                                                if (strpos($print_section_name, \'women\') !== false) {
                                                    if (strpos($a_lower, \'women\') !== false) $a_match = 10;
                                                    if (strpos($b_lower, \'women\') !== false) $b_match = 10;
                                                }
                                                if (strpos($print_section_name, \'youth\') !== false) {
                                                    if (strpos($a_lower, \'youth\') !== false) $a_match = 10;
                                                    if (strpos($b_lower, \'youth\') !== false) $b_match = 10;
                                                }
                                                if (strpos($print_section_name, \'elder\') !== false) {
                                                    if (strpos($a_lower, \'elder\') !== false) $a_match = 10;
                                                    if (strpos($b_lower, \'elder\') !== false) $b_match = 10;
                                                }
                                                if (strpos($print_section_name, \'sunday\') !== false) {
                                                    if (strpos($a_lower, \'sunday\') !== false) $a_match = 10;
                                                    if (strpos($b_lower, \'sunday\') !== false) $b_match = 10;
                                                }
                                                if (strpos($print_section_name, \'building\') !== false) {
                                                    if (strpos($a_lower, \'building\') !== false) $a_match = 10;
                                                    if (strpos($b_lower, \'building\') !== false) $b_match = 10;
                                                }
                                                
                                                // Fallback word matching if section is weird
                                                if ($a_match === 0 && $b_match === 0 && $print_section_name) {
                                                    $words = explode(\' \', str_replace([\' ministry\', \' department\', \'s\'], \'\', $print_section_name));
                                                    foreach ($words as $w) {
                                                        if (strlen($w) > 3) {
                                                            if (strpos($a_lower, $w) !== false) $a_match = 1;
                                                            if (strpos($b_lower, $w) !== false) $b_match = 1;
                                                        }
                                                    }
                                                }
                                                
                                                return $b_match - $a_match;
                                            });';

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
