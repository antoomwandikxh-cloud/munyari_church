<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // 1. Remove department CASE from query
    $old_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
    $new_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, first_name ASC";
    
    if (strpos($c, $old_q) !== false) {
        $c = str_replace($old_q, $new_q, $c);
    }
    
    // 2. Reduce profile block size
    $old_prof1 = '<div class="print-leader-profile" style="display:none; text-align:center; margin-bottom:20px; background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0;">';
    $new_prof1 = '<div class="print-leader-profile" style="display:none; text-align:center; margin:0 auto 10px; background:#f8fafc; padding:6px 12px; border-radius:6px; border:1px solid #e2e8f0; width:fit-content; min-width:200px;">';
    
    $old_img = 'style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid <?= $v_color ?>; margin-bottom:8px;"';
    $new_img = 'style="width:55px; height:55px; border-radius:50%; object-fit:cover; border:2px solid <?= $v_color ?>; margin-bottom:4px;"';
    
    $old_img_empty = 'style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid #cbd5e1; margin-bottom:8px; opacity:0.7; filter:grayscale(100%);"';
    $new_img_empty = 'style="width:55px; height:55px; border-radius:50%; object-fit:cover; border:2px solid #cbd5e1; margin-bottom:4px; opacity:0.7; filter:grayscale(100%);"';

    $c = str_replace($old_prof1, $new_prof1, $c);
    $c = str_replace($old_img, $new_img, $c);
    $c = str_replace($old_img_empty, $new_img_empty, $c);
    
    file_put_contents($file, $c);
    echo "Fixed $file\n";
}

$p_all = 'print_all_villages.php';
$c_all = file_get_contents($p_all);
$old_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
$new_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, first_name ASC";
if (strpos($c_all, $old_q) !== false) {
    $c_all = str_replace($old_q, $new_q, $c_all);
    file_put_contents($p_all, $c_all);
    echo "Fixed print_all_villages.php\n";
}

?>
