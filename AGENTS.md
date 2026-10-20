# Notification Requirements

These requirements apply to every dashboard and department feature in this project.

- Every new notification must use `notification_with_route($message, $tab, $group, $rowKey)` from `notification_badges.php` before it is stored. Choose the exact destination tab; include a group when the destination is department-scoped; include a row key when the notification refers to a specific rendered item.
- The row key must match a `data-notification-row` value rendered on that destination tab. The shared notification flow then shows the unread red badge, marks the item read on open, opens the destination tab/group, scrolls to the item, and applies the 10-second gold glow.
- Keep routing and focus metadata out of user-visible notification text by using `notification_display_message()` when rendering notifications.
- When a new destination or row type is added, update its dashboard rendering and add a regression case to `tests/notification_routing_test.php`. Do not rely on ambiguous keyword matching for new notifications.
- Legacy notifications without explicit route metadata must still map through `notification_badge_tabs()`. Unrecognized legacy messages remain visible on the Dashboard as a fallback instead of losing their unread badge.

# Print Layout Requirements

- Put each report's responsible leader profile first, above the church/report title, using `.print-leader-header` and `.print-leader-person`; mark the portrait `.print-leader-avatar`. The shared print helper moves this header before the report heading and renders the photo as a clean circle with no rectangular frame/card. Keep logos separate and do not apply this rule to member-photo columns.
- All print windows opened from dashboards inherit `church_print_layout.js`; every standalone print page must include it with `data-direct-print="true"`.
- Keep printable table headers repeating and each individual row together, but allow the table itself to flow across pages. Do not make a whole table, department group, or oversized card unbreakable.
- Avoid `rowspan` in printable table bodies. When the screen table groups rows with rowspans, the shared print helper repeats the leading department cell in the print document so remaining rows can fill the page before a page break.
- Reserve a modest 16 mm bottom page margin for the footer. Preserve the report's orientation and top/side margins; do not add large bottom padding that leaves unused page space.
- For new print features, verify portrait and landscape pagination with enough rows to cross a page boundary and confirm the footer remains at the bottom of each page.
- If the shared pagination helper changes, run `node tests/church_print_layout_test.js` to protect row-group splitting and repeated preparation.
