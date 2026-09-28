<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    
    $old = <<<'EOT'
                    $leader_role = 'Leader';
                    if ($highest_leader && !empty($highest_leader['church_role'])) {
EOT;
    $old = str_replace("\r", "", $old);
    
    $new = <<<'EOT'
                    $d = strtolower(trim($dept));
                    if ($d === 'youths') $leader_role = 'Youth Chairperson';
                    elseif ($d === 'womens ministry') $leader_role = 'Women Chairlady';
                    elseif ($d === 'elders') $leader_role = 'Elder Chairman';
                    elseif ($d === 'sunday school') $leader_role = 'Sunday School Patron';
                    else $leader_role = 'Chairperson';

                    if ($highest_leader && !empty($highest_leader['church_role'])) {
EOT;
    
    $content = str_replace("\r", "", $content);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($f, $content);
        echo "Replaced fallback in $f\n";
    } else {
        echo "Could not find text in $f\n";
    }
}
?>
