<?php
$member_dashboard = file_get_contents(__DIR__ . '/../member_dashboard.php');
$pastor_dashboard = file_get_contents(__DIR__ . '/../pastor_dashboard.php');
$notification_helpers = file_get_contents(__DIR__ . '/../notification_badges.php');
if ($member_dashboard === false || $pastor_dashboard === false || $notification_helpers === false) {
    fwrite(STDERR, "FAIL: preference dashboard sources could not be read.\n");
    exit(1);
}

$required_markers = [
    'horizontal village options' => 'class="church-village-choice-row"',
    'village option keys route to exact rows' => 'member_preference_notification_row(\'village\', $village_option)',
    'church village selection is submitted' => 'name="church_village" id="churchVillageSelection"',
    'role options submit as a multi-select list' => 'name="desired_role_prefs[]"',
    'no-role option is explicit and mutually exclusive' => 'name="desired_role_none" value="1" data-desired-role-none',
    'selected roles are persisted as JSON' => 'desired_role_prefs_json = \'$roles_json\'',
    'legacy primary role is retained for existing role checks' => 'desired_role_pref = $drp_sql',
    'pending selected roles are stored separately' => 'pending_desired_role_prefs_json = \'$roles_json\'',
    'role exits are calculated from the selected set' => '$removed_roles = array_values(array_filter($current_roles',
    'each role exit reason is required server-side' => 'Please provide a reason for leaving each role you selected.',
    'individual role reasons are paired by role name' => 'name = \'role_exit_names[]\'',
    'role reason textareas have independent posted values' => 'name = \'role_exit_reasons[]\'',
    'exit reasons are clearly labeled per role' => 'Reason for leaving \' + role',
    'saved reasons preserve the role-to-reason association' => '\'Leaving \' . $removed_role . \': \' . $exit_reason',
    'member cannot open another request while pending' => 'if (form.dataset.hasPendingRequest === \'true\')',
    'separate village action remains' => 'name="preference_action" value="change_village" class="btn-primary"',
    'separate role action remains' => 'name="preference_action" value="change_role" class="btn-primary"',
    'village changes require an explanation' => '$requires_preference_reason && $preference_reason === \'\'',
    'worshipper additions still require their own explanation' => '$village_preference_changed || $worshipper_added',
    'reason is retained for Pastor review' => 'pending_preference_reason = $preference_reason_sql',
    'pending reason schema remains available' => "ALTER TABLE members ADD COLUMN pending_preference_reason TEXT NULL AFTER pending_desired_role_pref",
    'role options have exact focus keys' => 'member_preference_notification_row(\'role\', $role_option)',
    'role choices are checked inputs' => 'data-desired-role-checkbox',
    'form behavior initializes after its role controls render' => "document.addEventListener('DOMContentLoaded', function () {",
    'no-role selection clears all role checkboxes' => 'if (noneSelected) roleChoices.forEach(function (input) { input.checked = false; });',
    'removed roles get gold-focus-compatible row elements' => 'data-notification-row="<?= htmlspecialchars(member_preference_notification_row(\'role\', $role_option)',
    'the role list is compared as an unordered set' => 'member_preference_role_lists_equal($requested_roles, $current_roles)',
    'role changes route to the Pastor pending row' => "notification_with_route(\$update_msg, 'desired_roles', '', 'member-village-update-' . \$member_id)",
    'pending request message lists requested roles' => "implode(', ', \$pending_role_values)",
    'selection changes render individual reason fields' => 'renderExitReasons(removedRoles)',
    'reason box stays before Change Village' => 'id="preferenceReasonWrap" hidden',
    'village options retain their selected gold glow' => '.church-village-choice[aria-pressed="true"]',
    'role options retain selected gold glow' => '.desired-role-choice.is-selected',
];
foreach ($required_markers as $label => $marker) {
    if (strpos($member_dashboard, $marker) === false) {
        fwrite(STDERR, "FAIL: $label is missing from the member Church Village form.\n");
        exit(1);
    }
}

if (strpos($notification_helpers, 'function member_preference_roles_from_storage(') === false
    || strpos($notification_helpers, 'function member_preference_roles_normalize(') === false
    || strpos($notification_helpers, 'function member_preference_role_lists_equal(') === false
    || strpos($notification_helpers, "desired_role_prefs_json' => 'ALTER TABLE members ADD COLUMN") === false
    || strpos($notification_helpers, "pending_desired_role_prefs_json' => 'ALTER TABLE members ADD COLUMN") === false) {
    fwrite(STDERR, "FAIL: multi-role storage helpers and schema migration are required.\n");
    exit(1);
}

foreach ([
    'admin_dashboard.php',
    'pastor_dashboard.php',
    'print_volunteers.php',
    'print_all_villages.php',
] as $role_report_file) {
    $role_report_source = file_get_contents(__DIR__ . '/../' . $role_report_file);
    if ($role_report_source === false || strpos($role_report_source, 'JSON_CONTAINS(desired_role_prefs_json') === false) {
        fwrite(STDERR, "FAIL: $role_report_file must include members who selected a role as one of several roles.\n");
        exit(1);
    }
}

if (strpos($pastor_dashboard, 'member_preference_roles_from_storage($mem[\'pending_desired_role_prefs_json\']') === false
    || strpos($pastor_dashboard, 'desired_role_prefs_json = \'$new_roles_json\'') === false
    || strpos($pastor_dashboard, 'pending_desired_role_prefs_json = NULL') === false
    || strpos($pastor_dashboard, '$pending_role_changed = !member_preference_role_lists_equal($pu_pending_roles, $pu_current_roles);') === false
    || strpos($pastor_dashboard, 'Desired roles &rarr;') === false
    || strpos($pastor_dashboard, 'data-preference-reason-for-request="<?= (int)$pu[\'id\'] ?>"') === false
    || strpos($pastor_dashboard, 'Decline &amp; Notify') === false
    || strpos($pastor_dashboard, 'declineButton.type = \'submit\'') === false
    || strpos($pastor_dashboard, 'data-cancel-decline') !== false
    || strpos($pastor_dashboard, 'Enter a reason of 1,000 characters or fewer before declining this request.') !== false) {
    fwrite(STDERR, "FAIL: Pastor must review, approve, decline, and display the full role request correctly.\n");
    exit(1);
}

$reason_box_position = strpos($member_dashboard, 'id="preferenceReasonWrap"');
$change_village_button_position = strpos($member_dashboard, 'value="change_village" class="btn-primary"');
if ($reason_box_position === false || $change_village_button_position === false || $reason_box_position > $change_village_button_position) {
    fwrite(STDERR, "FAIL: the preference reason box must appear before the Change Village button.\n");
    exit(1);
}
if (strpos($member_dashboard, '<select name="church_village"') !== false
    || strpos($member_dashboard, '<select name="desired_role_pref"') !== false
    || strpos($member_dashboard, '?3 Your request to update your church village') !== false) {
    fwrite(STDERR, "FAIL: legacy preference dropdowns or malformed pending text must not return.\n");
    exit(1);
}

echo "Member village and multi-role preference tests passed.\n";
