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
    'existing members are marked for update confirmation' => 'data-has-saved-village="<?= $has_church_village ? \'true\' : \'false\' ?>"',
    'update confirmation names selected village and role' => "You have already chosen ' + villageInput.value + ' village and ' + roleLabel + ' role.",
    'canceling update confirmation prevents submission' => 'if (!window.confirm(confirmMessage)) event.preventDefault();',
    'selected village reflected visually' => '.church-village-choice[aria-pressed="true"]',
    'village cards have individual accent colors' => '--choice-accent:<?= htmlspecialchars($choice_palette[\'accent\']',
    'village cards have tinted backgrounds' => '--choice-tint:<?= htmlspecialchars($choice_palette[\'tint\']',
    'village hover uses its accent color' => 'border-color:var(--choice-accent)',
    'selected options keep a continuous gold glow' => 'animation:member-choice-selected-glow 1.8s ease-in-out infinite',
    'gold glow remains distinct from card accent colors' => 'outline:3px solid #facc15;outline-offset:2px',
    'gold glow has a reduced-motion fallback' => '.church-village-choice[aria-pressed="true"],.desired-role-choice[aria-pressed="true"]{animation:none;outline:3px solid #facc15',
    'glow has room around horizontally scrolling cards' => 'overflow-x:auto;padding:8px 8px 14px',
    'empty selection prevented on submit' => 'Select a church village before saving.',
    'village selection updates button state' => "choice.setAttribute('aria-pressed', selected ? 'true' : 'false')",
    'horizontal desired role options' => 'class="desired-role-choice-row"',
    'desired role buttons' => 'data-desired-role-option=',
    'existing desired role POST field retained' => 'name="desired_role_pref" id="desiredRoleSelection"',
    'desired role hover feedback' => '.desired-role-choice:hover',
    'desired role cards use a multi-color palette' => "['accent' => '#0f766e'",
    'desired role cards have tinted backgrounds' => 'background:var(--choice-tint)',
    'selected cards retain readable contrast' => 'background:var(--choice-accent);color:#fff',
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
