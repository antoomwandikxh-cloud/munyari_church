<?php
function ensure_village_leader_chat_schema($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS village_leader_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_village_leader_messages_created (created_at)
        )
    ");
}

function village_leader_chat_styles() {
    ?>
    <style>
        .village-chat-panel { padding:0; overflow:hidden; border-radius:8px; }
        .village-chat-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border-color); background:var(--bg-lighter); }
        .village-chat-member-list { display:flex; gap:16px; overflow-x:auto; padding:12px 16px; border-bottom:1px solid var(--border-color); background:var(--bg-card); }
        .village-chat-member { display:flex; align-items:center; gap:8px; flex:0 0 auto; min-width:0; }
        .village-chat-thread { display:flex; flex-direction:column; gap:9px; min-height:280px; max-height:min(60vh,560px); overflow:auto; padding:16px; background:var(--bg-main); }
        .village-chat-message { display:flex; align-items:flex-end; gap:8px; align-self:flex-start; width:fit-content; max-width:86%; scroll-margin:24px; }
        .village-chat-message.is-own { align-self:flex-end; flex-direction:row-reverse; }
        .village-chat-avatar { width:36px; height:36px; flex:0 0 36px; border-radius:50%; object-fit:cover; border:1px solid var(--border-color); cursor:zoom-in; }
        .village-chat-bubble { min-width:92px; max-width:100%; padding:9px 12px 7px; border:1px solid var(--border-color); border-radius:13px 13px 13px 3px; background:var(--bg-lighter); color:var(--text-main); box-shadow:0 1px 2px rgba(0,0,0,.08); }
        .village-chat-message.is-own .village-chat-bubble { border-radius:13px 13px 3px 13px; border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.14); }
        .village-chat-bubble-head { display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:3px 12px; margin-bottom:4px; }
        .village-chat-text { white-space:pre-wrap; overflow-wrap:anywhere; line-height:1.45; }
        .village-chat-time { display:block; margin-top:4px; text-align:right; color:var(--text-muted); font-size:.7rem; }
        .village-chat-composer { display:flex; align-items:flex-end; gap:9px; padding:11px 12px; border-top:1px solid var(--border-color); background:var(--bg-lighter); }
        .village-chat-composer textarea { flex:1 1 180px; min-width:0; resize:vertical; padding:10px 13px; border:1px solid var(--border-color); border-radius:18px; background:var(--bg-main); color:var(--text-main); font:inherit; }
        .village-chat-send { flex:0 0 auto; min-height:42px; padding:0 16px; border:0; border-radius:22px; background:#168c66; color:#fff; font-weight:700; cursor:pointer; }
        @media (max-width:560px) {
            .village-chat-thread { min-height:240px; max-height:56vh; padding:12px 9px; }
            .village-chat-message { max-width:94%; gap:6px; }
            .village-chat-avatar { width:30px; height:30px; flex-basis:30px; }
            .village-chat-bubble { min-width:74px; padding:8px 10px 6px; }
            .village-chat-composer { flex-wrap:wrap; }
            .village-chat-composer textarea { flex-basis:170px; }
            .village-chat-send { min-height:40px; }
        }
    </style>
    <?php
}

function village_leader_chat_message_result($conn, $focus_messages = []) {
    $focus_ids = [];
    foreach ((array)$focus_messages as $focus_message) {
        if (preg_match('/\[village-leader-chat-id:\s*(\d+)\]/i', (string)$focus_message, $match)) {
            $focus_ids[] = (int)$match[1];
        }
    }
    $focus_ids = array_values(array_unique(array_filter($focus_ids)));
    $recent_ids = 'vlm.id IN (SELECT recent_messages.id FROM (SELECT id FROM village_leader_messages ORDER BY id DESC LIMIT 200) AS recent_messages)';
    $message_filter = $focus_ids
        ? '((' . $recent_ids . ') OR vlm.id IN (' . implode(',', $focus_ids) . '))'
        : $recent_ids;
    return $conn->query("SELECT vlm.id, vlm.message, vlm.created_at, m.first_name, m.last_name, m.church_village, m.profile_picture FROM village_leader_messages vlm JOIN members m ON m.id = vlm.sender_id WHERE m.is_village_leader = 1 AND m.is_approved = 1 AND LOWER(TRIM(COALESCE(m.church_village, ''))) IN ('akoritho', 'philadelphia', 'bethsaida') AND $message_filter ORDER BY vlm.id ASC");
}

