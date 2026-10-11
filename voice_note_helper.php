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
 * Renders the WhatsApp Audio Player client assets (CSS + JS) once
 */
function whatsapp_player_assets() {
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;

    return '
<style>
.whatsapp-voice-player {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.whatsapp-voice-player:hover {
    box-shadow: 0 4px 16px rgba(37,211,102,0.2) !important;
}
[data-theme="dark"] .whatsapp-voice-player {
    background: #1e293b !important;
    border-color: rgba(37,211,102,0.4) !important;
}
</style>
<script>
function initWhatsAppAudioPlayers() {
    document.querySelectorAll(".whatsapp-voice-player").forEach(function(player) {
        if (player.dataset.waInitialized) return;
        player.dataset.waInitialized = "true";

        var audio = player.querySelector(".wa-audio");
        var playBtn = player.querySelector(".wa-play-btn");
        var iconPlay = player.querySelector(".wa-icon-play");
        var iconPause = player.querySelector(".wa-icon-pause");
        var seekBar = player.querySelector(".wa-seek-bar");
        var currentTimeEl = player.querySelector(".wa-current-time");
        var durationEl = player.querySelector(".wa-duration");
        var speedBtn = player.querySelector(".wa-speed-btn");
        var waveformBars = player.querySelectorAll(".wa-bar");

        function fmt(sec) {
            if (isNaN(sec) || !isFinite(sec)) return "0:00";
            var m = Math.floor(sec / 60);
            var s = Math.floor(sec % 60);
            return m + ":" + (s < 10 ? "0" : "") + s;
        }

        if (audio) {
            audio.addEventListener("loadedmetadata", function() {
                if (durationEl) durationEl.textContent = fmt(audio.duration);
            });

            audio.addEventListener("timeupdate", function() {
                if (audio.duration) {
                    var pct = (audio.currentTime / audio.duration) * 100;
                    if (seekBar) seekBar.value = pct;
                    if (currentTimeEl) currentTimeEl.textContent = fmt(audio.currentTime);

                    var totalBars = waveformBars.length;
                    var activeCount = Math.floor((pct / 100) * totalBars);
                    waveformBars.forEach(function(bar, idx) {
                        bar.style.backgroundColor = idx <= activeCount ? "#25d366" : "#9ca3af";
                    });
                }
            });

            audio.addEventListener("ended", function() {
                if (iconPlay) iconPlay.style.display = "block";
                if (iconPause) iconPause.style.display = "none";
                if (seekBar) seekBar.value = 0;
                if (currentTimeEl) currentTimeEl.textContent = "0:00";
                waveformBars.forEach(function(bar) { bar.style.backgroundColor = "#9ca3af"; });
            });
        }

        if (playBtn && audio) {
            playBtn.addEventListener("click", function() {
                document.querySelectorAll(".whatsapp-voice-player .wa-audio").forEach(function(otherAudio) {
                    if (otherAudio !== audio && !otherAudio.paused) {
                        otherAudio.pause();
                        var op = otherAudio.closest(".whatsapp-voice-player");
                        if (op) {
                            var p1 = op.querySelector(".wa-icon-play");
                            var p2 = op.querySelector(".wa-icon-pause");
                            if (p1) p1.style.display = "block";
                            if (p2) p2.style.display = "none";
                        }
                    }
                });

                if (audio.paused) {
                    audio.play().then(function() {
                        if (iconPlay) iconPlay.style.display = "none";
                        if (iconPause) iconPause.style.display = "block";
                    }).catch(function(e) { console.error("Playback error:", e); });
                } else {
                    audio.pause();
                    if (iconPlay) iconPlay.style.display = "block";
                    if (iconPause) iconPause.style.display = "none";
                }
            });
        }

        if (seekBar && audio) {
            seekBar.addEventListener("input", function() {
                if (audio.duration) {
                    audio.currentTime = (seekBar.value / 100) * audio.duration;
                }
            });
        }

        if (speedBtn && audio) {
            var speeds = [1, 1.5, 2];
            var currentIdx = 0;
            speedBtn.addEventListener("click", function() {
                currentIdx = (currentIdx + 1) % speeds.length;
                audio.playbackRate = speeds[currentIdx];
                speedBtn.textContent = speeds[currentIdx] + "x";
            });
        }
    });
}
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWhatsAppAudioPlayers);
} else {
    initWhatsAppAudioPlayers();
}
</script>';
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

    $assets = whatsapp_player_assets();

    ob_start();
    echo $assets;
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

