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
    ?>
    <section class="content-card" style="margin-top:24px;padding:0;overflow:hidden;border-top:3px solid #d6a832;">
        <header style="padding:14px 16px;border-bottom:1px solid var(--border-color);">
            <h2 style="margin:0;color:var(--text-main);font-size:1.05rem;">Village Leaders Chat</h2>
            <p style="margin:5px 0 0;color:var(--text-muted);font-size:.86rem;">Shared messages from the leaders of Akoritho, Philadelphia, and Bethsaida.</p>
        </header>
        <div style="display:flex;gap:10px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--border-color);">
            <?php if ($leaders): while ($leader = $leaders->fetch_assoc()):
                $photo_file = trim((string)($leader['profile_picture'] ?? ''));
                $photo_url = $photo_file !== '' ? 'uploads/' . rawurlencode(basename($photo_file)) : 'uploads/default_avatar.png';
                $leader_name = trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? ''));
            ?>
                <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-lighter);">
                    <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>" alt="View <?= htmlspecialchars($leader_name, ENT_QUOTES, 'UTF-8') ?> profile photo" title="Click to view profile photo" onclick="viewProfileImage(this.src);" onerror="this.onerror=null;this.src='uploads/default_avatar.png';" style="width:38px;height:38px;flex:0 0 38px;border-radius:50%;object-fit:cover;cursor:zoom-in;border:2px solid #d6a832;">
                    <span style="min-width:0;color:var(--text-main);font-size:.84rem;"><strong><?= htmlspecialchars($leader_name, ENT_QUOTES, 'UTF-8') ?></strong><br><span style="color:var(--text-muted);font-size:.78rem;"><?= htmlspecialchars($leader['church_village'] ?? '', ENT_QUOTES, 'UTF-8') ?> Village</span></span>
                </div>
            <?php endwhile; endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;padding:16px;max-height:440px;overflow:auto;background:var(--bg-main);">
            <?php if ($messages && $messages->num_rows > 0): while ($message = $messages->fetch_assoc()):
                $photo_file = trim((string)($message['profile_picture'] ?? ''));
                $photo_url = $photo_file !== '' ? 'uploads/' . rawurlencode(basename($photo_file)) : 'uploads/default_avatar.png';
                $sender_name = trim(($message['first_name'] ?? '') . ' ' . ($message['last_name'] ?? ''));
            ?>
                <article data-notification-row="village-leader-chat-<?= (int)$message['id'] ?>" style="display:flex;align-items:flex-end;gap:9px;max-width:min(760px,100%);">
                    <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>" alt="View <?= htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8') ?> profile photo" title="Click to view profile photo" onclick="viewProfileImage(this.src);" onerror="this.onerror=null;this.src='uploads/default_avatar.png';" style="width:38px;height:38px;flex:0 0 38px;border-radius:50%;object-fit:cover;cursor:zoom-in;border:1px solid var(--border-color);">
                    <div style="min-width:120px;padding:10px 13px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-lighter);color:var(--text-main);">
                        <div style="display:flex;gap:12px;justify-content:space-between;align-items:baseline;flex-wrap:wrap;margin-bottom:5px;">
                            <strong style="font-size:.86rem;"><?= htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span style="font-size:.76rem;color:var(--text-muted);"><?= htmlspecialchars($message['church_village'] ?? '', ENT_QUOTES, 'UTF-8') ?> Village</span>
                        </div>
                        <div style="white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.45;"><?= htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8') ?></div>
                        <time datetime="<?= htmlspecialchars($message['created_at'], ENT_QUOTES, 'UTF-8') ?>" style="display:block;margin-top:6px;text-align:right;font-size:.72rem;color:var(--text-muted);">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($message['created_at']))) ?>
                        </time>
                    </div>
                </article>
            <?php endwhile; else: ?>
                <p style="margin:0;color:var(--text-muted);text-align:center;">No village leader messages yet.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
?>
