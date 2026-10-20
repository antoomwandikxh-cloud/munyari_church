<?php
$member_dashboard = file_get_contents(__DIR__ . '/../member_dashboard.php');
$pastor_dashboard = file_get_contents(__DIR__ . '/../pastor_dashboard.php');
if ($member_dashboard === false || $pastor_dashboard === false) {
    fwrite(STDERR, "FAIL: member or Pastor dashboard source could not be read.\n");
    exit(1);
}

$required_markers = [
    'horizontal village options' => 'class="church-village-choice-row"',
    'village option buttons' => 'data-village-option=',
    'existing POST field name retained' => 'name="church_village" id="churchVillageSelection"',
    'existing members are marked for update confirmation' => 'data-has-saved-village="<?= $has_church_village ? \'true\' : \'false\' ?>"',
    'either pending preference is identified by the form' => 'data-has-pending-request="<?= (!empty($member[\'pending_church_village\']) || !empty($member[\'pending_desired_role_pref\'])) ? \'true\' : \'false\' ?>"',
    'member is blocked from submitting another pending request' => 'if (form.dataset.hasPendingRequest === \'true\')',
    'pending request explains wait for Pastor decision' => 'Please wait until the Pastor approves or rejects it before making another change.',
    'server checks pending role preference too' => 'trim((string)($member[\'pending_desired_role_pref\'] ?? \'\')) !== \'\';',
    'first-time setup has one save action' => 'name="preference_action" value="initial_setup" class="btn-primary" style="padding:12px 24px;">Save Preferences',
    'village can be changed independently' => 'name="preference_action" value="change_village" class="btn-primary" style="margin-top:10px;padding:9px 16px;">Change Village',
    'role can be changed independently' => 'name="preference_action" value="change_role" class="btn-primary" style="margin-top:10px;padding:9px 16px;">Change Role',
    'same village warns member to select a different village' => "You cannot choose the village you picked earlier (' + savedVillage + '). Go back and choose a different one.",
    'same role is rejected with the requested message' => 'You cannot choose the same role you picked earlier. Go back and choose a different one.',
    'village change confirmation names only the selected village' => "confirmMessage = 'You selected ' + villageInput.value + ' village. Send this church village change request to the Pastor for approval?'",
    'village request message omits the unchanged role' => '"requested to change their church village to $cv"',
    'role change asks Pastor approval' => 'Send this role change request to the Pastor for approval?',
    'reason field appears when leaving Worshipper' => 'id="worshipperExitReasonWrap" hidden',
    'reason is submitted with a Worshipper role change' => 'name="preference_reason"',
    'Worshipper exit requires a reason in the browser' => 'Please explain why you want to leave the Worshipper role before sending your request.',
    'Worshipper exit reason is enforced server side' => '$requires_worshipper_exit_reason && $preference_reason === \'\'',
    'request reason is saved for Pastor review' => 'pending_preference_reason = $preference_reason_sql',
    'pending reason column is created when needed' => "ALTER TABLE members ADD COLUMN pending_preference_reason TEXT NULL AFTER pending_desired_role_pref",
    'village options have exact preference row keys' => "member_preference_notification_row('village', \$village_option)",
    'role options have exact preference row keys' => "member_preference_notification_row('role', \$role_option)",
    'village request preserves the saved role' => '$requested_role = $current_role;',
    'role request preserves the saved village' => '$requested_village = $current_village;',
    'unchanged preferences are blocked server side' => 'if ($unchanged_message !== \'\')',
    'pending message distinguishes changed village from role' => '$pending_request_message = $pending_changes',
    'pending village is compared with saved village' => 'strcasecmp($pending_village_value, $saved_village_value)',
    'pending role is compared with saved role' => 'strcasecmp($pending_role_value, $saved_role_value)',
    'pending banner uses readable theme-aware status styling' => 'role="status" style="display:flex;align-items:flex-start;',
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
if (strpos($pastor_dashboard, '$pending_village_changed = strcasecmp(trim((string)($pu[\'pending_church_village\'] ?? \'\')), trim((string)($pu[\'church_village\'] ?? \'\'))) !== 0;') === false
    || strpos($pastor_dashboard, '$pending_role_changed = strcasecmp(trim((string)($pu[\'pending_desired_role_pref\'] ?? \'\')), trim((string)($pu[\'desired_role_pref\'] ?? \'\'))) !== 0;') === false
    || strpos($pastor_dashboard, '<?php if ($pending_role_changed): ?>') === false
    || strpos($pastor_dashboard, 'Reason for declining') === false
    || strpos($pastor_dashboard, 'Enter a reason of 1,000 characters or fewer before declining this request.') === false
    || strpos($pastor_dashboard, "pending_preference_reason'] ??") === false
    || strpos($pastor_dashboard, "'. Reason: ' . \$decline_reason") === false) {
    fwrite(STDERR, "FAIL: Pastor pending update list must only show the role when it changed.\n");
    exit(1);
}
if (strpos($member_dashboard, '<select name="church_village"') !== false) {
    fwrite(STDERR, "FAIL: the member Church Village form should not render the old village dropdown.\n");
    exit(1);
}
if (strpos($member_dashboard, '?3 Your request to update your church village') !== false) {
    fwrite(STDERR, "FAIL: the pending preference banner must not contain the stray ?3 text.\n");
    exit(1);
}
if (strpos($member_dashboard, '<select name="desired_role_pref"') !== false) {
    fwrite(STDERR, "FAIL: the member Desired Role form should not render the old role dropdown.\n");
    exit(1);
}

echo "Member village selector tests passed.\n";
