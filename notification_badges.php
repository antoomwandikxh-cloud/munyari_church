<?php
function notification_badge_tabs($message, $user_type) {
    $msg  = strtolower($message);
    $tabs = [];

    if (strpos($msg, 'disciplinary report pending chairman review') !== false
        || strpos($msg, 'discipline master has reported a member') !== false) {
        return $user_type === 'member' ? ['manage_department'] : ['discipline_reports'];
    }
    if (strpos($msg, 'disciplinary report forwarded to pastor') !== false) {
        return ['discipline_reports'];
    }

    if (strpos($msg, 'fine') !== false) {
        if (strpos($msg, 'initiated a fine payment') !== false && strpos($msg, 'financial records') !== false) {
            return ['financials'];
        }
        return [$user_type === 'member' ? 'my_fines' : 'discipline_reports'];
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

    if (strpos($msg, 'new member registration pending approval') !== false) {
        $tabs[] = 'manage_members';
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


        // Village announcement notification -> desired_roles tab (where members see their village)
    if (strpos($msg, 'new village announcement') !== false && strpos($msg, 'village leader') !== false) {
        if ($user_type === 'member') {
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

function render_tab_notification_badge($badges, $tab) {
    $count = (int)($badges[$tab] ?? 0);
    if ($count < 1) {
        return '';
    }
    return '<span class="tab-notif-badge">' . ($count > 99 ? '99+' : $count) . '</span>';
}

function render_notification_tab_glow_script($target_tab, $current_tab, $notification_message = '') {
    if (!is_string($target_tab) || $target_tab === '' || $target_tab !== $current_tab) {
        return '';
    }

    $target_json = json_encode($target_tab, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $message_json = json_encode((string)$notification_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $script = <<<'HTML'
<script>
document.addEventListener("DOMContentLoaded", function () {
    var targetTab = __TARGET_TAB__;
    var notificationMessage = __NOTIFICATION_MESSAGE__;
    document.querySelectorAll(".sidebar-link").forEach(function (link) {
        try {
            if (new URL(link.href, window.location.href).searchParams.get("tab") === targetTab) {
                link.classList.add("notification-tab-glow");
            }
        } catch (error) {}
    });

    if (!notificationMessage) return;
    var main = document.querySelector(".main-content");
    if (!main) return;
    var fineMatch = notificationMessage.match(/\bfine record\s*#\s*(\d+)/i);
    if (fineMatch) {
        var fineRow = main.querySelector('[data-notification-row="member-fine-' + fineMatch[1] + '"]');
        if (fineRow) {
            fineRow.classList.add("notification-row-glow");
            fineRow.scrollIntoView({ behavior: "smooth", block: "center" });
            return;
        }
    }
    var reportMatch = notificationMessage.match(/disciplinary report[^#]{0,120}#\s*(\d+)/i);
    if (reportMatch) {
        var reportRow = main.querySelector('[data-notification-row="disciplinary-report-' + reportMatch[1] + '"]');
        if (reportRow) {
            reportRow.classList.add("notification-row-glow");
            reportRow.scrollIntoView({ behavior: "smooth", block: "center" });
            return;
        }
    }
    var normalize = function (value) {
        return String(value || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim().replace(/\s+/g, " ");
    };
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

    var selectors = ".main-content tr, .main-content [data-notification-row], .main-content article, .main-content .notification-item, .main-content .member-card, .main-content .event-card, .main-content .announcement-card, .main-content .fine-card, .main-content .content-card";
    var candidates = Array.from(document.querySelectorAll(selectors)).filter(function (node) {
        return node.textContent.trim().length > 0;
    });

    var stop = new Set(["this", "that", "with", "from", "your", "have", "has", "been", "will", "when", "where", "under", "into", "only", "tab", "notification", "new", "member", "department", "church", "the", "and", "for", "his", "her", "they", "them", "you", "are", "was", "were", "sent", "received", "message", "please", "review", "view", "click", "open", "record", "alert", "ksh", "role", "to", "in", "on", "of", "by", "as", "is", "a", "an"]);
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
    if (!best || tied || (exact.length ? bestScore < 100 : bestScore < 2)) return;
    best.classList.add("notification-row-glow");
    best.scrollIntoView({ behavior: "smooth", block: "center" });
}, { once: true });
</script>
HTML;

    return str_replace(
        ['__TARGET_TAB__', '__NOTIFICATION_MESSAGE__'],
        [$target_json, $message_json],
        $script
    );
}

function notification_target_group($message, $user_type) {
    $msg = strtolower($message);
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