/**
 * Render the WhatsApp-style voice recorder widget for Pastor announcement form
 */
function render_voice_note_recorder() {
    ob_start();
    ?>
    <style>
    @keyframes wa-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
    }
    @keyframes wa-wave {
        0%, 100% { height: 6px; }
        50% { height: 26px; }
    }
    .vn-live-waveform .bar {
        display: inline-block;
        width: 3.5px;
        background: #ef4444;
        border-radius: 3px;
        animation: wa-wave 0.9s ease-in-out infinite;
    }
    .vn-live-waveform .bar-1  { animation-delay: 0.05s; }
    .vn-live-waveform .bar-2  { animation-delay: 0.2s; }
    .vn-live-waveform .bar-3  { animation-delay: 0.35s; }
    .vn-live-waveform .bar-4  { animation-delay: 0.15s; }
    .vn-live-waveform .bar-5  { animation-delay: 0.4s; }
    .vn-live-waveform .bar-6  { animation-delay: 0.25s; }
    .vn-live-waveform .bar-7  { animation-delay: 0.5s; }
    .vn-live-waveform .bar-8  { animation-delay: 0.1s; }
    .vn-live-waveform .bar-9  { animation-delay: 0.3s; }
    .vn-live-waveform .bar-10 { animation-delay: 0.45s; }
    .vn-live-waveform .bar-11 { animation-delay: 0.18s; }
    .vn-live-waveform .bar-12 { animation-delay: 0.32s; }

    [data-theme="dark"] .voice-note-recorder-card {
        background: #1e293b !important;
    }
    </style>

    <!-- WhatsApp-Style Voice Recorder Section -->
    <div class="voice-note-recorder-card" style="background:var(--bg-card, #ffffff); border:2px dashed #25d366; border-radius:14px; padding:16px; margin-bottom:18px; box-shadow:0 3px 10px rgba(37,211,102,0.08);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:50%; background:#25d366; display:flex; align-items:center; justify-content:center; color:#ffffff; font-size:18px; box-shadow:0 2px 6px rgba(37,211,102,0.4);">
                    🎙️
                </div>
                <div>
                    <strong style="font-size:0.95rem; color:var(--text-main); display:block;">WhatsApp-Style Voice Note</strong>
                    <span style="font-size:0.78rem; color:var(--text-muted);">Record a voice announcement for all church members</span>
                </div>
            </div>
            <span id="vn_status_badge" style="display:none; font-size:0.78rem; font-weight:800; padding:4px 12px; border-radius:20px; background:#fee2e2; color:#ef4444; border:1px solid #fca5a5;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444; margin-right:6px; animation:wa-pulse 1s infinite;"></span>
                RECORDING <span id="vn_timer">0:00</span>
            </span>
        </div>

        <!-- Recorder Controls Area -->
        <div id="vn_controls_area">
            <!-- 1. Idle State: Big WhatsApp Mic Button -->
            <div id="vn_idle_state" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <button type="button" id="vn_start_btn" onclick="startVoiceRecording();" style="background:#25d366; color:#ffffff; border:none; padding:11px 22px; border-radius:26px; font-size:0.9rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 3px 12px rgba(37,211,102,0.4); letter-spacing:0.3px; transition:transform 0.1s, background 0.15s;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 003-3V5a3 3 0 00-6 0v6a3 3 0 003 3z"/></svg>
                    Record Voice Note
                </button>
                <span style="font-size:0.82rem; color:var(--text-muted);">Tap to record your pastoral voice note</span>
            </div>

            <!-- 2. Recording State: Animated WhatsApp Sound Waves, Stop & Cancel -->
            <div id="vn_recording_state" style="display:none; align-items:center; gap:12px; flex-wrap:wrap;">
                <div class="vn-live-waveform" style="display:flex; align-items:center; gap:4px; height:36px; padding:0 14px; background:rgba(239,68,68,0.08); border-radius:20px; border:1px solid rgba(239,68,68,0.25);">
                    <span class="bar bar-1"></span>
                    <span class="bar bar-2"></span>
                    <span class="bar bar-3"></span>
                    <span class="bar bar-4"></span>
                    <span class="bar bar-5"></span>
                    <span class="bar bar-6"></span>
                    <span class="bar bar-7"></span>
                    <span class="bar bar-8"></span>
                    <span class="bar bar-9"></span>
                    <span class="bar bar-10"></span>
                    <span class="bar bar-11"></span>
                    <span class="bar bar-12"></span>
                </div>
                <button type="button" onclick="stopVoiceRecording();" style="background:#ef4444; color:#ffffff; border:none; padding:10px 18px; border-radius:22px; font-size:0.86rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:6px; box-shadow:0 3px 10px rgba(239,68,68,0.45);">
                    <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z"/></svg>
                    Stop &amp; Review
                </button>
                <button type="button" onclick="cancelVoiceRecording();" style="background:transparent; color:#6b7280; border:1px solid #d1d5db; padding:9px 15px; border-radius:20px; font-size:0.82rem; font-weight:600; cursor:pointer;">
                    Cancel
                </button>
            </div>

            <!-- 3. Review State: Playable Voice Note Preview + Discard Button -->
            <div id="vn_preview_state" style="display:none; align-items:center; gap:12px; flex-wrap:wrap;">
                <audio id="vn_preview_audio" controls style="display:none;"></audio>
                <div style="background:var(--bg-main, #f0fdf4); border:1.5px solid #25d366; border-radius:22px; padding:8px 16px; display:inline-flex; align-items:center; gap:12px; box-shadow:0 3px 10px rgba(37,211,102,0.2);">
                    <button type="button" id="vn_preview_play_btn" onclick="toggleVnPreview();" style="background:#25d366; color:#ffffff; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(37,211,102,0.4);">
                        <svg id="vn_preview_icon_play" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <svg id="vn_preview_icon_pause" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="display:none;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>
                    <div>
                        <span style="font-size:0.85rem; font-weight:800; color:#15803d; display:block;">Voice Note Ready</span>
                        <span id="vn_preview_duration" style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">0:00</span>
                    </div>
                </div>
                <button type="button" onclick="discardVoiceRecording();" style="background:#fee2e2; color:#ef4444; border:1px solid #fca5a5; padding:8px 14px; border-radius:20px; font-size:0.82rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete &amp; Re-record
                </button>
            </div>
        </div>

        <!-- Hidden Base64 blob input -->
        <input type="hidden" name="pastor_voice_note_blob" id="pastor_voice_note_blob" value="">
    </div>

    <script>
    var vnMediaRecorder = null;
    var vnAudioChunks = [];
    var vnTimerInterval = null;
    var vnSecondsRecorded = 0;
    var vnAudioBlob = null;

    function formatVnTime(sec) {
        var m = Math.floor(sec / 60);
        var s = Math.floor(sec % 60);
        return m + ":" + (s < 10 ? "0" : "") + s;
    }

    function startVoiceRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert("Audio recording is not supported in this browser. Please use Chrome, Edge, or Firefox.");
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function(stream) {
                vnAudioChunks = [];
                var mimeType = "";
                var supportedTypes = [
                    "audio/webm;codecs=opus",
                    "audio/webm",
                    "audio/ogg;codecs=opus",
                    "audio/ogg",
                    "audio/mp4"
                ];
                for (var i = 0; i < supportedTypes.length; i++) {
                    if (MediaRecorder.isTypeSupported(supportedTypes[i])) {
                        mimeType = supportedTypes[i];
                        break;
                    }
                }

                vnMediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType: mimeType }) : new MediaRecorder(stream);

                vnMediaRecorder.ondataavailable = function(e) {
                    if (e.data && e.data.size > 0) {
                        vnAudioChunks.push(e.data);
                    }
                };

                vnMediaRecorder.onstop = function() {
                    stream.getTracks().forEach(function(track) { track.stop(); });
                    var finalType = mimeType || "audio/webm";
                    vnAudioBlob = new Blob(vnAudioChunks, { type: finalType });

                    var reader = new FileReader();
                    reader.readAsDataURL(vnAudioBlob);
                    reader.onloadend = function() {
                        var base64Data = reader.result;
                        document.getElementById("pastor_voice_note_blob").value = base64Data;
                    };

                    var previewAudio = document.getElementById("vn_preview_audio");
                    var blobUrl = URL.createObjectURL(vnAudioBlob);
                    previewAudio.src = blobUrl;
                    document.getElementById("vn_preview_duration").textContent = formatVnTime(vnSecondsRecorded);

                    document.getElementById("vn_recording_state").style.display = "none";
                    document.getElementById("vn_status_badge").style.display = "none";
                    document.getElementById("vn_preview_state").style.display = "flex";
                };

                vnMediaRecorder.start(200);

                document.getElementById("vn_idle_state").style.display = "none";
                document.getElementById("vn_preview_state").style.display = "none";
                document.getElementById("vn_recording_state").style.display = "flex";
                document.getElementById("vn_status_badge").style.display = "inline-flex";

                vnSecondsRecorded = 0;
                document.getElementById("vn_timer").textContent = "0:00";
                clearInterval(vnTimerInterval);
                vnTimerInterval = setInterval(function() {
                    vnSecondsRecorded++;
                    document.getElementById("vn_timer").textContent = formatVnTime(vnSecondsRecorded);
                }, 1000);
            })
            .catch(function(err) {
                console.error("Microphone access error:", err);
                alert("Could not access microphone. Please allow microphone permissions in your browser.");
            });
    }

    function stopVoiceRecording() {
        clearInterval(vnTimerInterval);
        if (vnMediaRecorder && vnMediaRecorder.state !== "inactive") {
            vnMediaRecorder.stop();
        }
    }

    function cancelVoiceRecording() {
        clearInterval(vnTimerInterval);
        if (vnMediaRecorder && vnMediaRecorder.state !== "inactive") {
            vnMediaRecorder.stop();
        }
        vnAudioChunks = [];
        vnAudioBlob = null;
        var blobInput = document.getElementById("pastor_voice_note_blob");
        if (blobInput) blobInput.value = "";
        document.getElementById("vn_recording_state").style.display = "none";
        document.getElementById("vn_status_badge").style.display = "none";
        document.getElementById("vn_preview_state").style.display = "none";
        document.getElementById("vn_idle_state").style.display = "flex";
    }

    function discardVoiceRecording() {
        var previewAudio = document.getElementById("vn_preview_audio");
        if (previewAudio) {
            previewAudio.pause();
            previewAudio.src = "";
        }
        vnAudioChunks = [];
        vnAudioBlob = null;
        var blobInput = document.getElementById("pastor_voice_note_blob");
        if (blobInput) blobInput.value = "";
        document.getElementById("vn_preview_state").style.display = "none";
        document.getElementById("vn_recording_state").style.display = "none";
        document.getElementById("vn_status_badge").style.display = "none";
        document.getElementById("vn_idle_state").style.display = "flex";
    }

    function toggleVnPreview() {
        var audio = document.getElementById("vn_preview_audio");
        var iconPlay = document.getElementById("vn_preview_icon_play");
        var iconPause = document.getElementById("vn_preview_icon_pause");
        if (!audio) return;

        if (audio.paused) {
            audio.play();
            iconPlay.style.display = "none";
            iconPause.style.display = "block";
        } else {
            audio.pause();
            iconPlay.style.display = "block";
            iconPause.style.display = "none";
        }

        audio.onended = function() {
            iconPlay.style.display = "block";
            iconPause.style.display = "none";
        };
    }

    function handlePastorAnnFormSubmit(form, event) {
        var textarea = document.getElementById("pastor_ann_textarea");
        var voiceBlob = document.getElementById("pastor_voice_note_blob").value;
        var hasText = textarea && textarea.value.trim().length > 0;
        var hasVoice = voiceBlob && voiceBlob.length > 50;

        if (!hasText && !hasVoice) {
            event.preventDefault();
            alert("Please write a message or record a voice note announcement before sending.");
            return false;
        }

        var submitBtn = document.getElementById("pastor_ann_submit_btn");
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = "Sending to Entire Church...";
        }
        return true;
    }
    </script>
    <?php
    return ob_get_clean();
}
?>
