# Notification Requirements

These requirements apply to every dashboard and department feature in this project.

- Every new notification must use `notification_with_route($message, $tab, $group, $rowKey)` from `notification_badges.php` before it is stored. Choose the exact destination tab; include a group when the destination is department-scoped; include a row key when the notification refers to a specific rendered item.
- The row key must match a `data-notification-row` value rendered on that destination tab. The shared notification flow then shows the unread red badge, marks the item read on open, opens the destination tab/group, scrolls to the item, and applies the 10-second gold glow.
- Keep routing and focus metadata out of user-visible notification text by using `notification_display_message()` when rendering notifications.
- When a new destination or row type is added, update its dashboard rendering and add a regression case to `tests/notification_routing_test.php`. Do not rely on ambiguous keyword matching for new notifications.
- Legacy notifications without explicit route metadata must still map through `notification_badge_tabs()`. Unrecognized legacy messages remain visible on the Dashboard as a fallback instead of losing their unread badge.
