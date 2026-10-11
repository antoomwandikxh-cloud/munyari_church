<?php
/**
 * WhatsApp-Style Voice Note Helper for Munyari Church
 * Handles audio recording, saving, and WhatsApp-style audio player rendering.
 */

function ensure_voice_notes_dir() {
    $dir = __DIR__ . '/uploads/voice_notes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

/**
 * Save recorded voice note from either base64 POST data or $_FILES upload
 * Returns relative path e.g. 'uploads/voice_notes/pastor_vn_12345.webm' or null
 */
function save_voice_note($base64_data = null, $file_upload = null) {
    $dir = ensure_voice_notes_dir();

    // 1. Check base64 data URI (from MediaRecorder)
    if (!empty($base64_data)) {
        $data_str = trim($base64_data);
        if (preg_match('/^data:audio\/([a-zA-Z0-9\-_]+);base64,(.+)$/', $data_str, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'mpeg') $ext = 'mp3';
            if ($ext === 'ogg' || $ext === 'opus') $ext = 'ogg';
            if ($ext === 'webm') $ext = 'webm';
            if (!in_array($ext, ['webm', 'mp3', 'ogg', 'wav', 'm4a', 'aac'])) {
                $ext = 'webm';
            }
            $binary = base64_decode($matches[2]);
            if ($binary !== false && strlen($binary) > 100) {
                $fname = 'pastor_vn_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (file_put_contents($dir . $fname, $binary)) {
                    return 'uploads/voice_notes/' . $fname;
                }
            }
        }
    }

    // 2. Check $_FILES upload
    if (!empty($file_upload) && isset($file_upload['tmp_name']) && $file_upload['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file_upload['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['webm', 'mp3', 'ogg', 'wav', 'm4a', 'aac'])) {
            $ext = 'webm';
        }
        $fname = 'pastor_vn_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file_upload['tmp_name'], $dir . $fname)) {
            return 'uploads/voice_notes/' . $fname;
        }
    }

    return null;
}

/**
 * Render a WhatsApp-style voice note player
 */
function render_whatsapp_voice_player($audio_path, $speaker_name = 'Pastor', $speaker_pic = null, $created_at = null) {
    if (empty($audio_path)) return '';
    
    $safe_audio = htmlspecialchars($audio_path);
    $safe_name  = htmlspecialchars($speaker_name);
    $pic_url    = !empty($speaker_pic) ? htmlspecialchars($speaker_pic) : 'uploads/default_avatar.png';
    $uid        = 'wa_audio_' . substr(md5($audio_path . microtime()), 0, 10);

    // Dynamic wave heights to look like realistic WhatsApp speech waveform
    $wave_heights = [8, 14, 22, 10, 18, 24, 16, 20, 12, 15, 22, 18, 14, 20, 24, 16, 12, 19, 14, 10, 16, 22, 15, 8];

    ob_start();
    ?>
    <div class="whatsapp-voice-player" id="<?= $uid ?>" data-audio-src="<?= $safe_audio ?>" style="background:var(--bg-main, #f0fdf4); border:1.5px solid rgba(37,211,102,0.35); border-radius:18px; padding:12px 16px; margin:12px 0; max-width:480px; box-shadow:0 3px 12px rgba(37,211,102,0.12); display:flex; align-items:center; gap:12px; position:relative;">
        <!-- Avatar with green mic badge -->
        <div style="position:relative; width:44px; height:44px; flex-shrink:0;">
            <img src="<?= $pic_url ?>" alt="<?= $safe_name ?>" style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #25d366; display:block;">
            <span style="position:absolute; bottom:-2px; right:-2px; background:#25d366; color:#ffffff; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; box-shadow:0 1px 3px rgba(0,0,0,0.3);" title="Voice Note">🎙️</span>
        </div>

        <!-- Play / Pause Button -->
        <button type="button" class="wa-play-btn" aria-label="Play Voice Note" style="background:#25d366; color:#ffffff; border:none; width:40px; height:40px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 3px 8px rgba(37,211,102,0.45); transition:transform 0.1s, background 0.15s;">
            <svg class="wa-icon-play" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <svg class="wa-icon-pause" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="display:none;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
        </button>

        <!-- Waveform & Track -->
        <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:4px;">
            <div class="wa-track-area" style="position:relative; height:24px; display:flex; align-items:center; cursor:pointer;">
                <!-- Waveform Bars -->
                <div class="wa-waveform" style="display:flex; align-items:center; gap:2.5px; width:100%; height:24px;">
                    <?php foreach ($wave_heights as $h): ?>
                        <span class="wa-bar" style="display:inline-block; flex:1; min-width:2px; height:<?= $h ?>px; background:#9ca3af; border-radius:3px; transition:background 0.1s;"></span>
                    <?php endforeach; ?>
                </div>
                <input type="range" class="wa-seek-bar" min="0" max="100" value="0" step="0.1" style="position:absolute; width:100%; height:24px; opacity:0; cursor:pointer; margin:0; left:0; top:0; z-index:3;">
            </div>

            <!-- Time and Speed -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; color:var(--text-muted, #64748b); font-weight:700;">
                <span class="wa-current-time">0:00</span>
                <span style="font-size:0.7rem; color:#15803d; font-weight:800; letter-spacing:0.3px;">VOICE NOTE</span>
                <span class="wa-duration">--:--</span>
            </div>
        </div>

        <!-- Playback speed pill (1x, 1.5x, 2x) -->
        <button type="button" class="wa-speed-btn" title="Toggle Speed" style="background:rgba(37,211,102,0.15); color:#15803d; border:1px solid rgba(37,211,102,0.3); border-radius:12px; padding:3px 8px; font-size:0.72rem; font-weight:800; cursor:pointer; flex-shrink:0;">1x</button>

        <!-- Hidden Audio Element -->
        <audio class="wa-audio" preload="metadata" src="<?= $safe_audio ?>" style="display:none;"></audio>
    </div>
    <?php
    return ob_get_clean();
}
?>
