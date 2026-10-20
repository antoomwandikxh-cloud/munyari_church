<?php
function notification_display_message($message, $user_type = null) {
    if ($user_type === 'pastor'
        && preg_match('/^Financial record submitted for chairperson review in (.+?)\. KSh ([\d,]+(?:\.\d{2})?) from the Treasurer\. Open Received Financial Records to confirm and disburse\. Reason: (.*?)(?=\s*\[financial-record-id:\s*\d+\]|\s*$)/i', (string)$message, $legacy_finance_match)) {
        $message = "Treasurer submitted a financial record of KSh {$legacy_finance_match[2]} for the {$legacy_finance_match[1]} department. It is awaiting chairperson confirmation and has not yet been disbursed to members. Please review the transaction before the chairperson disburses it. Reason: {$legacy_finance_match[3]}" . (preg_match('/\[financial-record-id:\s*\d+\]/i', (string)$message, $record_id_match) ? ' ' . $record_id_match[0] : '');
    }
    $message = preg_replace('/\s*\[financial-record-id:\s*\d+\]/i', '', (string)$message);
    $message = preg_replace('/\s*\[focus-fine-rule-id:\s*\d+\]/i', '', (string)$message);
    $message = preg_replace('/\s*\[focus-fine-id:\s*\d+\]/i', '', (string)$message);
    $message = preg_replace('/\s*Fine record\s*#\s*\d+\.?/i', '', $message);
    $message = preg_replace('/\s*\[village-announcement-id:\s*\d+\]/i', '', $message);
    $message = preg_replace('/\s*\[village-leader-chat-id:\s*\d+\]/i', '', $message);
    $message = preg_replace('/\s*\[notification-(?:tab|group|row):[^\]]+\]/i', '', $message);
    return trim($message);
}

function notification_with_route($message, $target_tab, $target_group = '', $target_row = '') {
    $message = trim((string)$message);
    $target_tab = strtolower(trim((string)$target_tab));
    $target_group = strtolower(trim((string)$target_group));
    $target_row = strtolower(trim((string)$target_row));

    if ($target_tab === '' || !preg_match('/^[a-z0-9_-]+$/', $target_tab)) {
        return $message;
    }

    $message = preg_replace('/\s*\[notification-(?:tab|group|row):[^\]]+\]/i', '', $message);
    $message .= ' [notification-tab:' . $target_tab . ']';
    if ($target_group !== '' && preg_match('/^[a-z0-9_-]+$/', $target_group)) {
        $message .= ' [notification-group:' . $target_group . ']';
    }
    if ($target_row !== '' && preg_match('/^[a-z0-9_-]+$/', $target_row)) {
        $message .= ' [notification-row:' . $target_row . ']';
    }
    return $message;
}

function notification_route_metadata($message) {
    $metadata = ['tab' => '', 'group' => '', 'row' => ''];
    foreach (['tab', 'group', 'row'] as $key) {
        if (preg_match('/\[notification-' . $key . ':([a-z0-9_-]+)\]/i', (string)$message, $match)) {
            $metadata[$key] = strtolower($match[1]);
        }
    }
    return $metadata;
}

