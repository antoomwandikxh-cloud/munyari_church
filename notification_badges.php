<?php
function notification_badge_tabs($message, $user_type) {
    $msg  = strtolower($message);
    $tabs = [];

    // ─── 1. SECRETARY REPLY TO QUERY (most specific – must come first) ──────
    // e.g. "Reply to your announcement query from General Church Secretary rose: ..."
    if (strpos($msg, 'reply to your announcement query') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'query_announcements';
        } elseif ($user_type === 'pastor') {
            $tabs[] = 'general_leadership';
        } else {
            $tabs[] = 'general_leadership';
        }
        return array_values(array_unique($tabs)); // early exit – don't bleed into generic announcement checks
    }

    // ─── 2. ANNOUNCEMENT QUERY / QUERIED ────────────────────────────────────
    // e.g. "Announcement Queried message from General Church Secretary ..."
    if (strpos($msg, 'announcement query') !== false || strpos($msg, 'announcement queried') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'query_announcements';
        } else {
            $tabs[] = 'general_leadership';
        }
        return array_values(array_unique($tabs)); // early exit
    }

    // ─── 3. LEADERSHIP CHAT MESSAGES ────────────────────────────────────────
    if (strpos($msg, 'leadership chat') !== false) {
        if (strpos($msg, 'worship') !== false) {
            $tabs[] = $user_type === 'member' ? 'worship_leadership_chat' : 'general_leadership';
        } elseif (strpos($msg, 'building') !== false || strpos($msg, 'construction') !== false) {
            $tabs[] = $user_type === 'member' ? 'building_leadership_chat' : 'general_leadership';
        } else {
            // youths, womens, sunday school, general church, etc.
            $tabs[] = $user_type === 'member' ? 'general_leader_chat' : 'general_leadership';
        }
        return array_values(array_unique($tabs)); // early exit
    }

    // ─── 4. ACCOUNT / REGISTRATION ──────────────────────────────────────────
    if (strpos($msg, 'account') !== false && (strpos($msg, 'approved') !== false || strpos($msg, 'created') !== false)) {
        $tabs[] = 'dashboard';
        $tabs[] = 'settings';
    }
    if (strpos($msg, 'pastor') !== false && strpos($msg, 'registration') !== false) {
        $tabs[] = $user_type === 'admin' ? 'pastors' : 'dashboard';
    }
    if ((strpos($msg, 'member') !== false && strpos($msg, 'worship') === false) || 
        (strpos($msg, 'registration') !== false && strpos($msg, 'pastor') === false)) {
        $tabs[] = $user_type === 'pastor' ? 'manage_members' : 'members';
    }

    // ─── 5. ROLE / APPOINTMENT ──────────────────────────────────────────────
    if (strpos($msg, 'role') !== false || strpos($msg, 'appointment to the role') !== false) {
        $tabs[] = 'assign_roles';
        if ($user_type === 'member') {
            $tabs[] = 'settings';
        }
    }
    if (strpos($msg, 'appointment') !== false && strpos($msg, 'role') === false) {
        $tabs[] = $user_type === 'member' ? 'contact_pastor' : 'appointments_messages';
    }

    // ─── 6. DAILY QUOTE / HIGHLIGHTS ────────────────────────────────────────
    if (strpos($msg, 'daily quote') !== false || strpos($msg, 'highlight') !== false || strpos($msg, 'inspiration') !== false) {
        $tabs[] = $user_type === 'admin' ? 'content' : 'inspiration_highlights';
    }

    // ─── 7. GENERAL ANNOUNCEMENTS (broad – must come after specific query checks) ─
    if (strpos($msg, 'announcement') !== false || strpos($msg, 'department') !== false || strpos($msg, 'transfer') !== false) {
        if ($user_type === 'member') {
            $tabs[] = 'announcements';
            $tabs[] = 'dept_announcements';
            $tabs[] = 'elder_announcements';
        } else {
            $tabs[] = 'departments';
            if (strpos($msg, 'announcement') !== false) {
                $tabs[] = 'general_announcements';
            }
            if (strpos($msg, 'general church') !== false || strpos($msg, 'senior church elder') !== false) {
                $tabs[] = 'general_leadership';
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
            $tabs[] = $user_type === 'member' ? 'leader_chat' : 'appointments_messages';
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

    return array_values(array_unique($tabs));
}

function build_tab_notification_badges($conn, $user_id, $user_type, $current_tab) {
    $user_id      = (int)$user_id;
    $user_type_sql = $conn->real_escape_string($user_type);
    $result = $conn->query("SELECT id, message FROM notifications WHERE user_id = $user_id AND user_type = '$user_type_sql' AND is_read = 0");
    $badges   = [];
    $read_ids = [];

    if ($result) {
        while ($notification = $result->fetch_assoc()) {
            $tabs = notification_badge_tabs($notification['message'], $user_type);
            foreach ($tabs as $target_tab) {
                if (!isset($badges[$target_tab])) {
                    $badges[$target_tab] = 0;
                }
                $badges[$target_tab]++;
                if ($current_tab === $target_tab) {
                    $read_ids[] = (int)$notification['id'];
                }
            }
        }
    }

    if (!empty($read_ids)) {
        $ids = implode(',', array_unique($read_ids));
        $conn->query("UPDATE notifications SET is_read = 1 WHERE id IN ($ids) AND user_id = $user_id AND user_type = '$user_type_sql'");
        unset($badges[$current_tab]);
    }

    return $badges;
}

function notification_target_tab($message, $user_type, $fallback_tab = 'dashboard') {
    $tabs = notification_badge_tabs($message, $user_type);
    return $tabs[0] ?? $fallback_tab;
}
?>
