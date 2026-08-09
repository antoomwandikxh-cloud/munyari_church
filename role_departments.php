<?php
function normalize_role_name($role) {
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $role ?? '')));
    $normalized = str_replace('(subsidiary)', '', $normalized);
    return trim($normalized);
}

function department_for_role($role) {
    $role_departments = [
        // Youth ministry - both chairperson and chairman/chairlady variants
        'youth chairperson'       => 'Youths',
        'youth chairman'          => 'Youths',
        'youth chairlady'         => 'Youths',
        'vice youth chairperson'  => 'Youths',
        'vice youth chairman'     => 'Youths',
        'vice youth chairlady'    => 'Youths',
        'youth secretary'         => 'Youths',
        'vice youth secretary'    => 'Youths',
        'youth treasurer'         => 'Youths',

        'women chairlady'         => 'Womens Ministry',
        'vice women chairlady'    => 'Womens Ministry',
        'women secretary'         => 'Womens Ministry',
        'vice women secretary'    => 'Womens Ministry',
        'women treasurer'         => 'Womens Ministry',

        'elder chairman'          => 'Elders',
        'vice elder chairman'     => 'Elders',
        'elder secretary'         => 'Elders',
        'vice elder secretary'    => 'Elders',
        'elder treasurer'         => 'Elders',

        'sunday school patron'    => 'Sunday School',
        'vice sunday school patron' => 'Sunday School',
        'sunday school secretary' => 'Sunday School',
        'vice sunday school secretary' => 'Sunday School',
        'sunday school treasurer' => 'Sunday School',
    ];

    $normalized = normalize_role_name($role);
    return $role_departments[$normalized] ?? null;
}

function role_department_sql($conn, $role) {
    $department = department_for_role($role);
    return $department ? ", department = '" . $conn->real_escape_string($department) . "'" : "";
}

function department_aliases($department) {
    $normalized = strtolower(trim(str_replace("'", '', $department ?? '')));
    $aliases = [
        'womens ministry' => ['Womens Ministry', "Women's Ministry", 'Women Ministry', 'Women'],
        'youths' => ['Youths', 'Youth Ministry', 'Youth'],
        'elders' => ['Elders', 'Elder Ministry'],
        'sunday school' => ['Sunday School'],
        'none' => ['None', ''],
    ];

    return $aliases[$normalized] ?? [$department];
}

function department_match_sql($conn, $column, $department) {
    $parts = [];
    foreach (department_aliases($department) as $alias) {
        $parts[] = "$column = '" . $conn->real_escape_string($alias) . "'";
    }

    return '(' . implode(' OR ', array_unique($parts)) . ')';
}

function department_allows_graduation_and_sport($department) {
    $normalized = strtolower(trim(str_replace("'", '', $department ?? '')));
    return in_array($normalized, ['youths', 'youth ministry', 'youth', 'sunday school'], true);
}

function department_matches($member_department, $department) {
    $member_aliases = array_map('strtolower', department_aliases($member_department));
    $department_aliases = array_map('strtolower', department_aliases($department));
    return !empty(array_intersect($member_aliases, $department_aliases));
}

function is_subsidiary_department_role($role) {
    $normalized_role = normalize_role_name($role);
    return in_array($normalized_role, [
        'organizing secretary', 'vice organizing secretary',
        'discipline master', 'vice discipline master',
        'prayer coordinator', 'vice prayer coordinator',
        'choir leader', 'vice choir leader',
        'graduands secretary', 'vice graduands secretary',
        'sport secretary', 'sports secretary', 'vice sport secretary', 'vice sports secretary'
    ], true);
}

function role_allowed_for_department($role, $department) {
    $normalized_role = normalize_role_name($role);
    if (in_array($normalized_role, ['graduands secretary', 'sport secretary', 'sports secretary'], true)) {
        return department_allows_graduation_and_sport($department);
    }

    return true;
}