function notification_badge_tabs($message, $user_type, $is_treasurer = false) {
    $route = notification_route_metadata($message);
    if ($route['tab'] !== '') {
        return [$route['tab']];
    }

    $msg  = strtolower($message);
    $tabs = [];

    if (strpos($msg, 'village leaders chat message from ') !== false) {
        return $user_type === 'member' ? ['village_leader_chat'] : ['general_leadership'];
    }

    // Village announcements may contain words used by other notification categories (for example, "fine").
    // Route them first so each audience gets the correct announcements tab and exact post focus.
    if (strpos($msg, 'new village announcement') !== false && strpos($msg, 'village leader') !== false) {
        return $user_type === 'member' ? ['desired_roles'] : ['general_announcements'];
    }

    if (strpos($msg, 'disciplinary report outcome:') !== false) {
        return $user_type === 'member' ? ['discipline'] : ['discipline_reports'];
    }
    if (strpos($msg, 'disciplinary report pending chairman review') !== false
        || strpos($msg, 'discipline master has reported a member') !== false) {
        return $user_type === 'member' ? ['manage_department'] : ['discipline_reports'];
    }
    if (strpos($msg, 'disciplinary report forwarded to pastor') !== false) {
        return ['discipline_reports'];
    }

    if ($user_type === 'member' && strpos($msg, 'discipline master fine payment update:') !== false) {
        return ['discipline'];
    }
    if ($user_type === 'member' && strpos($msg, 'discipline master fine payment declined in ') !== false) {
        return ['discipline'];
    }
    if ($user_type === 'member' && strpos($msg, 'fine payment awaiting treasurer verification:') !== false) {
        return ['discipline'];
    }

    if (strpos($msg, 'fine') !== false) {
        if (strpos($msg, 'initiated a fine payment') !== false && strpos($msg, 'financial records') !== false) {
            return ['financials'];
        }
        return [$user_type === 'member' ? 'my_fines' : 'discipline_reports'];
    }

    if (strpos($msg, 'financial record submitted for chairperson review in ') === 0) {
        return $user_type === 'member' ? ['received_financials'] : ['financials'];
    }
    if (($user_type === 'pastor' || $user_type === 'admin')
        && preg_match('/^.+?\s+treasurer\s+\(.+\)\s+recorded a collection of ksh\b/i', trim((string)$message))) {
        return ['financials'];
    }
    if (strpos($msg, 'treasurer submitted a financial record of ksh ') === 0) {
        return ['financials'];
    }
    if (strpos($msg, 'financial record disbursed to members in ') === 0) {
        return $user_type === 'member'
            ? ($is_treasurer ? ['treasurer_records'] : ['my_financials'])
            : ['financials'];
    }

    if (strpos($msg, 'department event') !== false && strpos($msg, 'organising secretary') !== false) {
        return ['organized_events'];
    }

    // ─── WORSHIP TASKS & TEAM NOTIFICATIONS ────────────────────────────────
    if (strpos($msg, 'worship task') !== false 
        || strpos($msg, 'worship assignment') !== false 
        || strpos($msg, 'worship team update') !== false
        || strpos($msg, 'worship control panel') !== false
    ) {
        // If member accepted/agreed to task, or leader team update -> route to manage_worshippers
        if (strpos($msg, 'worship team update') !== false || strpos($msg, 'agreed to do') !== false) {
            $tabs[] = 'manage_worshippers';
            return array_values(array_unique($tabs));
        }

        if ($user_type === 'member') {
            $tabs[] = 'my_worship_tasks';
        } elseif ($user_type === 'pastor' || $user_type === 'admin') {
            $tabs[] = 'manage_worshippers';
        }
        return array_values(array_unique($tabs));
    }

    // ─── PASTOR GENERAL CHURCH ANNOUNCEMENT (MUST be before contact_pastor rules) ─────────
    // Notification text: "General Church Announcement from Pastor <name>[Voice Note]: ..."
    // These MUST route to announcements (members) or general_announcements (admin/pastor). NEVER contact_pastor.
    if (strpos($msg, 'general church announcement') !== false) {
        if ($user_type === 'member') {
            return ['announcements'];
        } else {
            return ['general_announcements'];
        }
    }

    if ($user_type === 'member' && strpos($msg, 'message from admin') !== false) {
        return ['contact_pastor'];
    }

    if ($user_type === 'member' && strpos($msg, 'your membership has been approved by the pastor') === 0) {
        return ['dashboard'];
    }
    if (strpos($msg, 'new church village member:') === 0) {
        return $user_type === 'member' ? ['manage_church_village'] : ['desired_roles'];
    }
    if (preg_match('/^a new member has been added in .+ church village\.?$/i', trim($message))) {
        return $user_type === 'member' ? ['manage_church_village'] : ['desired_roles'];
    }
    if ($user_type === 'member' && strpos($msg, 'new member added to your department:') === 0) {
        return ['manage_department'];
    }
    if (($user_type === 'pastor' || $user_type === 'admin') && strpos($msg, 'new approved member:') === 0) {
        return ['departments'];
    }

    // ─── APPOINTMENTS & CONTACT PASTOR & DIRECT MESSAGES ─────────────────────
    if (strpos($msg, 'reply from member') !== false
        || strpos($msg, 'member reply') !== false
        || strpos($msg, 'message from member') !== false
        || (strpos($msg, 'member') !== false && strpos($msg, 'replied') !== false)
    ) {
        if ($user_type === 'pastor' || $user_type === 'admin') {
            $tabs[] = 'appointments';
            return array_values(array_unique($tabs));
        }
    }

    if (strpos($msg, 'appointment') !== false && strpos($msg, 'role') === false) {
        if ($user_type === 'member') {
            $tabs[] = 'contact_pastor';
        } else {
            $tabs[] = 'appointments';
        }
        return array_values(array_unique($tabs));
    }
    if (strpos($msg, 'contact pastor') !== false 
        || strpos($msg, 'message from pastor') !== false 
        || strpos($msg, 'message from the pastor') !== false
        || strpos($msg, 'reply from pastor') !== false
        || (strpos($msg, 'pastor') !== false && strpos($msg, 'sent you a message') !== false)
        || (strpos($msg, 'pastor') !== false && strpos($msg, 'replied') !== false)
        || (strpos($msg, 'pastor') !== false && strpos($msg, 'personal message') !== false)
    ) {
        if ($user_type === 'member') {
            $tabs[] = 'contact_pastor';
            return array_values(array_unique($tabs));
        }
    }

    if (strpos($msg, 'new member registration:') === 0 && strpos($msg, 'pending approval') !== false) {
        return [$user_type === 'admin' ? 'members' : 'manage_members'];
    }
    // Role removals are shown on the member dashboard, where current role/status is visible.
    if ($user_type === 'member'
        && strpos($msg, 'your role') !== false
        && strpos($msg, 'removed') !== false) {
        return ['dashboard'];
    }
    if (strpos($msg, 'approved by the pastor') !== false || strpos($msg, 'approved by the admin') !== false) {
        $tabs[] = 'dashboard';
    }
    if (strpos($msg, 'deployed to their department') !== false) {
        $tabs[] = 'departments';
    }

    if (strpos($msg, 'assigned as the church village leader') !== false) {
        $tabs[] = 'manage_church_village';
    }
    if (strpos($msg, 'a new member has been added in') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'manage_church_village';
        } else {
            $tabs[] = 'desired_roles';
        }
    }


    if (strpos($msg, 'sunday school class change request') !== false || strpos($msg, 'sunday school class approval') !== false) {
        if ($user_type === 'member') {
            $tabs[] = (strpos($msg, 'your sunday school class') !== false) ? 'manage_classes' : 'manage_department';
        } elseif ($user_type === 'pastor' || $user_type === 'admin') {
            $tabs[] = 'manage_sunday_school';
        }
        return array_values(array_unique($tabs));
    }

    if (strpos($msg, 'sunday school teacher message') !== false) {
        return [$user_type === 'member' ? 'manage_classes' : 'manage_sunday_school'];
    }

    if (strpos($msg, 'sunday school attendance register') !== false) {
        return [$user_type === 'member' ? 'manage_sunday_classes' : 'manage_sunday_school'];
    }

    if (strpos($msg, 'sunday school class leader') !== false || strpos($msg, 'sunday school teacher') !== false) {
        if ($user_type === 'member') {
            $tabs[] = (strpos($msg, 'approval request') !== false) ? 'manage_department' : 'manage_sunday_classes';
        } elseif ($user_type === 'pastor' || $user_type === 'admin') {
            $tabs[] = 'manage_sunday_school';
        }
        return array_values(array_unique($tabs));
    }

    // A member's leadership appointment is confirmed on their dashboard. Keep this
    // before the general role rule so its badge and bell link share one destination.
    $is_member_role_appointment = $user_type === 'member' && (
        strpos($msg, 'you have been assigned a new role') !== false
        || (strpos($msg, 'welcome ') === 0 && strpos($msg, ' in the youth ministry') !== false)
        || (strpos($msg, 'welcome ') === 0 && strpos($msg, ' in the building & construction department') !== false)
    );
    if ($is_member_role_appointment) {
        return ['dashboard'];
    }

    if ($user_type === 'member') {
        if (strpos($msg, 'treasurer') !== false && (strpos($msg, 'received') !== false || strpos($msg, 'sent') !== false || strpos($msg, 'record') !== false)) {
            return ['manage_department', 'received_financials'];
        }
        if (strpos($msg, 'chairperson') !== false && (strpos($msg, 'received') !== false || strpos($msg, 'dispersed') !== false || strpos($msg, 'ksh') !== false)) {
            return ['my_financials', 'financials'];
        }
    }

    // ─── 1. SECRETARY REPLY TO QUERY (most specific – must come first) ──────
    // e.g. "Reply to your announcement query from General Church Secretary rose: ..."
    if (strpos($msg, 'reply to your announcement query') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'query_announcements';
        } else {
            $tabs[] = 'general_announcements';
        }
        return array_values(array_unique($tabs)); // early exit – don't bleed into generic announcement checks
    }

    // ─── 2. ANNOUNCEMENT QUERY / QUERIED ────────────────────────────────────
    // e.g. "Announcement Queried message from General Church Secretary ..."
    if (strpos($msg, 'announcement query') !== false || strpos($msg, 'announcement queried') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'query_announcements';
        } else {
            $tabs[] = 'general_announcements';
        }
        return array_values(array_unique($tabs)); // early exit
    }

    // ─── 3. LEADERSHIP CHAT MESSAGES ────────────────────────────────────────
    if (strpos($msg, 'department leaders chat message in ') !== false) {
        return [$user_type === 'member' ? 'leader_chat' : 'general_leadership'];
    }
    if (strpos($msg, 'leadership chat') !== false) {
        if (strpos($msg, 'worship') !== false) {
            $tabs[] = $user_type === 'member' ? 'worship_leadership_chat' : 'general_leadership';
        } elseif (strpos($msg, 'building') !== false || strpos($msg, 'construction') !== false) {
            $tabs[] = $user_type === 'member' ? 'building_leadership_chat' : 'general_leadership';
        } elseif (strpos($msg, 'general church') !== false || strpos($msg, 'secretary council') !== false) {
            $tabs[] = $user_type === 'member' ? 'general_leader_chat' : 'general_leadership';
        } else {
            $tabs[] = $user_type === 'member' ? 'leader_chat' : 'general_leadership';
        }
        return array_values(array_unique($tabs)); // early exit
    }

    // ─── 4. ACCOUNT / REGISTRATION ──────────────────────────────────────────
    if (strpos($msg, 'update their church village') !== false) {
        $tabs[] = 'desired_roles';
        return array_values(array_unique($tabs)); // early exit
    }

    if (strpos($msg, 'account') !== false && (strpos($msg, 'approved') !== false || strpos($msg, 'created') !== false)) {
        $tabs[] = 'dashboard';
        $tabs[] = 'settings';
    }
    if (strpos($msg, 'pastor') !== false && strpos($msg, 'registration') !== false) {
        $tabs[] = $user_type === 'admin' ? 'pastors' : 'dashboard';
    }
        if ((strpos($msg, 'member') !== false && strpos($msg, 'worship') === false) || 
        (strpos($msg, 'registration') !== false && strpos($msg, 'pastor') === false)) {
        $tabs[] = $user_type === 'admin' ? 'members' : 'manage_members';
        $tabs[] = 'departments';
    }

    if (strpos($msg, 'assigned as the church village leader') !== false) {
        $tabs[] = 'manage_church_village';
    }
    if (strpos($msg, 'a new member has been added in') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'manage_church_village';
        } else {
            $tabs[] = 'desired_roles';
        }
    }

    // ─── 5. ROLE / APPOINTMENT ──────────────────────────────────────────────
    if (strpos($msg, 'role') !== false || strpos($msg, 'appointment to the role') !== false) {
        $tabs[] = 'assign_roles';
    }
    if (strpos($msg, 'appointment') !== false && strpos($msg, 'role') === false) {
        if ($user_type === 'member') {
            $tabs[] = 'contact_pastor';
        } elseif ($user_type === 'pastor') {
            $tabs[] = 'appointments';
        } else {
            $tabs[] = 'appointments';
        }
    }

    // ─── 6. DAILY QUOTE / HIGHLIGHTS ────────────────────────────────────────
    if (strpos($msg, 'daily quote') !== false || strpos($msg, 'highlight') !== false || strpos($msg, 'inspiration') !== false) {
        $tabs[] = $user_type === 'admin' ? 'content' : 'inspiration_highlights';
    }

    // ─── 7. GENERAL ANNOUNCEMENTS (broad – must come after specific query checks) ─
    if (strpos($msg, 'transferred') !== false) {
        $tabs[] = 'dashboard';
    }

    // Senior Church Elder Announcement -> direct to general_announcements for pastor/admin, announcements for members
    if (strpos($msg, 'senior church elder announcement') !== false || (strpos($msg, 'senior') !== false && strpos($msg, 'elder') !== false && strpos($msg, 'announcement') !== false)) {
        if ($user_type === 'member') {
            $tabs[] = 'announcements';
            $tabs[] = 'elder_announcements';
        } else {
            $tabs[] = 'general_announcements';
        }
        return array_values(array_unique($tabs));
    }

    // Pastor announcement -> direct to announcements for members, general_announcements for admin/pastor
    if (strpos($msg, 'announcement from pastor') !== false || (strpos($msg, 'pastor') !== false && strpos($msg, 'announcement') !== false)) {
        if ($user_type === 'member') {
            $tabs[] = 'announcements';
        } else {
            $tabs[] = 'general_announcements';
        }
        return array_values(array_unique($tabs));
    }

    // Secretary announcement -> direct to general_announcements for pastor/admin
    if (strpos($msg, 'announcement') !== false && strpos($msg, 'secretary') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'announcements';
            $tabs[] = 'dept_announcements';
        } else {
            $tabs[] = 'general_announcements';
        }
        return array_values(array_unique($tabs));
    }

    if (strpos($msg, 'announcement') !== false || strpos($msg, 'department') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'announcements';
            $tabs[] = 'dept_announcements';
            $tabs[] = 'elder_announcements';
        } else {
            if (strpos($msg, 'announcement') !== false) {
                $tabs[] = 'general_announcements';
            }
            $tabs[] = 'departments';
            if (strpos($msg, 'general church') !== false || strpos($msg, 'senior church elder') !== false) {
                $tabs[] = 'general_announcements';
            }
        }
    }

    // ─── 8. EVENTS / MEETINGS ───────────────────────────────────────────────
    if (strpos($msg, 'event') !== false || strpos($msg, 'meeting') !== false) {
        $tabs[] = 'organized_events';
    }

    // ─── 9. FINES / DISCIPLINE ──────────────────────────────────────────────
    if (strpos($msg, 'fine') !== false || strpos($msg, 'discipline') !== false || strpos($msg, 'report') !== false) {
        $tabs[] = $user_type === 'member' ? 'my_fines' : 'discipline_reports';
    }

    // ─── 10. GRADUATION ─────────────────────────────────────────────────────
    if (strpos($msg, 'graduation') !== false || strpos($msg, 'graduand') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'graduation_info';
            $tabs[] = 'graduation_panel';
        } else {
            $tabs[] = 'graduation_info';
        }
    }

    // ─── 11. PRAYER ─────────────────────────────────────────────────────────
    if (strpos($msg, 'prayer') !== false) {
        $tabs[] = 'prayer_board';
        if ($user_type === 'member') {
            $tabs[] = 'prayer_panel';
        }
    }

    // ─── 12. SPORT / MATCH ──────────────────────────────────────────────────
    if (strpos($msg, 'sport') !== false || strpos($msg, 'match') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'dept_sport';
            $tabs[] = 'manage_sport';
        } else {
            $tabs[] = 'sport_board';
        }
    }

    // ─── 13. CHOIR / SONG ───────────────────────────────────────────────────
    if (strpos($msg, 'choir') !== false || strpos($msg, 'song') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'choir_songs';
            $tabs[] = 'manage_songs';
        } else {
            $tabs[] = 'choir_songs';
        }
    }

    // ─── 14. FINANCIALS ─────────────────────────────────────────────────────
    if (strpos($msg, 'financial') !== false || strpos($msg, 'payment') !== false || strpos($msg, 'treasurer') !== false || strpos($msg, 'collection') !== false || strpos($msg, 'ksh') !== false) {
        $tabs[] = 'financials';
    }

    // ─── 15. GENERAL CHAT / MESSAGES (non-leadership, non-usher) ────────────
    if (strpos($msg, 'message') !== false || strpos($msg, 'chat') !== false) {
        if (strpos($msg, 'usher') !== false) {
            // handled by usher block below
        } elseif (strpos($msg, 'general church leadership') !== false || strpos($msg, 'secretary council') !== false) {
            $tabs[] = $user_type === 'member'
                ? (strpos($msg, 'secretary council') !== false ? 'query_announcements' : 'general_leader_chat')
                : 'general_leadership';
        } else {
            $tabs[] = $user_type === 'member' ? 'leader_chat' : 'appointments';
        }
    }

    // ─── 16. USHER ──────────────────────────────────────────────────────────
    if (strpos($msg, 'usher') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'usher_panel';
            $tabs[] = 'usher_chat';
        } else {
            $tabs[] = 'usher_monitoring';
        }
    }

    // ─── 17. BUILDING / CONSTRUCTION (non-leadership-chat already handled) ──
    if (strpos($msg, 'building') !== false || strpos($msg, 'construction') !== false || strpos($msg, 'material') !== false || strpos($msg, 'roofing') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'church_buildings';
        } else {
            $tabs[] = 'building_monitoring';
        }
    }

    // ─── 18. WORSHIP ANNOUNCEMENTS (worship leader posts to worshippers) ────
    if (strpos($msg, 'worship') !== false && strpos($msg, 'announcement') !== false && strpos($msg, 'worshipper') !== false) {
        $tabs[] = $user_type === 'member' ? 'manage_worshippers' : 'general_leadership';
    }

    // Keep messages that predate explicit routing visible instead of silently
    // dropping their unread badge. New notifications should use notification_with_route().
    return $tabs ? array_values(array_unique($tabs)) : ['dashboard'];
}

