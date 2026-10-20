<?php
$member_dashboard = file_get_contents(__DIR__ . '/../member_dashboard.php');
if ($member_dashboard === false) {
    fwrite(STDERR, "FAIL: member dashboard source could not be read.\n");
    exit(1);
}

$required_markers = [
    'horizontal village options' => 'class="church-village-choice-row"',
    'village option buttons' => 'data-village-option=',
    'existing POST field name retained' => 'name="church_village" id="churchVillageSelection"',
    'selected village reflected visually' => '.church-village-choice[aria-pressed="true"]',
    'empty selection prevented on submit' => 'Select a church village before saving.',
    'village selection updates button state' => "choice.setAttribute('aria-pressed', selected ? 'true' : 'false')",
    'horizontal desired role options' => 'class="desired-role-choice-row"',
    'desired role buttons' => 'data-desired-role-option=',
    'existing desired role POST field retained' => 'name="desired_role_pref" id="desiredRoleSelection"',
    'desired role hover feedback' => '.desired-role-choice:hover',
    'desired role selection updates submitted value' => 'roleInput.value = value',
];
foreach ($required_markers as $label => $marker) {
    if (strpos($member_dashboard, $marker) === false) {
        fwrite(STDERR, "FAIL: $label is missing from the member Church Village form.\n");
        exit(1);
    }
}
if (strpos($member_dashboard, '<select name="church_village"') !== false) {
    fwrite(STDERR, "FAIL: the member Church Village form should not render the old village dropdown.\n");
    exit(1);
}
if (strpos($member_dashboard, '<select name="desired_role_pref"') !== false) {
    fwrite(STDERR, "FAIL: the member Desired Role form should not render the old role dropdown.\n");
    exit(1);
}

echo "Member village selector tests passed.\n";