function role_assignment_departments($role, $context_department = null) {
    $normalized_role = normalize_role_name($role);

    if (is_subsidiary_department_role($role) && !empty($context_department)) {
        return [$context_department];
    }

    if ($normalized_role === 'senior church elder') {
        return ['Elders'];
    }

    if (in_array($normalized_role, ['general church secretary', 'vice church secretary', 'treasurer'], true)) {
        return ['Elders', 'Womens Ministry'];
    }

    if ($normalized_role === 'mama youth') {
        return ['Womens Ministry'];
    }

    if ($normalized_role === 'baba youth') {
        return ['Elders'];
    }

    $department = department_for_role($role);
    return $department ? [$department] : [];
}

function member_can_receive_role($member_department, $role) {
    $allowed_departments = role_assignment_departments($role);
    if (empty($allowed_departments)) {
        return true;
    }

    $member_aliases = array_map('strtolower', department_aliases($member_department));
    foreach ($allowed_departments as $department) {
        foreach (department_aliases($department) as $alias) {
            if (in_array(strtolower($alias), $member_aliases, true)) {
                return true;
            }
        }
    }

    return false;
}

function is_youth_chairperson_role($role) {
    $n = normalize_role_name($role);
    return in_array($n, ['youth chairperson', 'youth chairman', 'youth chairlady', 'vice youth chairperson', 'vice youth chairman', 'vice youth chairlady'], true);
}

function is_building_chairperson_role($role) {
    $n = normalize_role_name($role);
    return in_array($n, ['building chairperson', 'building chairman', 'building chairlady', 'vice building chairperson', 'vice building chairman', 'vice building chairlady'], true);
}

function is_building_leadership_role($role) {
    $n = normalize_role_name($role);
    return in_array($n, ['building chairperson', 'building chairman', 'building chairlady', 'vice building chairperson', 'vice building chairman', 'vice building chairlady', 'building secretary', 'vice building secretary', 'building treasurer'], true);
}

function is_worship_leadership_role($role) {
    $n = normalize_role_name($role);
    return in_array($n, ['worship leader', 'vice worship leader'], true);
}

function is_general_church_role($role) {
    $normalized_role = normalize_role_name($role);
    return in_array($normalized_role, [
        'general church secretary',
        'vice church secretary',
        'treasurer',
        'senior church elder',
        'head usher',
        'usher',
        'building chairperson',
        'vice building chairperson',
        'building secretary',
        'vice building secretary',
        'building treasurer',
        'worship leader',
        'vice worship leader'
    ], true);
}

function is_general_church_secretary_role($role) {
    $normalized_role = normalize_role_name($role);
    return in_array($normalized_role, ['general church secretary', 'vice church secretary'], true);
}

function is_general_church_leader_role($role) {
    // Only secretary, vice secretary, treasurer, church elder get leadership chat permissions
    $normalized_role = normalize_role_name($role);
    return in_array($normalized_role, [
        'general church secretary',
        'vice church secretary',
        'treasurer',
        'senior church elder'
    ], true);
}

function is_department_secretary_role($role) {
    $normalized_role = normalize_role_name($role);
    return in_array($normalized_role, [
        'youth secretary',
        'vice youth secretary',
        'women secretary',
        'vice women secretary',
        'elder secretary',
        'vice elder secretary',
        'sunday school secretary',
        'vice sunday school secretary',
    ], true);
}

function is_youth_advisor_role($role) {
    $normalized_role = normalize_role_name($role);
    return in_array($normalized_role, ['mama youth', 'baba youth'], true);
}

function get_youth_chairperson_title($gender, $role_name = '') {
    // Returns gender-based display title for the Youth Chairperson role
    $gender_lower = strtolower(trim($gender ?? ''));
    $is_vice = str_contains(normalize_role_name($role_name), 'vice');
    
    if (in_array($gender_lower, ['female', 'f', 'woman', 'girl'], true)) {
        return $is_vice ? 'Vice Youth Chairlady' : 'Youth Chairlady';
    }
    return $is_vice ? 'Vice Youth Chairman' : 'Youth Chairman';
}

