<?php
$content = file_get_contents('role_departments.php');

$old = <<<'EOT'
    // Gender-aware display for Youth Chairperson and Vice Youth Chairperson
    if (in_array($normalized_role, ['youth chairperson', 'vice youth chairperson'], true)) {
        if ($member_gender !== null) {
            $label = get_youth_chairperson_title($member_gender, $normalized_role);
        } else {
            // No gender passed ?" show the neutral label
            $label = $normalized_role === 'vice youth chairperson' ? 'Vice Youth Chairperson' : 'Youth Chairperson';
        }
    } elseif (in_array($normalized_role, ['building chairperson', 'vice building chairperson'], true)) {
        if ($member_gender !== null) {
            $label = get_building_chairperson_title($member_gender, $normalized_role);
        } else {
            $label = $normalized_role === 'vice building chairperson' ? 'Vice Building Chairperson' : 'Building Chairperson';
        }
    } elseif ($normalized_role === 'mama youth') {
EOT;

$new = <<<'EOT'
    // Universal formatting for Chairpersons across all departments
    $is_chair = preg_match('/^(youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)$/', $normalized_role);
    $is_vice_chair = preg_match('/^vice (youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)$/', $normalized_role);
    
    if ($is_chair || $is_vice_chair) {
        $gender_lower = strtolower(trim($member_gender ?? ''));
        $is_female = in_array($gender_lower, ['female', 'f', 'woman', 'girl'], true);
        $d = strtolower(trim($member_department ?? ''));
        
        if ($d === 'youths' || str_contains($normalized_role, 'youth')) {
            $label = $is_vice_chair ? ($is_female ? 'Vice Youth Chairlady' : 'Vice Youth Chairman') : ($is_female ? 'Youth Chairlady' : 'Youth Chairman');
        } elseif ($d === 'womens ministry' || str_contains($normalized_role, 'women')) {
            $label = $is_vice_chair ? 'Vice Women Chairlady' : 'Women Chairlady';
        } elseif ($d === 'elders' || str_contains($normalized_role, 'elder')) {
            $label = $is_vice_chair ? 'Vice Elder Chairman' : 'Elder Chairman';
        } elseif ($d === 'sunday school' || str_contains($normalized_role, 'sunday school') || str_contains($normalized_role, 'patron')) {
            $label = $is_vice_chair ? 'Vice Sunday School Patron' : 'Sunday School Patron';
        } elseif ($d === 'building' || str_contains($normalized_role, 'building')) {
            $label = $is_vice_chair ? ($is_female ? 'Vice Building Chairlady' : 'Vice Building Chairman') : ($is_female ? 'Building Chairlady' : 'Building Chairman');
        } else {
            // Fallback generic
            $label = $is_vice_chair ? ($is_female ? 'Vice Chairlady' : 'Vice Chairman') : ($is_female ? 'Chairlady' : 'Chairman');
        }
    } elseif ($normalized_role === 'mama youth') {
EOT;

$old = str_replace("\r", "", $old);
$old = str_replace("?\"", "-", $old); // The weird character from CLI output
$content = str_replace("\r", "", $content);

// Try to match ignoring the exact "No gender passed" line if there's an encoding issue
$content = preg_replace('/\/\/ Gender-aware display for Youth.*?\} elseif \(\$normalized_role === \'mama youth\'\) \{/s', $new, $content);

file_put_contents('role_departments.php', $content);
echo "Updated role_display_label in role_departments.php\n";
?>
