<?php
function normalize_role_name($role) {
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $role ?? '')));
    $normalized = preg_replace('/\(.*?\)/', '', $normalized);
    return trim($normalized);
}

// Sunday School main leadership roles are cross-church â€” any member from any
// department can hold them. They must NOT auto-set the member's home department.
function is_sunday_school_leadership_role($role) {
    $n = normalize_role_name($role);
    return in_array($n, [
        'sunday school patron', 'vice sunday school patron',
        'sunday school chairperson', 'vice sunday school chairperson',
        'sunday school chairman', 'vice sunday school chairman',
        'sunday school chairlady', 'vice sunday school chairlady',
        'sunday school secretary', 'vice sunday school secretary',
        'sunday school treasurer',
    ], true);
}

function department_for_role($role) {
    // Sunday School roles are cross-church â€” they don't own a single department
    // so we deliberately exclude them from this map to prevent the member's
    // registered (home) department from being silently overwritten.
    if (is_sunday_school_leadership_role($role)) {
        return null;
    }

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
        'women chairperson'       => 'Womens Ministry',
        'women chairman'          => 'Womens Ministry',
        'vice women chairlady'    => 'Womens Ministry',
        'vice women chairperson'  => 'Womens Ministry',
        'vice women chairman'     => 'Womens Ministry',
        'women secretary'         => 'Womens Ministry',
        'vice women secretary'    => 'Womens Ministry',
        'women treasurer'         => 'Womens Ministry',

        'elder chairman'          => 'Elders',
        'elder chairperson'       => 'Elders',
        'elder chairlady'         => 'Elders',
        'vice elder chairman'     => 'Elders',
        'vice elder chairperson'  => 'Elders',
        'vice elder chairlady'    => 'Elders',
        'elder secretary'         => 'Elders',
        'vice elder secretary'    => 'Elders',
        'elder treasurer'         => 'Elders',
        
        // Building Ministry
        'building chairperson'       => 'Building',
        'building chairman'          => 'Building',
        'building chairlady'         => 'Building',
        'vice building chairperson'  => 'Building',
        'vice building chairman'     => 'Building',
        'vice building chairlady'    => 'Building',
        'building secretary'         => 'Building',
        'vice building secretary'    => 'Building',
        'building treasurer'         => 'Building',
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
    if (in_array($normalized_role, ['graduands secretary', 'vice graduands secretary', 'sport secretary', 'sports secretary', 'vice sport secretary', 'vice sports secretary'], true)) {
        return department_allows_graduation_and_sport($department);
    }

    return true;
}

function role_assignment_departments($role, $context_department = null) {
    $normalized_role = normalize_role_name($role);

    // Sunday School subsidiary roles (Discipline Master, Choir Leader, etc. assigned
    // under Sunday School context) are open to ALL church members â€” any dept.
    if (is_subsidiary_department_role($role) && !empty($context_department) && strtolower(trim($context_department)) === 'sunday school') {
        return []; // No restriction â€” any church member can serve as a Sunday School subsidiary leader
    }

    // Subsidiary roles in other departments (Youth, Women, Elders) are restricted
    // to members of that specific department.
    if (is_subsidiary_department_role($role) && !empty($context_department)) {
        return [$context_department];
    }

    // All Sunday School main leadership roles are open to ALL church members.
    if (!empty($context_department) && strtolower(trim($context_department)) === 'sunday school') {
        return [];
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

    if (is_building_leadership_role($role)) {
        // Building roles open to all departments EXCEPT Sunday School
        return ['General Church', 'Youths', 'Womens Ministry', 'Elders', 'Building'];
    }

    $department = department_for_role($role);

    // Sunday School main leadership roles are also open to all general church members
    $ss_leadership_roles = [
        'sunday school patron', 'vice sunday school patron',
        'sunday school chairperson', 'vice sunday school chairperson',
        'sunday school chairman', 'vice sunday school chairman',
        'sunday school chairlady', 'vice sunday school chairlady',
        'sunday school secretary', 'vice sunday school secretary',
        'sunday school treasurer'
    ];
    if (in_array($normalized_role, $ss_leadership_roles, true)) {
        return []; // No department restriction â€” any church member can be appointed
    }

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

function get_best_role_from_string($church_role_str) {
    if (empty($church_role_str)) return 'Member';
    $roles = array_map('trim', explode(',', $church_role_str));
    $best_role = 'Leader';
    $best_rank = 999;
    foreach ($roles as $r) {
        if ($r === '') continue;
        $l = strtolower($r);
        $rank = 50;
        
        if (preg_match('/^(youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)$/', $l)) $rank = 1;
        elseif (preg_match('/^vice (youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)$/', $l)) $rank = 2;
        elseif (preg_match('/^(general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary$/', $l)) $rank = 3;
        elseif (preg_match('/^vice (general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary$/', $l)) $rank = 4;
        elseif (preg_match('/^(youth |women |elder |sunday school |building )?treasurer$/', $l)) $rank = 5;
        elseif (preg_match('/^vice (youth |women |elder |sunday school |building )?treasurer$/', $l)) $rank = 6;
        elseif (preg_match('/^(mama youth|baba youth)$/', $l)) $rank = 7;
        elseif (preg_match('/^head usher$/', $l)) $rank = 10;
        elseif (preg_match('/^usher$/', $l)) $rank = 11;
        elseif (preg_match('/^discipline master$/', $l)) $rank = 21;
        elseif (preg_match('/^prayer coordinator$/', $l)) $rank = 23;
        elseif (preg_match('/^choir leader$/', $l)) $rank = 25;
        elseif ($l === 'member') $rank = 90;
        
        if ($rank < $best_rank) {
            $best_rank = $rank;
            $best_role = $r;
        }
    }
    return $best_role;
}

function role_rank_case_sql($column = 'church_role') {
    return "
        CASE 
            WHEN LOWER($column) REGEXP '(^|, *)(youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)( *,|$)' THEN 1
            WHEN LOWER($column) REGEXP '(^|, *)vice (youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)( *,|$)' THEN 2
            WHEN LOWER($column) REGEXP '(^|, *)(general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary( *,|$)' THEN 3
            WHEN LOWER($column) REGEXP '(^|, *)vice (general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary( *,|$)' THEN 4
            WHEN LOWER($column) REGEXP '(^|, *)(youth |women |elder |sunday school |building )?treasurer( *,|$)' THEN 5
            WHEN LOWER($column) REGEXP '(^|, *)vice (youth |women |elder |sunday school |building )?treasurer( *,|$)' THEN 6
            WHEN LOWER($column) REGEXP '(^|, *)(mama youth|baba youth)( *,|$)' THEN 7
            WHEN LOWER($column) REGEXP '(^|, *)head usher( *,|$)' THEN 10
            WHEN LOWER($column) REGEXP '(^|, *)usher( *,|$)' THEN 11
            WHEN LOWER($column) REGEXP '(^|, *)discipline master( *,|$)' THEN 21
            WHEN LOWER($column) REGEXP '(^|, *)prayer coordinator( *,|$)' THEN 23
            WHEN LOWER($column) REGEXP '(^|, *)choir leader( *,|$)' THEN 25
            WHEN LOWER(TRIM(COALESCE($column, ''))) = 'member' THEN 90
            WHEN TRIM(COALESCE($column, '')) = '' THEN 90
            ELSE 40
        END
    ";
}

?>

