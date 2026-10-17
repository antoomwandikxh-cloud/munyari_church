<?php
function ensure_organized_event_attendance_schema($conn) {
    $attendance_created = $conn->query("CREATE TABLE IF NOT EXISTS organized_event_attendance (
        event_id INT(6) UNSIGNED NOT NULL,
        member_id INT(6) UNSIGNED NOT NULL,
        marked_by INT(6) UNSIGNED NOT NULL,
        marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        joined_at TIME NULL,
        PRIMARY KEY (event_id, member_id),
        KEY idx_event_attendance_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$attendance_created) return false;

    $joined_column = $conn->query("SHOW COLUMNS FROM organized_event_attendance LIKE 'joined_at'");
    if ($joined_column && $joined_column->num_rows === 0) {
        if (!$conn->query("ALTER TABLE organized_event_attendance ADD COLUMN joined_at TIME NULL AFTER marked_at")) return false;
    }

    $audio_column = $conn->query("SHOW COLUMNS FROM organized_events LIKE 'audio_path'");
    if ($audio_column && $audio_column->num_rows === 0) {
        if (!$conn->query("ALTER TABLE organized_events ADD COLUMN audio_path VARCHAR(255) NULL AFTER attendance_file")) return false;
    }

    $date_column = $conn->query("SHOW COLUMNS FROM organized_events LIKE 'meeting_date'");
    if ($date_column && $date_column->num_rows === 0) {
        if (!$conn->query("ALTER TABLE organized_events ADD COLUMN meeting_date DATE NULL AFTER audio_path")) return false;
    }

    return (bool)$conn->query("CREATE TABLE IF NOT EXISTS organized_event_manual_attendance (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id INT(6) UNSIGNED NOT NULL,
        attendee_name VARCHAR(255) NOT NULL,
        gender VARCHAR(40) NULL,
        phone VARCHAR(40) NULL,
        profile_picture VARCHAR(255) NULL,
        is_present TINYINT(1) NOT NULL DEFAULT 1,
        joined_at TIME NULL,
        marked_by INT(6) UNSIGNED NOT NULL,
        marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_manual_attendance_event (event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function save_organized_event_attendee_photo($upload) {
    if (empty($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || (int)($upload['size'] ?? 0) > 5 * 1024 * 1024) return false;

    $image_info = @getimagesize($upload['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime_type = $image_info['mime'] ?? '';
    if (!$image_info || !isset($extensions[$mime_type])) return false;

    $directory = __DIR__ . '/uploads/organized_event_attendees/';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return false;
    $filename = 'attendee_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime_type];
    if (!move_uploaded_file($upload['tmp_name'], $directory . $filename)) return false;
    return 'organized_event_attendees/' . $filename;
}
?>
