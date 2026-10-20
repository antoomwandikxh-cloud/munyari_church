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
$glow_script = render_notification_tab_glow_script('manage_department', 'manage_department', $routed_message);
if (strpos($glow_script, 'notification-row:department-member-42') === false
    || strpos($glow_script, 'notification-row-glow') === false) {
    fwrite(STDERR, "FAIL: explicit row metadata reaches the shared gold-glow handler.\n");
    exit(1);
}
expect_same(['dashboard'], notification_badge_tabs('Unclassified legacy notification', 'member'), 'unclassified legacy notifications retain an unread badge');
expect_same(['my_fines'], notification_badge_tabs('Fine issued to Jane: KSh 100', 'member'), 'legacy fine notifications retain their existing route');

echo "Notification routing tests passed.\n";