function ensure_notification_focus_schema($conn) {
    static $available = null;
    if ($available !== null) return $available;

    try {
        $column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'related_member_id'");
        if ($column && $column->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN related_member_id INT(6) UNSIGNED NULL AFTER message");
            $column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'related_member_id'");
        }
        $available = $column && $column->num_rows > 0;
    } catch (Throwable $error) {
        // Older hosted databases may not grant ALTER to the application account.
        $available = false;
    }
    return $available;
}

function notify_church_village_leaders_of_member($conn, $member_id, $village) {
    $member_id = (int)$member_id;
    $village = trim((string)$village);
    if ($member_id < 1 || $village === '') return 0;

    $member_result = $conn->query("SELECT first_name, last_name FROM members WHERE id = $member_id LIMIT 1");
    $member = $member_result ? $member_result->fetch_assoc() : null;
    if (!$member) return 0;

    $member_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    $message = "New church village member: $member_name is now in $village village.";
    $has_related_member_id = ensure_notification_focus_schema($conn);
    $village_sql = $conn->real_escape_string($village);
    $leaders = $conn->query("SELECT id FROM members
        WHERE is_village_leader = 1 AND is_approved = 1 AND id <> $member_id
          AND LOWER(TRIM(church_village)) = LOWER('$village_sql')");
    if (!$leaders) return 0;

    $sent = 0;
    $statement = $has_related_member_id
        ? $conn->prepare("INSERT INTO notifications (user_id, user_type, message, related_member_id) VALUES (?, 'member', ?, ?)")
        : $conn->prepare("INSERT INTO notifications (user_id, user_type, message) VALUES (?, 'member', ?)");
    if (!$statement) return 0;

    while ($leader = $leaders->fetch_assoc()) {
        $leader_id = (int)$leader['id'];
        if ($has_related_member_id) {
            $statement->bind_param('isi', $leader_id, $message, $member_id);
        } else {
            $statement->bind_param('is', $leader_id, $message);
        }
        if ($statement->execute()) $sent++;
    }
    $statement->close();
    return $sent;
}

function notify_staff_of_department_member_approval($conn, $member_id) {
    $member_id = (int)$member_id;
    if ($member_id < 1) return 0;

    $member_result = $conn->query("SELECT first_name, last_name, department FROM members WHERE id = $member_id AND is_approved = 1 LIMIT 1");
    $member = $member_result ? $member_result->fetch_assoc() : null;
    if (!$member) return 0;

    $department = trim((string)($member['department'] ?? ''));
    if ($department === '') return 0;
    $department_sql = $conn->real_escape_string($department);
    $count_result = $conn->query("SELECT COUNT(*) AS total FROM members WHERE is_approved = 1 AND LOWER(TRIM(department)) = LOWER('$department_sql')");
    $department_total = $count_result ? (int)($count_result->fetch_assoc()['total'] ?? 0) : 0;
    $full_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    $message = "New approved member: $full_name has joined the $department department. Current total: $department_total.";
    $has_related_member_id = ensure_notification_focus_schema($conn);
    $recipients = [];

    $admins = $conn->query("SELECT id FROM admins");
    if ($admins) {
        while ($admin = $admins->fetch_assoc()) $recipients[] = [(int)$admin['id'], 'admin'];
    }
    $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
    if ($pastors) {
        while ($pastor = $pastors->fetch_assoc()) $recipients[] = [(int)$pastor['id'], 'pastor'];
    }

    $chair_roles_by_department = [
        'youths' => ['youth chairperson', 'youth chairman', 'youth chairlady'],
        'womens ministry' => ['women chairlady', 'women chairperson', 'women chairman'],
        'women ministry' => ['women chairlady', 'women chairperson', 'women chairman'],
        'elders' => ['elder chairman', 'elder chairperson', 'elder chairlady'],
        'sunday school' => ['sunday school patron', 'sunday school chairperson', 'sunday school chairman', 'sunday school chairlady'],
        'building & construction' => ['building chairperson', 'building chairman', 'building chairlady'],
    ];
    $chair_roles = $chair_roles_by_department[strtolower($department)] ?? [];
    $chair_ids = [];
    if ($chair_roles) {
        $department_sql = $conn->real_escape_string($department);
        $chairs = $conn->query("SELECT id, church_role FROM members
            WHERE is_approved = 1 AND id <> $member_id
              AND LOWER(TRIM(department)) = LOWER('$department_sql')");
        if ($chairs) {
            while ($chair = $chairs->fetch_assoc()) {
                $assigned_roles = preg_split('/[,;|]+/', (string)($chair['church_role'] ?? ''));
                $assigned_roles = array_map(static function ($role) {
                    $role = strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/\(.*?\)/', '', $role))));
                    return trim($role);
                }, $assigned_roles);
                if (array_intersect($chair_roles, $assigned_roles)) {
                    $chair_ids[] = (int)$chair['id'];
                }
            }
        }
    }

    $statement = $has_related_member_id
        ? $conn->prepare("INSERT INTO notifications (user_id, user_type, message, related_member_id) VALUES (?, ?, ?, ?)")
        : $conn->prepare("INSERT INTO notifications (user_id, user_type, message) VALUES (?, ?, ?)");
    $sent = 0;
    if ($statement) {
        foreach ($recipients as [$recipient_id, $recipient_type]) {
            if ($has_related_member_id) {
                $statement->bind_param('issi', $recipient_id, $recipient_type, $message, $member_id);
            } else {
                $statement->bind_param('iss', $recipient_id, $recipient_type, $message);
            }
            if ($statement->execute()) $sent++;
        }
        $statement->close();
    }

    if ($chair_ids) {
        $chair_message = "New member added to your department: $full_name ($department).";
        $chair_statement = $has_related_member_id
            ? $conn->prepare("INSERT INTO notifications (user_id, user_type, message, related_member_id) VALUES (?, 'member', ?, ?)")
            : $conn->prepare("INSERT INTO notifications (user_id, user_type, message) VALUES (?, 'member', ?)");
        if ($chair_statement) {
            foreach (array_unique($chair_ids) as $chair_id) {
                if ($has_related_member_id) {
                    $chair_statement->bind_param('isi', $chair_id, $chair_message, $member_id);
                } else {
                    $chair_statement->bind_param('is', $chair_id, $chair_message);
                }
                if ($chair_statement->execute()) $sent++;
            }
            $chair_statement->close();
        }
    }

    return $sent;
}

function build_tab_notification_badges($conn, $user_id, $user_type, $current_tab, &$current_tab_messages = null) {
    $user_id      = (int)$user_id;
    $user_type_sql = $conn->real_escape_string($user_type);
    $is_treasurer = false;
    if ($user_type === 'member') {
        $role_result = $conn->query("SELECT church_role FROM members WHERE id = $user_id LIMIT 1");
        $role_row = $role_result ? $role_result->fetch_assoc() : null;
        $is_treasurer = stripos((string)($role_row['church_role'] ?? ''), 'treasurer') !== false;
    }
    $has_related_member_id = ensure_notification_focus_schema($conn);
    $related_member_column = $has_related_member_id ? 'related_member_id' : 'NULL AS related_member_id';
    $result = $conn->query("SELECT id, message, created_at, $related_member_column FROM notifications WHERE user_id = $user_id AND user_type = '$user_type_sql' AND is_read = 0");
    $badges   = [];
    $read_ids = [];
    $current_tab_messages = [];

    if ($result) {
        while ($notification = $result->fetch_assoc()) {
            $tabs = notification_badge_tabs($notification['message'], $user_type, $is_treasurer);
            foreach ($tabs as $target_tab) {
                if (!isset($badges[$target_tab])) {
                    $badges[$target_tab] = 0;
                }
                $badges[$target_tab]++;
                if ($current_tab === $target_tab) {
                    $read_ids[] = (int)$notification['id'];
                    $current_tab_messages[] = notification_focus_message_with_member_id(
                        $conn,
                        (string)$notification['message'],
                        (string)($notification['created_at'] ?? ''),
                        (int)($notification['related_member_id'] ?? 0)
                    );
                    $last_message_index = count($current_tab_messages) - 1;
                    $current_tab_messages[$last_message_index] = notification_focus_message_with_financial_record_id(
                        $conn,
                        $current_tab_messages[$last_message_index],
                        (string)($notification['created_at'] ?? ''),
                        $user_type
                    );
                }
            }
        }
    }

    $current_tab_messages = array_values(array_unique($current_tab_messages));

    if (!empty($read_ids)) {
        $ids = implode(',', array_unique($read_ids));
        $conn->query("UPDATE notifications SET is_read = 1 WHERE id IN ($ids) AND user_id = $user_id AND user_type = '$user_type_sql'");
        unset($badges[$current_tab]);
    }

    return $badges;
}

function notification_focus_message_with_member_id($conn, $message, $created_at = '', $related_member_id = 0) {
    $message = (string)$message;
    $focus_type = '';
    if (preg_match('/^New member added to your department:/i', $message)) {
        $focus_type = 'department-member';
    } elseif (preg_match('/^New approved member:/i', $message)) {
        $focus_type = 'approved-member';
    } elseif (preg_match('/^New church village member:/i', $message)) {
        $focus_type = 'church-village-member';
    } elseif (preg_match('/^A new member has been added in .+ church village\.?$/i', trim($message))) {
        $focus_type = 'church-village-member';
    } elseif (preg_match('/^New member registration:/i', $message)) {
        $focus_type = 'member-registration';
    }
    if ($focus_type === '') return $message;

    $related_member_id = (int)$related_member_id;
    $fallback_name = '';
    $fallback_department = '';
    $legacy_village = '';
    if ($focus_type === 'member-registration'
        && preg_match('/^New member registration:\s*(.+?)\s+in\s+(.+?)\s+department\s+is\s+pending approval\.?$/i', trim($message), $match)) {
        $fallback_name = trim($match[1]);
        $fallback_department = trim($match[2]);
    } elseif ($focus_type === 'department-member'
        && preg_match('/^New member added to your department:\s*(.+?)\s*\((.+?)\)\.?$/i', trim($message), $match)) {
        $fallback_name = trim($match[1]);
        $fallback_department = trim($match[2]);
    } elseif ($focus_type === 'approved-member'
        && preg_match('/^New approved member:\s*(.+?)\s+has joined the\s+(.+?)\s+department\./i', trim($message), $match)) {
        $fallback_name = trim($match[1]);
        $fallback_department = trim($match[2]);
    } elseif ($focus_type === 'church-village-member'
        && preg_match('/^New church village member:\s*(.+?)\s+is now in\s+(.+?)\s+village\.?$/i', trim($message), $match)) {
        $fallback_name = trim($match[1]);
        $fallback_department = trim($match[2]);
    } elseif ($focus_type === 'church-village-member'
        && preg_match('/^A new member has been added in\s+(.+?)\s+church village\.?$/i', trim($message), $match)) {
        $legacy_village = trim($match[1]);
    }

    if ($related_member_id < 1 && $focus_type === 'church-village-member' && $legacy_village !== '') {
        $village = $conn->real_escape_string($legacy_village);
        $created_at = $conn->real_escape_string($created_at);
        $result = $conn->query("SELECT id FROM members
            WHERE LOWER(TRIM(church_village)) = LOWER('$village')
              AND reg_date IS NOT NULL
              AND ABS(TIMESTAMPDIFF(SECOND, reg_date, '$created_at')) <= 2592000
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, reg_date, '$created_at')) ASC, id DESC
            LIMIT 1");
        $member = $result ? $result->fetch_assoc() : null;
        $related_member_id = (int)($member['id'] ?? 0);
    }

    if ($related_member_id < 1 && $fallback_name !== '' && $fallback_department !== '') {
        $name = $conn->real_escape_string($fallback_name);
        $department = $conn->real_escape_string($fallback_department);
        $created_at = $conn->real_escape_string($created_at);
        $order = $focus_type === 'member-registration'
            ? "ORDER BY CASE WHEN reg_date IS NULL THEN 1 ELSE 0 END, ABS(TIMESTAMPDIFF(SECOND, reg_date, '$created_at')) ASC, id DESC"
            : "ORDER BY id DESC";
        $focus_where = $focus_type === 'church-village-member'
            ? "LOWER(TRIM(church_village)) = LOWER('$department')"
            : "LOWER(TRIM(department)) = LOWER('$department')";
        $result = $conn->query("SELECT id FROM members
            WHERE LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = LOWER('$name')
              AND $focus_where
            $order
            LIMIT 1");
        $member = $result ? $result->fetch_assoc() : null;
        $related_member_id = (int)($member['id'] ?? 0);
    }

    return $related_member_id > 0
        ? $message . ' [focus-' . $focus_type . '-id:' . $related_member_id . ']'
        : $message;
}

function notification_target_tab($message, $user_type, $fallback_tab = 'dashboard', $is_treasurer = false) {
    $route = notification_route_metadata($message);
    if ($route['tab'] !== '') {
        return $route['tab'];
    }
    $tabs = notification_badge_tabs($message, $user_type, $is_treasurer);
    return $tabs[0] ?? $fallback_tab;
}

function notification_focus_message_with_financial_record_id($conn, $message, $notification_created_at, $user_type) {
    $message = (string)$message;
    if (!in_array($user_type, ['pastor', 'admin'], true)
        || preg_match('/\[financial-record-id:\s*\d+\]/i', $message)
        || !preg_match('/^(.+?)\s+Treasurer\s+\((.+?)\)\s+recorded a collection of KSh\s+([\d,]+(?:\.\d{1,2})?)/i', trim($message), $match)) {
        return $message;
    }

    $department = trim($match[1]);
    $poster_name = trim($match[2]);
    $amount = (float)str_replace(',', '', $match[3]);
    $created_at = trim((string)$notification_created_at);
    if ($department === '' || $poster_name === '' || $amount <= 0 || $created_at === '') {
        return $message;
    }

    $statement = $conn->prepare("SELECT fr.id
        FROM financial_records fr
        LEFT JOIN members m ON m.id = fr.recorded_by
        WHERE fr.department = ?
          AND fr.amount = ?
          AND LOWER(TRIM(COALESCE(NULLIF(fr.recorded_by_name, ''), CONCAT_WS(' ', m.first_name, m.last_name)))) = LOWER(TRIM(?))
          AND fr.recorded_at IS NOT NULL
          AND ABS(TIMESTAMPDIFF(SECOND, fr.recorded_at, ?)) <= 600
        ORDER BY ABS(TIMESTAMPDIFF(SECOND, fr.recorded_at, ?)) ASC, fr.id DESC
        LIMIT 1");
    if (!$statement) return $message;

    $statement->bind_param('sdsss', $department, $amount, $poster_name, $created_at, $created_at);
    $statement->execute();
    $statement->bind_result($record_id);
    $found = $statement->fetch();
    $statement->close();

    return $found && (int)$record_id > 0
        ? $message . ' [financial-record-id:' . (int)$record_id . ']'
        : $message;
}

function notification_focus_message_with_media_id($conn, $message, $notification_created_at) {
    $message = (string)$message;
    if (preg_match('/\bdaily quote\s*#\s*\d+/i', $message) || preg_match('/\bchurch highlight\s*#\s*\d+/i', $message)) {
        return $message;
    }

    if (preg_match('/\bdaily quote\b/i', $message)) {
        $media_table = 'daily_messages';
        $media_alias = 'dm';
        $media_id_column = 'dm.id';
        $media_time_column = 'dm.created_at';
        $media_type = 'daily quote';
        $author_pattern = '/^Pastor\s+(.+?)\s+has posted a new Daily Quote!?$/i';
    } elseif (preg_match('/\bchurch highlight\b/i', $message)) {
        $media_table = 'church_highlights';
        $media_alias = 'ch';
        $media_id_column = 'ch.id';
        $media_time_column = 'ch.created_at';
        $media_type = 'Church Highlight';
        $author_pattern = '/^Pastor\s+(.+?)\s+has uploaded a new Church Highlight!?$/i';
    } else {
        return $message;
    }

    $notification_timestamp = strtotime((string)$notification_created_at);
    if ($notification_timestamp === false) {
        return $message;
    }
    $notification_time_for_sql = (string)$notification_created_at;
    $author_name = '';
    if (preg_match($author_pattern, trim($message), $author_match)) {
        $author_name = trim($author_match[1]);
    }

    $find_media_id = function ($filter_author) use ($conn, $media_table, $media_alias, $media_id_column, $media_time_column, $notification_time_for_sql, $author_name, $notification_timestamp) {
        $sql = "SELECT $media_id_column, $media_time_column FROM $media_table $media_alias JOIN pastors p ON p.id = $media_alias.pastor_id WHERE $media_time_column BETWEEN DATE_SUB(?, INTERVAL 15 MINUTE) AND DATE_ADD(?, INTERVAL 15 MINUTE)";
        if ($filter_author && $author_name !== '') {
            $sql .= " AND p.first_name = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($filter_author && $author_name !== '') {
            $stmt->bind_param('sss', $notification_time_for_sql, $notification_time_for_sql, $author_name);
        } else {
            $stmt->bind_param('ss', $notification_time_for_sql, $notification_time_for_sql);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $candidate_id = 0;
        $candidate_created_at = '';
        $stmt->bind_result($candidate_id, $candidate_created_at);
        $best_id = 0;
        $best_distance = PHP_INT_MAX;
        while ($stmt->fetch()) {
            $candidate_timestamp = strtotime($candidate_created_at);
            if ($candidate_timestamp === false) {
                continue;
            }
            $distance = abs($candidate_timestamp - $notification_timestamp);
            if ($distance < $best_distance || ($distance === $best_distance && (int)$candidate_id > $best_id)) {
                $best_id = (int)$candidate_id;
                $best_distance = $distance;
            }
        }
        $stmt->close();
        return $best_id;
    };

    $media_id = $find_media_id(true);
    if (!$media_id) {
        $media_id = $find_media_id(false);
    }
    return $media_id ? $message . ' ' . $media_type . ' #' . $media_id : $message;
}

function render_tab_notification_badge($badges, $tab) {
    $count = (int)($badges[$tab] ?? 0);
    if ($count < 1) {
        return '';
    }
    return '<span class="tab-notif-badge">' . ($count > 99 ? '99+' : $count) . '</span>';
}

function render_notification_tab_glow_script($target_tab, $current_tab, $notification_messages = '') {
    if (!is_string($target_tab) || $target_tab === '' || $target_tab !== $current_tab) {
        return '';
    }

    $target_json = json_encode($target_tab, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $notification_messages = is_array($notification_messages) ? $notification_messages : [$notification_messages];
    $notification_messages = array_values(array_filter(array_map('strval', $notification_messages), function ($message) {
        return trim($message) !== '';
    }));
    $messages_json = json_encode($notification_messages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $script = <<<'HTML'
<script>
document.addEventListener("DOMContentLoaded", function () {
    var targetTab = __TARGET_TAB__;
    var notificationMessages = __NOTIFICATION_MESSAGES__;
    var targetLinks = [];
    document.querySelectorAll(".sidebar-link").forEach(function (link) {
        try {
            if (new URL(link.href, window.location.href).searchParams.get("tab") === targetTab) {
                targetLinks.push(link);
            }
        } catch (error) {}
    });
    var targetLink = targetLinks.find(function (link) {
        return link.classList.contains("active");
    }) || targetLinks[0];
    if (targetLink) {
        targetLink.classList.add("notification-tab-glow");
        var sidebar = targetLink.closest(".sidebar");
        if (sidebar && window.matchMedia("(max-width: 980px) and (orientation: portrait)").matches) {
            sidebar.classList.add("open");
        }
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                targetLink.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
            });
        });
    }

    if (!notificationMessages.length) return;
    var main = document.querySelector(".main-content");
    if (!main) return;
    var normalize = function (value) {
        return String(value || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim().replace(/\s+/g, " ");
    };
    var selectors = ".main-content tr, .main-content [data-notification-row], .main-content article, .main-content .notification-item, .main-content .member-card, .main-content .event-card, .main-content .announcement-card, .main-content .fine-card, .main-content .content-card";
    var candidates = Array.from(document.querySelectorAll(selectors)).filter(function (node) {
        return node.textContent.trim().length > 0;
    });
    var stop = new Set(["this", "that", "with", "from", "your", "have", "has", "been", "will", "when", "where", "under", "into", "only", "tab", "notification", "new", "member", "department", "church", "the", "and", "for", "his", "her", "they", "them", "you", "are", "was", "were", "sent", "received", "message", "please", "review", "view", "click", "open", "record", "alert", "ksh", "role", "to", "in", "on", "of", "by", "as", "is", "a", "an"]);
    var highlightedRows = new Set();
    function addHighlightedRow(row) {
        if (row && !highlightedRows.has(row)) {
            row.classList.add("notification-row-glow");
            highlightedRows.add(row);
        }
    }
    notificationMessages.forEach(function (notificationMessage) {
        var focusMatch = notificationMessage.match(/\[focus-(department-member|approved-member|member-registration|church-village-member)-id:\s*(\d+)\]/i);
        var legacyRegistrationMatch = notificationMessage.match(/\[member-registration-id:\s*(\d+)\]/i);
        if (focusMatch || legacyRegistrationMatch) {
            var focusType = focusMatch ? focusMatch[1].toLowerCase() : "member-registration";
            var focusId = focusMatch ? focusMatch[2] : legacyRegistrationMatch[1];
            var focusRow = main.querySelector('[data-notification-row="' + focusType + '-' + focusId + '"]');
            if (focusRow) {
                addHighlightedRow(focusRow);
                return;
            }
        }
        var villageAnnouncementMatch = notificationMessage.match(/\[village-announcement-id:\s*(\d+)\]/i);
        if (villageAnnouncementMatch) {
            var villageAnnouncementRow = main.querySelector('[data-notification-row="village-announcement-' + villageAnnouncementMatch[1] + '"]');
            if (villageAnnouncementRow) addHighlightedRow(villageAnnouncementRow);
            return;
        }
        var villageLeaderChatMatch = notificationMessage.match(/\[village-leader-chat-id:\s*(\d+)\]/i);
        if (villageLeaderChatMatch) {
            var villageLeaderChatRow = main.querySelector('[data-notification-row="village-leader-chat-' + villageLeaderChatMatch[1] + '"]');
            if (villageLeaderChatRow) addHighlightedRow(villageLeaderChatRow);
            return;
        }
        var explicitRowMatch = notificationMessage.match(/\[notification-row:([a-z0-9_-]+)\]/i);
        if (explicitRowMatch) {
            var explicitRow = main.querySelector('[data-notification-row="' + explicitRowMatch[1].toLowerCase() + '"]');
            if (explicitRow) {
                addHighlightedRow(explicitRow);
                return;
            }
        }
        var financialRecordMatch = notificationMessage.match(/\[financial-record-id:\s*(\d+)\]/i);
        if (financialRecordMatch) {
            var financialRecordRow = main.querySelector('[data-notification-row="financial-record-' + financialRecordMatch[1] + '"]');
            if (financialRecordRow) {
                addHighlightedRow(financialRecordRow);
                return;
            }
        }
        var dailyQuoteMatch = notificationMessage.match(/\bdaily quote\s*#\s*(\d+)/i);
        if (dailyQuoteMatch) {
            var dailyQuoteRow = main.querySelector('[data-notification-row="daily-quote-' + dailyQuoteMatch[1] + '"]');
            if (dailyQuoteRow) {
                addHighlightedRow(dailyQuoteRow);
                return;
            }
        }
        var highlightMatch = notificationMessage.match(/\bchurch highlight\s*#\s*(\d+)/i);
        if (highlightMatch) {
            var highlightRow = main.querySelector('[data-notification-row="church-highlight-' + highlightMatch[1] + '"]');
            if (highlightRow) {
                addHighlightedRow(highlightRow);
                return;
            }
        }
        if (/\byour roles?\b[\s\S]*\bremoved\b/i.test(notificationMessage)) {
            main.classList.add("notification-dashboard-glow");
            return;
        }
        if (/^your membership has been approved by the pastor\b/i.test(notificationMessage)) {
            var approvedGreeting = main.querySelector("[data-welcome-greeting]");
            if (approvedGreeting) {
                approvedGreeting.classList.add("notification-role-glow");
                addHighlightedRow(approvedGreeting);
            }
            return;
        }

        var assignedRole = "";
        var roleAssignmentMatch = notificationMessage.match(/you have been assigned a new role:\s*(.+)$/i);
        if (roleAssignmentMatch) {
            assignedRole = roleAssignmentMatch[1]
                .replace(/\s+by\s+(?:admin|pastor)\.?\s*$/i, "")
                .replace(/\s+under\s+.+$/i, "")
                .replace(/[.!?]+\s*$/, "")
                .trim();
        } else {
            var welcomeRoleMatch = notificationMessage.match(/\bwelcome\s+.+?\s+as\s+(.+?)\s+in\s+the\s+.+$/i);
            if (welcomeRoleMatch) assignedRole = welcomeRoleMatch[1].trim();
        }
        if (assignedRole) {
            var roleKey = assignedRole.replace(/\([^)]*\)/g, "").toLowerCase().trim().replace(/\s+/g, " ");
            var roleNode = Array.from(main.querySelectorAll("[data-welcome-role]")).find(function (node) {
                return node.getAttribute("data-welcome-role") === roleKey;
            });
            if (roleNode) {
                addHighlightedRow(main.querySelector("[data-welcome-greeting]"));
                roleNode.classList.add("notification-role-glow");
                addHighlightedRow(roleNode);
                return;
            }
        }
        var fineRuleMatch = notificationMessage.match(/\[focus-fine-rule-id:\s*(\d+)\]/i);
        if (fineRuleMatch) {
            var fineRuleRow = main.querySelector('[data-notification-row="fine-rule-' + fineRuleMatch[1] + '"]');
            if (fineRuleRow) {
                addHighlightedRow(fineRuleRow);
                return;
            }
        }
        var fineMatch = notificationMessage.match(/\bfine record\s*#\s*(\d+)/i)
            || notificationMessage.match(/\[focus-fine-id:\s*(\d+)\]/i);
        if (fineMatch) {
            var fineRow = main.querySelector('[data-notification-row="pending-fine-' + fineMatch[1] + '"]')
                || main.querySelector('[data-notification-row="oversight-fine-' + fineMatch[1] + '"]')
                || main.querySelector('[data-notification-row="paid-fine-' + fineMatch[1] + '"]')
                || main.querySelector('[data-notification-row="discipline-fine-' + fineMatch[1] + '"]')
                || main.querySelector('[data-notification-row="member-fine-' + fineMatch[1] + '"]');
            if (fineRow) {
                addHighlightedRow(fineRow);
                return;
            }
        }
        var reportMatch = notificationMessage.match(/disciplinary report[^#]{0,120}#\s*(\d+)/i);
        if (reportMatch) {
            var reportRow = main.querySelector('[data-notification-row="disciplinary-report-' + reportMatch[1] + '"]');
            if (reportRow) {
                addHighlightedRow(reportRow);
                return;
            }
        }

        var exact = [];
        var patterns = [
            /new member registration:\s*(.+?)\s+in\s+.+?\s+department\b/i,
            /^(.+?)\s+has been registered as a new sunday school member\b/i,
            /new pastor registration:\s*(.+?)\s+is pending\b/i,
            /fine issued to\s+(.+?):\s*ksh\b/i,
            /new appointment booking:\s*(.+?)\s+booked an appointment\b/i,
            /^(.+?)\s+has been appointed as\b/i,
            /^(.+?)\s+has been assigned the role\b/i
        ];
        for (var i = 0; i < patterns.length; i++) {
            var match = notificationMessage.match(patterns[i]);
            if (match && match[1]) {
                exact.push(normalize(match[1]));
                break;
            }
        }
        var quoted = notificationMessage.match(/["']([^"']{4,})["']/g) || [];
        quoted.forEach(function (value) {
            var needle = normalize(value.slice(1, -1));
            if (needle.length >= 4) exact.push(needle);
        });

        var message = normalize(notificationMessage);
        var tokens = message.split(" ").filter(function (word, index, all) {
            return word.length >= 4 && !stop.has(word) && all.indexOf(word) === index;
        });
        var best = null;
        var bestScore = 0;
        var bestLength = Infinity;
        var tied = false;
        candidates.forEach(function (node) {
            var text = normalize(node.textContent);
            var score = 0;
            if (exact.length) {
                exact.forEach(function (needle) {
                    if (needle && text.indexOf(needle) !== -1) score += 100 + needle.length;
                });
            } else {
                tokens.forEach(function (token) {
                    if (text.indexOf(token) !== -1) score++;
                });
            }
            if (score > bestScore || (score === bestScore && score > 0 && text.length < bestLength)) {
                best = node;
                bestScore = score;
                bestLength = text.length;
                tied = false;
            } else if (score && score === bestScore && text.length === bestLength && node !== best) {
                tied = true;
            }
        });
        if (best && !tied && (exact.length ? bestScore >= 100 : bestScore >= 2)) {
            addHighlightedRow(best);
        }
    });

    var firstHighlightedRow = highlightedRows.values().next().value;
    if (firstHighlightedRow) {
        firstHighlightedRow.scrollIntoView({ behavior: "smooth", block: "center" });
    }
}, { once: true });
</script>
HTML;

    return str_replace(
        ['__TARGET_TAB__', '__NOTIFICATION_MESSAGES__'],
        [$target_json, $messages_json],
        $script
    );
}

function notification_target_group($message, $user_type) {
    $route = notification_route_metadata($message);
    if ($route['group'] !== '') {
        return $route['group'];
    }

    $msg = strtolower($message);
    if (in_array($user_type, ['pastor', 'admin'], true)
        && strpos($msg, 'village leaders chat message from ') !== false) {
        return 'village_leaders';
    }
    $fine_department = '';
    if ($user_type === 'member'
        && preg_match('/^financial record submitted for chairperson review in\s+(.+?)\.\s*KSh/i', $message, $finance_department_match)) {
        $fine_department = trim($finance_department_match[1]);
    } elseif ($user_type === 'member'
        && preg_match('/^financial record disbursed to members in\s+(.+?):/i', $message, $finance_department_match)) {
        $fine_department = trim($finance_department_match[1]);
    }
    if ($user_type === 'member'
        && strpos($msg, 'fine payment awaiting treasurer verification:') !== false
        && preg_match('/fine payment awaiting treasurer verification:.*?\bin\s+(.+?)\s+is paid/i', $message, $fine_department_match)) {
        $fine_department = trim($fine_department_match[1]);
    } elseif ($user_type === 'member'
        && strpos($msg, 'discipline master fine payment declined in ') !== false
        && preg_match('/discipline master fine payment declined in\s+(.+?):/i', $message, $fine_department_match)) {
        $fine_department = trim($fine_department_match[1]);
    } elseif ($user_type === 'member'
        && strpos($msg, 'discipline master fine payment update:') !== false
        && preg_match('/discipline master fine payment update:.*?\bin\s+(.+?)\s+department was marked paid/i', $message, $fine_department_match)) {
        $fine_department = trim($fine_department_match[1]);
    }
    if ($fine_department !== '') {
        $department_groups = [
            'youths' => 'youths',
            'youth ministry' => 'youths',
            'youth' => 'youths',
            'women ministry' => 'womens_ministry',
            'womens ministry' => 'womens_ministry',
            "women's ministry" => 'womens_ministry',
            'women' => 'womens_ministry',
            'elders' => 'elders',
            'elder ministry' => 'elders',
            'elder' => 'elders',
            'sunday school' => 'sunday_school',
            'building' => 'building_construction',
            'building & construction' => 'building_construction',
            'building and construction' => 'building_construction',
        ];
        return $department_groups[strtolower($fine_department)] ?? '';
    }
    if ($user_type === 'member'
        && preg_match('/initiated a fine payment in\s+(.+?)\s+department\./i', $message, $fine_department_match)) {
        $department_groups = [
            'youths' => 'youths',
            'women ministry' => 'womens_ministry',
            'womens ministry' => 'womens_ministry',
            'elders' => 'elders',
            'sunday school' => 'sunday_school',
            'building & construction' => 'building_construction',
        ];
        return $department_groups[strtolower(trim($fine_department_match[1]))] ?? '';
    }
    if ($user_type === 'member'
        && preg_match('/^new member added to your department:.*\(([^)]+)\)/i', $message, $department_match)) {
        $department_groups = [
            'youths' => 'youths',
            'women ministry' => 'womens_ministry',
            'womens ministry' => 'womens_ministry',
            'elders' => 'elders',
            'sunday school' => 'sunday_school',
            'building & construction' => 'building_construction'
        ];
        return $department_groups[strtolower(trim($department_match[1]))] ?? '';
    }
    if (preg_match('/department leaders chat message in ([a-z_]+)/', $msg, $matches)) {
        return $matches[1];
    }
    if (($user_type === 'pastor' || $user_type === 'admin')
        && strpos($msg, 'worship leadership chat') !== false) {
        return 'worship_leaders';
    }
    return '';
}
?>


