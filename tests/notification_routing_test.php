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