function get_building_chairperson_title($gender, $role_name = '') {
    // Returns gender-based display title for the Building Chairperson role
    $gender_lower = strtolower(trim($gender ?? ''));
    $is_vice = str_contains(normalize_role_name($role_name), 'vice');
    
    if (in_array($gender_lower, ['female', 'f', 'woman', 'girl'], true)) {
        return $is_vice ? 'Vice Building Chairlady' : 'Building Chairlady';
    }
    return $is_vice ? 'Vice Building Chairman' : 'Building Chairman';
}

function role_display_label($role, $member_department = null, $member_gender = null) {
    $normalized_role = normalize_role_name($role);
    $label = $role;

    // Gender-aware display for Youth Chairperson and Vice Youth Chairperson
    if (in_array($normalized_role, ['youth chairperson', 'vice youth chairperson'], true)) {
        if ($member_gender !== null) {
            $label = get_youth_chairperson_title($member_gender, $normalized_role);
        } else {
            // No gender passed — show the neutral label
            $label = $normalized_role === 'vice youth chairperson' ? 'Vice Youth Chairperson' : 'Youth Chairperson';
        }
    } elseif (in_array($normalized_role, ['building chairperson', 'vice building chairperson'], true)) {
        if ($member_gender !== null) {
            $label = get_building_chairperson_title($member_gender, $normalized_role);
        } else {
            $label = $normalized_role === 'vice building chairperson' ? 'Vice Building Chairperson' : 'Building Chairperson';
        }
    } elseif ($normalized_role === 'mama youth') {
        $department_label = $member_department ?: 'Women Ministry';
        if (normalize_role_name($department_label) === 'womens ministry') {
            $department_label = 'Women Ministry';
        }
        $label = 'Mama Youth (' . $department_label . ')';
    } elseif ($normalized_role === 'baba youth') {
        $label = 'Baba Youth (' . ($member_department ?: 'Elders') . ')';
    }

    if (is_subsidiary_department_role($role)) {
        $label .= ' (Subsidiary)';
    }

    return $label;
}

function role_rank_case_sql($column = 'church_role') {
    return "
        CASE LOWER(TRIM(COALESCE($column, '')))
            WHEN 'youth chairperson' THEN 1
            WHEN 'women chairlady' THEN 1
            WHEN 'elder chairman' THEN 1
            WHEN 'sunday school patron' THEN 1
            WHEN 'general church secretary' THEN 1
            WHEN 'vice youth chairperson' THEN 2
            WHEN 'vice women chairlady' THEN 2
            WHEN 'vice elder chairman' THEN 2
            WHEN 'vice sunday school patron' THEN 2
            WHEN 'youth secretary' THEN 3
            WHEN 'women secretary' THEN 3
            WHEN 'elder secretary' THEN 3
            WHEN 'sunday school secretary' THEN 3
            WHEN 'vice church secretary' THEN 4
            WHEN 'vice youth secretary' THEN 4
            WHEN 'vice women secretary' THEN 4
            WHEN 'vice elder secretary' THEN 4
            WHEN 'vice sunday school secretary' THEN 4
            WHEN 'treasurer' THEN 5
            WHEN 'youth treasurer' THEN 5
            WHEN 'women treasurer' THEN 5
            WHEN 'elder treasurer' THEN 5
            WHEN 'sunday school treasurer' THEN 5
            WHEN 'mama youth' THEN 6
            WHEN 'baba youth' THEN 6
            WHEN 'head usher' THEN 10
            WHEN 'usher' THEN 11
            WHEN 'building chairperson' THEN 12
            WHEN 'vice building chairperson' THEN 13
            WHEN 'building secretary' THEN 14
            WHEN 'vice building secretary' THEN 15
            WHEN 'building treasurer' THEN 16
            WHEN 'organizing secretary' THEN 20
            WHEN 'discipline master' THEN 21
            WHEN 'graduands secretary' THEN 22
            WHEN 'prayer coordinator' THEN 23
            WHEN 'sport secretary' THEN 24
            WHEN 'sports secretary' THEN 24
            WHEN 'choir leader' THEN 25
            WHEN 'member' THEN 90
            WHEN '' THEN 90
            ELSE 40
        END
    ";
}
?>
