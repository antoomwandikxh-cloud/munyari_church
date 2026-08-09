<?php
/**
 * Church Buildings & Construction Panel
 * - For ALL members: view public posts and progress updates from the Chairperson
 * - For Building Chairperson/Vice: also shows form to post updates AND schedule construction
 * - Triggered by tabs: church_buildings OR manage_building (both render here)
 */
if ($tab == 'church_buildings' || $tab == 'manage_building'):

    $posts        = $conn->query("
        SELECT p.*, m.first_name, m.last_name, m.church_role, m.gender
        FROM building_posts p
        JOIN members m ON p.author_id = m.id
        ORDER BY p.created_at DESC
    ");
    $progress_log = $conn->query("
        SELECT bp.*, m.first_name, m.last_name, m.church_role, m.gender
        FROM building_progress bp
        JOIN members m ON bp.chairperson_id = m.id
        ORDER BY bp.created_at DESC
    ");
?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
        <h1 style="margin:0;">🏗️ Church Buildings</h1>
        <?php if ($is_building_chairperson): ?>
            <span style="background:rgba(139,92,246,0.15); color:#8b5cf6; padding:4px 12px; border-radius:20px; font-size:0.85rem; font-weight:600;">Chairperson View</span>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" style="margin-bottom:20px;">✅ <?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>

    <!-- ===== CHAIRPERSON/VICE: POSTING & SCHEDULE PANEL ===== -->
    <?php if ($is_building_chairperson): ?>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px;">

        <!-- Post a public update -->
        <div class="card" style="border-left:4px solid var(--primary);">
            <h3 style="margin-top:0; color:var(--primary);">📢 Post a Building Update</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_building_post" value="1">
                <div class="form-group">
                    <textarea name="post_content" class="form-control" rows="4"
                        placeholder="Write an update for all church members about the building progress..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Picture</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <label for="building_post_camera" class="camera-upload-trigger" data-input="building_post_camera" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h2.2l1.4-2.1A2 2 0 0110.3 4h3.4a2 2 0 011.7.9L16.8 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Take Picture
                        </label>
                        <label for="building_post_upload" class="camera-upload-trigger" data-input="building_post_upload" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0-12l-4 4m4-4l4 4"></path></svg>
                            Upload Picture
                        </label>
                    </div>
                    <input id="building_post_camera" type="file" name="building_post_camera" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                    <input id="building_post_upload" type="file" name="building_post_upload" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                    <small class="selected-upload-name" data-for="building_post_camera,building_post_upload" style="display:block;margin-top:6px;color:var(--text-muted);"></small>
                </div>
                <button type="submit" class="btn-submit">Post to All Members</button>
            </form>
        </div>

        <!-- Schedule construction -->
        <div class="card" style="border-left:4px solid #8b5cf6;">
            <h3 style="margin-top:0; color:#8b5cf6;">📅 Schedule Construction</h3>
            <form method="POST">
                <input type="hidden" name="add_building_progress" value="1">
                <div class="form-group">
                    <label>Date Scheduled</label>
                    <input type="date" name="date_scheduled" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Current Progress Status</label>
                    <input type="text" name="progress_status" class="form-control" placeholder="e.g. We are roofing" required>
                </div>
                <div class="form-group">
                    <label>Materials Needed</label>
                    <textarea name="materials_needed" class="form-control" rows="2"
                        placeholder="e.g. 50 bags of cement, 20 iron sheets..." required></textarea>
                </div>
                <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">Save Schedule</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== CONSTRUCTION PROGRESS LOG (visible to ALL members) ===== -->
    <?php if ($progress_log && $progress_log->num_rows > 0): ?>
    <div class="card" style="margin-bottom:30px;">
        <h2 style="margin-top:0;">📋 Construction Progress Log</h2>
        <div style="display:flex; flex-direction:column; gap:14px;">
            <?php while ($prog = $progress_log->fetch_assoc()):
                $ptitle = get_building_chairperson_title($prog['gender'], $prog['church_role']);
            ?>
            <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:16px; display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start;">
                <div style="flex:1; min-width:200px;">
                    <div style="font-weight:700; color:var(--primary); font-size:1rem; margin-bottom:4px;">
                        <?= htmlspecialchars($prog['progress_status']) ?>
                    </div>
                    <div style="font-size:0.82rem; color:var(--text-muted);">
                        Posted by <strong><?= htmlspecialchars($prog['first_name'] . ' ' . $prog['last_name']) ?></strong> · <?= htmlspecialchars($ptitle) ?>
                    </div>
                    <div style="margin-top:10px; font-size:0.9rem; color:var(--text-main);">
                        <strong>Materials:</strong> <?= nl2br(htmlspecialchars($prog['materials_needed'])) ?>
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px; min-width:110px;">
                    <span style="background:rgba(139,92,246,0.12); color:#8b5cf6; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:600;">Scheduled</span>
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?= date('M j, Y', strtotime($prog['date_scheduled'])) ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== PUBLIC BUILDING POSTS (visible to ALL members) ===== -->
    <h2 style="margin-bottom:16px;">📣 Building Updates from Leadership</h2>
    <div style="display:flex; flex-direction:column; gap:20px;">
        <?php if ($posts && $posts->num_rows > 0):
            while ($post = $posts->fetch_assoc()):
                $author_title = get_building_chairperson_title($post['gender'], $post['church_role']);
        ?>
            <div class="card" style="border-left:3px solid var(--primary);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                    <div>
                        <div style="font-weight:700; font-size:1rem; color:var(--text-main);">
                            <?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?>
                        </div>
                        <div style="color:var(--primary); font-size:0.82rem; font-weight:600;">
                            <?= htmlspecialchars($author_title) ?>
                        </div>
                    </div>
                    <div style="color:var(--text-muted); font-size:0.82rem; display:flex; align-items:center; gap:5px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                    </div>
                </div>
                <div style="color:var(--text-main); line-height:1.7; white-space:pre-wrap; font-size:0.95rem;">
                    <?= htmlspecialchars($post['post_content']) ?>
                </div>
                <?php if (!empty($post['image_path'])): ?>
                    <div style="margin-top:12px;">
                        <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Building Post Image" style="max-width:100%; max-height:400px; border-radius:8px; border:1px solid var(--border-color); object-fit:contain;">
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; else: ?>
            <div class="card" style="text-align:center; padding:50px 30px;">
                <svg width="52" height="52" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    style="color:var(--text-muted); margin-bottom:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <h3 style="margin:0 0 8px; color:var(--text-main);">No Building Updates Yet</h3>
                <p style="margin:0; color:var(--text-muted);">
                    <?= $is_building_chairperson ? 'Use the form above to post the first update.' : 'When the building chairperson posts updates, they will appear here.' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab == 'building_leadership_chat' && $is_building_leader):
    $chat_msgs = $conn->query("
        SELECT c.*,
               COALESCE(m.first_name, p.first_name) as first_name,
               COALESCE(m.last_name, p.last_name) as last_name,
               COALESCE(m.church_role, 'Pastor') as church_role
        FROM building_leadership_chat c
        LEFT JOIN members m ON c.sender_id = m.id AND c.sender_type = 'member'
        LEFT JOIN pastors p ON c.sender_id = p.id AND c.sender_type = 'pastor'
        ORDER BY c.created_at ASC
    ");
?>
    <div class="card" style="height:calc(100vh - 150px); display:flex; flex-direction:column;">
        <h2 style="margin-top:0; border-bottom:1px solid var(--border-color); padding-bottom:15px;">
            💬 Building Leadership Chat
        </h2>
        <div style="flex:1; overflow-y:auto; padding:10px 0; display:flex; flex-direction:column; gap:14px;" id="buildingChatBox">
            <?php if ($chat_msgs && $chat_msgs->num_rows > 0):
                while ($msg = $chat_msgs->fetch_assoc()):
                    $is_me = ($msg['sender_id'] == $member_id && $msg['sender_type'] == 'member');
            ?>
                <div style="display:flex; flex-direction:column; align-items:<?= $is_me ? 'flex-end' : 'flex-start' ?>;">
                    <span style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">
                        <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                        <span style="background:var(--border-color); padding:2px 7px; border-radius:4px; margin-left:5px;">
                            <?= htmlspecialchars(role_display_label($msg['church_role'] ?? 'Pastor')) ?>
                        </span>
                    </span>
                    <div style="background:<?= $is_me ? 'var(--primary)' : 'var(--bg-card)' ?>; color:<?= $is_me ? 'white' : 'var(--text-main)' ?>; padding:10px 16px; border-radius:<?= $is_me ? '18px 18px 4px 18px' : '18px 18px 18px 4px' ?>; max-width:75%; border:1px solid var(--border-color); word-break:break-word;">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </div>
                    <span style="font-size:0.7rem; color:var(--text-muted); margin-top:4px;">
                        <?= date('M j, g:i A', strtotime($msg['created_at'])) ?>
                    </span>
                </div>
            <?php endwhile; else: ?>
                <p style="text-align:center; color:var(--text-muted); margin-top:30px;">No leadership chat messages yet. Start the conversation!</p>
            <?php endif; ?>
        </div>
        <form method="POST" style="margin-top:15px; display:flex; gap:10px;">
            <input type="hidden" name="send_building_chat" value="1">
            <input type="text" name="chat_message" class="form-control"
                placeholder="Type a message to building leaders..." required style="margin-bottom:0;">
            <button type="submit" class="btn-submit" style="white-space:nowrap; padding:0 22px;">Send</button>
        </form>
    </div>
    <script>
        const chatBox = document.getElementById('buildingChatBox');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
<?php endif; ?>
