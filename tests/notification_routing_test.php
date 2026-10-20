<?php
require_once __DIR__ . '/../notification_badges.php';

function expect_same($expected, $actual, $label) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $label\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$routed_message = notification_with_route(
    'A new member was approved.',
    'manage_department',
    'womens_ministry',
    'department-member-42'
);

expect_same(['manage_department'], notification_badge_tabs($routed_message, 'member'), 'explicit tab controls the badge destination');
expect_same('manage_department', notification_target_tab($routed_message, 'member'), 'click routing uses the explicit tab');
expect_same('womens_ministry', notification_target_group($routed_message, 'member'), 'click routing preserves the department group');
expect_same('department-member-42', notification_route_metadata($routed_message)['row'], 'exact row focus survives routing');
expect_same('A new member was approved.', notification_display_message($routed_message, 'member'), 'routing metadata is hidden from the visible message');
$village_update_notification = notification_with_route(
    'Member Rose requested to change their church village to Philadelphia and desired role to Worshipper.',
    'desired_roles',
    '',
    'member-village-update-42'
);
expect_same(['desired_roles'], notification_badge_tabs($village_update_notification, 'pastor'), 'village preference requests badge the Pastor Desired Roles tab');
expect_same('desired_roles', notification_target_tab($village_update_notification, 'pastor'), 'village preference request opens Pastor Desired Roles');
expect_same('member-village-update-42', notification_route_metadata($village_update_notification)['row'], 'village preference request targets the exact pending member row');
expect_same(
    'Member Rose requested to change their church village to Philadelphia and desired role to Worshipper.',
    notification_display_message($village_update_notification, 'pastor'),
    'village preference route metadata stays hidden from the Pastor notification text'
);
$village_update_glow = render_notification_tab_glow_script('desired_roles', 'desired_roles', $village_update_notification);
if (strpos($village_update_glow, 'notification-row:member-village-update-42') === false
    || strpos($village_update_glow, 'notification-row-glow') === false) {
    fwrite(STDERR, "FAIL: village preference request opens and highlights the exact pending member row.\n");
    exit(1);
}
$member_dashboard_source = file_get_contents(__DIR__ . '/../member_dashboard.php');
$pastor_dashboard_source = file_get_contents(__DIR__ . '/../pastor_dashboard.php');
if ($member_dashboard_source === false
    || strpos($member_dashboard_source, 'notification_with_route($update_msg, \'desired_roles\', \'\', \'member-village-update-\' . $member_id)') === false
    || strpos($member_dashboard_source, 'form.dataset.hasSavedVillage === \'true\'') === false
    || $pastor_dashboard_source === false
    || strpos($pastor_dashboard_source, 'data-notification-row="member-village-update-<?= (int)$pu[\'id\'] ?>"') === false) {
    fwrite(STDERR, "FAIL: village update requests must confirm before sending and route to the exact Pastor pending-update row.\n");
    exit(1);
}
$worship_announcement = notification_with_route(
    'Worship announcement posted by Amina Kageni for worshippers.',
    'manage_worshippers',
    '',
    'worship-announcement-73'
);
expect_same(['manage_worshippers'], notification_badge_tabs($worship_announcement, 'pastor'), 'worship announcements badge the Pastor Manage Worshippers tab');
expect_same('manage_worshippers', notification_target_tab($worship_announcement, 'pastor'), 'worship announcement opens Pastor Manage Worshippers');
expect_same('worship-announcement-73', notification_route_metadata($worship_announcement)['row'], 'worship announcement routing identifies its exact post');
expect_same('Worship announcement posted by Amina Kageni for worshippers.', notification_display_message($worship_announcement, 'pastor'), 'worship announcement route metadata stays hidden from the visible notification');
$worshipper_announcement = notification_with_route(
    'Worship announcement posted by Amina Kageni for worshippers.',
    'my_worship_tasks',
    '',
    'worship-announcement-73'
);
expect_same('my_worship_tasks', notification_target_tab($worshipper_announcement, 'member'), 'worshipper announcement opens My Tasks');
$worship_query_secretary = notification_with_route(
    'Worship announcement query from Amina Kageni in Worship Ministry.',
    'query_announcements',
    '',
    'announcement-query-73'
);
expect_same(['query_announcements'], notification_badge_tabs($worship_query_secretary, 'member'), 'worship query badges the General Secretary query tab');
expect_same('announcement-query-73', notification_route_metadata($worship_query_secretary)['row'], 'secretary query notification targets the exact received query');
$worship_query_oversight = notification_with_route(
    'Worship announcement query from Amina Kageni in Worship Ministry.',
    'general_announcements',
    'announcement_queries',
    'worship-query-73'
);
expect_same(['general_announcements'], notification_badge_tabs($worship_query_oversight, 'pastor'), 'worship query badges Pastor General Announcements');
expect_same(['general_announcements'], notification_badge_tabs($worship_query_oversight, 'admin'), 'worship query badges Admin General Announcements');
expect_same(
    'Worship announcement query from Amina Kageni in Worship Ministry.',
    notification_display_message($worship_query_oversight, 'pastor'),
    'worship query route metadata stays out of the visible notification text'
);
expect_same('announcement_queries', notification_target_group($worship_query_oversight, 'pastor'), 'worship query keeps the queried-announcement filter group');
expect_same('worship-query-73', notification_route_metadata($worship_query_oversight)['row'], 'oversight query alert targets the exact query row');
$worship_query_glow = render_notification_tab_glow_script('general_announcements', 'general_announcements', $worship_query_oversight);
if (strpos($worship_query_glow, 'notification-row:worship-query-73') === false
    || strpos($worship_query_glow, 'notification-row-glow') === false) {
    fwrite(STDERR, "FAIL: worship query notification reaches the exact queried-announcement glow row.\n");
    exit(1);
}
$worship_query_member_glow = render_notification_tab_glow_script('query_announcements', 'query_announcements', $worship_query_secretary);
if (strpos($worship_query_member_glow, 'notification-row:announcement-query-73') === false
    || strpos($worship_query_member_glow, 'notification-row-glow') === false) {
    fwrite(STDERR, "FAIL: Secretary worship query notification reaches the exact received query row.\n");
    exit(1);
}
foreach (['pastor_dashboard.php', 'admin_dashboard.php'] as $oversight_dashboard) {
    $oversight_source = file_get_contents(__DIR__ . '/../' . $oversight_dashboard);
    if ($oversight_source === false
        || strpos($oversight_source, 'data-notification-row="worship-query-') === false
        || strpos($oversight_source, "=== 'announcement_queries'") === false) {
        fwrite(STDERR, "FAIL: $oversight_dashboard must reveal and render exact worship announcement queries.\n");
        exit(1);
    }
}
$worship_query_member_source = file_get_contents(__DIR__ . '/../member_dashboard.php');
if ($worship_query_member_source === false
    || strpos($worship_query_member_source, 'notification_with_route($notice_text, \'query_announcements\'') === false
    || strpos($worship_query_member_source, 'notification_with_route($notice_text, \'general_announcements\', \'announcement_queries\'') === false
    || strpos($worship_query_member_source, 'data-notification-row="announcement-query-') === false) {
    fwrite(STDERR, "FAIL: worship announcement queries must route Secretary and oversight notifications to the exact rendered query.\n");
    exit(1);
}
$worship_glow_script = render_notification_tab_glow_script('manage_worshippers', 'manage_worshippers', $worship_announcement);
if (strpos($worship_glow_script, 'notification-row:worship-announcement-73') === false
    || strpos($worship_glow_script, 'notification-row-glow') === false
    || strpos($worship_glow_script, 'data-notification-row-panel') === false
    || strpos($worship_glow_script, 'rowPanel.hidden = false') === false) {
    fwrite(STDERR, "FAIL: worship announcement notification reaches the exact gold-glow row handler.\n");
    exit(1);
}
foreach (['pastor_dashboard.php', 'admin_dashboard.php', 'member_dashboard.php'] as $worship_dashboard) {
    $worship_dashboard_source = file_get_contents(__DIR__ . '/../' . $worship_dashboard);
    if ($worship_dashboard_source === false
        || strpos($worship_dashboard_source, 'data-notification-row="worship-announcement-') === false) {
        fwrite(STDERR, "FAIL: $worship_dashboard must render the exact worship-announcement focus row.\n");
        exit(1);
    }
}
foreach ([
    'pastor_dashboard.php' => 'pastorWorshipAnnouncementPanel',
    'admin_dashboard.php' => 'adminWorshipAnnouncementPanel',
] as $dashboard_file => $announcement_panel_id) {
    $dashboard_source = file_get_contents(__DIR__ . '/../' . $dashboard_file);
    if ($dashboard_source === false
        || strpos($dashboard_source, 'id="' . $announcement_panel_id . '" data-notification-row-panel') === false
        || strpos($dashboard_source, "aria-controls=\"" . $announcement_panel_id . "\"") === false
        || strpos($dashboard_source, 'SELECT COUNT(*) AS c FROM worship_announcements') === false
        || strpos($dashboard_source, 'Show Worship Announcements (<?= $worship_announcement_count ?>)') === false
        || strpos($dashboard_source, 'toggleWorshipAnnouncementPanel(' . "'" . $announcement_panel_id . "'") === false) {
        fwrite(STDERR, "FAIL: $dashboard_file must provide a collapsible, counted worship-announcement panel that can be opened by notification focus.\n");
        exit(1);
    }
}
$worship_member_source = file_get_contents(__DIR__ . '/../member_dashboard.php');
if ($worship_member_source === false
    || strpos($worship_member_source, 'notify_worship_announcement_audience($conn, $announcement_id, $notice, $member_id)') === false
    || strpos($worship_member_source, "['pastor' => 'pastors', 'admin' => 'admins']") === false
    || strpos($worship_member_source, 'notification_with_route($notice_text, $target_tab') === false) {
    fwrite(STDERR, "FAIL: worship announcements must send explicitly routed notifications to church oversight.\n");
    exit(1);
}
foreach ([
    'worship announcement audio column migration' => '$worship_announcement_audio_col',
    'worship voice recorder' => "render_voice_note_recorder('approved worshippers')",
    'worship announcement editor' => 'beginWorshipAnnouncementEdit(this)',
    'shared leader edit permission' => 'isset($_POST[\'edit_worship_announcement\']) && ($is_worship_leader || $is_vice_worship_leader)',
    'shared leader delete permission' => 'isset($_POST[\'delete_worship_announcement\']) && ($is_worship_leader || $is_vice_worship_leader)',
    'worship leader management panel is shared by leader and vice' => "elseif (\$tab == 'manage_worshippers' && (\$is_worship_leader || \$is_vice_worship_leader))",
    'announcement delete clears exact stale notification routes' => "'[notification-row:worship-announcement-' . \$announcement_id . ']'",
    'announcement delete removes attached media' => 'remove_worship_announcement_media($existing[\'audio_path\'] ?? null)',
    'announcement delete button' => 'name="delete_worship_announcement"',
    'announcement delete button label' => 'Delete Announcement',
    'announcement update notifications use the route helper' => 'notification_with_route($notice_text, $target_tab',
    'voice-only announcements are accepted' => '($ann_msg !== \'\' || $audio_path)',
] as $worship_feature => $worship_source_marker) {
    if (strpos($worship_member_source, $worship_source_marker) === false) {
        fwrite(STDERR, "FAIL: $worship_feature must be supported in the worship announcement flow.\n");
        exit(1);
    }
}
foreach (['pastor_dashboard.php', 'admin_dashboard.php', 'member_dashboard.php'] as $audio_dashboard) {
    $audio_dashboard_source = file_get_contents(__DIR__ . '/../' . $audio_dashboard);
    if ($audio_dashboard_source === false || strpos($audio_dashboard_source, 'render_whatsapp_voice_player($announcement[\'audio_path\']') === false) {
        fwrite(STDERR, "FAIL: $audio_dashboard must render posted worship voice announcements.\n");
        exit(1);
    }
}
$event_notification = notification_with_route(
    'New department announcement from Organising Secretary Amina Kageni: Weekly Meeting',
    'organized_events',
    notification_department_group_key('Youths'),
    'organized-event-73'
);
expect_same('organized_events', notification_target_tab($event_notification, 'pastor'), 'organizing secretary announcements route pastor to Organised Events');
expect_same('youths', notification_target_group($event_notification, 'pastor'), 'pastor event notice keeps its department ribbon filter');
expect_same('organized-event-73', notification_route_metadata($event_notification)['row'], 'organizing secretary announcement focuses its exact event row');
expect_same('New department announcement from Organising Secretary Amina Kageni: Weekly Meeting', notification_display_message($event_notification, 'pastor'), 'organizing secretary route metadata stays hidden from pastor message');
$stale_event_notification = notification_with_route(
    'New department announcement from Organising Secretary Amina Kageni: Weekly Meeting',
    'general_announcements',
    'youths',
    'organized-event-73'
);
expect_same(['organized_events'], notification_badge_tabs($stale_event_notification, 'pastor'), 'older pastor event notices move their unread badge to Organised Events');
expect_same('organized_events', notification_target_tab($stale_event_notification, 'pastor'), 'stale announcement-tab metadata cannot override pastor event routing');
expect_same('youths', notification_target_group($stale_event_notification, 'pastor'), 'older pastor event notices retain their department ribbon filter');
$admin_event_notification = notification_with_route(
    'New department announcement from Organising Secretary Amina Kageni: Weekly Meeting',
    'general_announcements',
    notification_department_group_key('Youths'),
    'organized-event-73'
);
expect_same('general_announcements', notification_target_tab($admin_event_notification, 'admin'), 'admin event notice remains in department General Announcements');
expect_same('youths', notification_target_group($admin_event_notification, 'admin'), 'admin event notice selects its department filter');
expect_same('womens_ministry', notification_department_group_key('Womens Ministry'), 'department filter mapping matches Women ministry');
expect_same('elders', notification_department_group_key('Elders'), 'department filter mapping matches Elders');
expect_same('other', notification_department_group_key('Unknown Department'), 'unknown departments use the catch-all announcement filter');
$event_glow_script = render_notification_tab_glow_script('organized_events', 'organized_events', $event_notification);
if (strpos($event_glow_script, 'notification-row:organized-event-73') === false
    || strpos($event_glow_script, 'notification-row-glow') === false) {
    fwrite(STDERR, "FAIL: organizing secretary event notification reaches the exact announcement row glow handler.\n");
    exit(1);
}
foreach (['pastor_dashboard.php', 'admin_dashboard.php'] as $announcement_dashboard) {
    $dashboard_source = file_get_contents(__DIR__ . '/../' . $announcement_dashboard);
    if ($dashboard_source === false || strpos($dashboard_source, 'data-notification-row="organized-event-') === false) {
        fwrite(STDERR, "FAIL: $announcement_dashboard must render organized-event notification row keys.\n");
        exit(1);
    }
}
$pastor_dashboard_source = file_get_contents(__DIR__ . '/../pastor_dashboard.php');
foreach (['youths', 'womens_ministry', 'elders', 'sunday_school'] as $event_department_filter) {
    if ($pastor_dashboard_source === false
        || strpos($pastor_dashboard_source, "'" . $event_department_filter . "' => ['label'") === false) {
        fwrite(STDERR, "FAIL: pastor Organised Events must provide the $event_department_filter department ribbon.\n");
        exit(1);
    }
}
if (strpos($pastor_dashboard_source, 'data-organized-event-filter="<?= htmlspecialchars($filter_key, ENT_QUOTES) ?>"') === false
    || strpos($pastor_dashboard_source, 'data-organized-event-department=') === false
    || strpos($pastor_dashboard_source, 'notification-group:([a-z0-9_-]+)') === false
    || strpos($pastor_dashboard_source, 'const nextFilter = wasSelected ? \'\' : selected;') === false
    || strpos($pastor_dashboard_source, 'nextFilter !== \'\' && row.dataset.organizedEventDepartment === nextFilter') === false
    || strpos($pastor_dashboard_source, 'organized-event-filter-empty') === false
    || strpos($pastor_dashboard_source, 'No events posted yet in \' + label + \'.') === false
    || strpos($pastor_dashboard_source, 'data-organized-event-label=') === false) {
    fwrite(STDERR, "FAIL: pastor Organised Events must filter, toggle closed, and auto-select the department from a routed notification.\n");
    exit(1);
}
foreach (['pastor_dashboard.php', 'admin_dashboard.php'] as $discipline_dashboard) {
    $discipline_dashboard_source = file_get_contents(__DIR__ . '/../' . $discipline_dashboard);
    foreach (['youths', 'womens_ministry', 'elders', 'sunday_school'] as $discipline_department_filter) {
        if ($discipline_dashboard_source === false
            || strpos($discipline_dashboard_source, 'data-discipline-record-filter="' . $discipline_department_filter . '"') === false) {
            fwrite(STDERR, "FAIL: $discipline_dashboard must provide a discipline-record filter for $discipline_department_filter.\n");
            exit(1);
        }
    }
    if (strpos($discipline_dashboard_source, 'data-discipline-record-department=') === false
        || strpos($discipline_dashboard_source, 'const nextFilter = wasSelected ? \'\' : selected;') === false
        || strpos($discipline_dashboard_source, 'No discipline records posted yet in \' + label + \'.') === false
        || strpos($discipline_dashboard_source, 'data-notification-row="oversight-fine-') === false
        || strpos($discipline_dashboard_source, 'data-notification-row="disciplinary-report-') === false) {
        fwrite(STDERR, "FAIL: $discipline_dashboard must filter, toggle, and show empty-state text for routed discipline records.\n");
        exit(1);
    }
}
$member_dashboard_source = file_get_contents(__DIR__ . '/../member_dashboard.php');
if ($member_dashboard_source === false
    || strpos($member_dashboard_source, 'data-discipline-master-filter=') === false
    || strpos($member_dashboard_source, 'data-discipline-master-record-list') === false
    || strpos($member_dashboard_source, 'department_matches($requested_disc_dept, $assigned_disc_dept)') === false
    || strpos($member_dashboard_source, 'recordsClosed = !recordsClosed;') === false
    || strpos($member_dashboard_source, 'You are not assigned as Discipline Master for this department.') === false) {
    fwrite(STDERR, "FAIL: Discipline Masters must see department ribbons limited to their assigned departments.\n");
    exit(1);
}
if (strpos($member_dashboard_source, 'data-attendance-member-search-input') === false
    || strpos($member_dashboard_source, 'oninput="filterAttendanceRegisterMembers(this)"') === false
    || strpos($member_dashboard_source, 'onclick="filterAttendanceRegisterMembers(this)"') === false
    || strpos($member_dashboard_source, 'window.filterAttendanceRegisterMembers = function(control)') === false
    || strpos($member_dashboard_source, 'name.includes(term)') === false) {
    fwrite(STDERR, "FAIL: Organizing Secretary attendance registers must search attendee names on the first input and from the magnifying-glass button.\n");
    exit(1);
}
$glow_script = render_notification_tab_glow_script('manage_department', 'manage_department', $routed_message);
if (strpos($glow_script, 'notification-row:department-member-42') === false
    || strpos($glow_script, 'notification-row-glow') === false) {
    fwrite(STDERR, "FAIL: explicit row metadata reaches the shared gold-glow handler.\n");
    exit(1);
}
expect_same(['dashboard'], notification_badge_tabs('Unclassified legacy notification', 'member'), 'unclassified legacy notifications retain an unread badge');
expect_same(['my_fines'], notification_badge_tabs('Fine issued to Jane: KSh 100', 'member'), 'legacy fine notifications retain their existing route');

echo "Notification routing tests passed.\n";