function render_village_leaders_chat_monitor($conn, $focus_messages = []) {
    $leaders = $conn->query("SELECT id, first_name, last_name, church_village, profile_picture FROM members WHERE is_village_leader = 1 AND is_approved = 1 AND LOWER(TRIM(COALESCE(church_village, ''))) IN ('akoritho', 'philadelphia', 'bethsaida') ORDER BY FIELD(LOWER(TRIM(church_village)), 'akoritho', 'philadelphia', 'bethsaida'), first_name, last_name");
    $messages = village_leader_chat_message_result($conn, $focus_messages);
    village_leader_chat_styles();
    ?>
    <section class="content-card village-chat-panel" style="margin-top:24px;border-top:3px solid #d6a832;">
        <header class="village-chat-header">
            <div>
                <h2 style="margin:0;color:var(--text-main);font-size:1.05rem;">Village Leaders</h2>
                <p style="margin:4px 0 0;color:var(--text-muted);font-size:.82rem;">Akoritho · Philadelphia · Bethsaida</p>
            </div>
            <span style="font-size:.8rem;color:var(--text-muted);">Group chat</span>
        </header>
        <div class="village-chat-member-list">
            <?php if ($leaders): while ($leader = $leaders->fetch_assoc()):
                $photo_file = trim((string)($leader['profile_picture'] ?? ''));
                $photo_url = $photo_file !== '' ? 'uploads/' . rawurlencode(basename($photo_file)) : 'uploads/default_avatar.png';
                $leader_name = trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? ''));
            ?>
                <div class="village-chat-member">
                    <img class="village-chat-avatar" src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>" alt="View <?= htmlspecialchars($leader_name, ENT_QUOTES, 'UTF-8') ?> profile photo" title="Click to view profile photo" onclick="viewProfileImage(this.src);" onerror="this.onerror=null;this.src='uploads/default_avatar.png';" style="border:2px solid #d6a832;">
                    <span style="min-width:0;color:var(--text-main);font-size:.82rem;"><strong><?= htmlspecialchars($leader_name, ENT_QUOTES, 'UTF-8') ?></strong><br><span style="color:var(--text-muted);font-size:.76rem;"><?= htmlspecialchars($leader['church_village'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></span>
                </div>
            <?php endwhile; endif; ?>
        </div>
        <div class="village-chat-thread">
            <?php if ($messages && $messages->num_rows > 0): while ($message = $messages->fetch_assoc()):
                $photo_file = trim((string)($message['profile_picture'] ?? ''));
                $photo_url = $photo_file !== '' ? 'uploads/' . rawurlencode(basename($photo_file)) : 'uploads/default_avatar.png';
                $sender_name = trim(($message['first_name'] ?? '') . ' ' . ($message['last_name'] ?? ''));
            ?>
                <article class="village-chat-message" data-notification-row="village-leader-chat-<?= (int)$message['id'] ?>">
                    <img class="village-chat-avatar" src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>" alt="View <?= htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8') ?> profile photo" title="Click to view profile photo" onclick="viewProfileImage(this.src);" onerror="this.onerror=null;this.src='uploads/default_avatar.png';">
                    <div class="village-chat-bubble">
                        <div class="village-chat-bubble-head">
                            <strong style="font-size:.84rem;"><?= htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span style="font-size:.74rem;color:var(--text-muted);"><?= htmlspecialchars($message['church_village'] ?? '', ENT_QUOTES, 'UTF-8') ?> Village</span>
                        </div>
                        <div class="village-chat-text"><?= htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8') ?></div>
                        <time class="village-chat-time" datetime="<?= htmlspecialchars($message['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($message['created_at']))) ?>
                        </time>
                    </div>
                </article>
            <?php endwhile; else: ?>
                <p style="margin:0;color:var(--text-muted);text-align:center;">No village leader messages yet.</p>
            <?php endif; ?>
        </div>
    </section>
    <script>
    (function () {
        var thread = document.querySelector('.village-chat-thread');
        if (thread) thread.scrollTop = thread.scrollHeight;
    })();
    </script>
    <?php
}
?>
