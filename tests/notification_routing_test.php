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
    || strpos($pastor_dashboard_source, 'notification-group:([a-z0-9_-]+)') === false) {
    fwrite(STDERR, "FAIL: pastor Organised Events must filter and auto-select the department from a routed notification.\n");
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
