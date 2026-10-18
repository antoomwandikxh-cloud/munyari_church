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

    $meeting_type_column = $conn->query("SHOW COLUMNS FROM organized_events LIKE 'meeting_type'");
    if ($meeting_type_column && $meeting_type_column->num_rows === 0) {
        if (!$conn->query("ALTER TABLE organized_events ADD COLUMN meeting_type VARCHAR(100) NOT NULL DEFAULT 'Online' AFTER meeting_date")) return false;
    }

    $manual_created = $conn->query("CREATE TABLE IF NOT EXISTS organized_event_manual_attendance (
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
    if (!$manual_created) return false;

    $registers_created = $conn->query("CREATE TABLE IF NOT EXISTS organized_attendance_registers (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        department VARCHAR(100) NOT NULL,
        organizer_id INT(6) UNSIGNED NOT NULL,
        meeting_date DATE NOT NULL,
        meeting_type VARCHAR(100) NOT NULL DEFAULT 'Online',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_attendance_register_department (department),
        KEY idx_attendance_register_organizer (organizer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$registers_created) return false;

    $register_members_created = $conn->query("CREATE TABLE IF NOT EXISTS organized_attendance_register_members (
        register_id INT(10) UNSIGNED NOT NULL,
        member_id INT(6) UNSIGNED NOT NULL,
        marked_by INT(6) UNSIGNED NOT NULL,
        marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        joined_at TIME NULL,
        PRIMARY KEY (register_id, member_id),
        KEY idx_register_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$register_members_created) return false;

    $manual_members_created = $conn->query("CREATE TABLE IF NOT EXISTS organized_attendance_register_manual_members (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        register_id INT(10) UNSIGNED NOT NULL,
        attendee_name VARCHAR(255) NOT NULL,
        gender VARCHAR(40) NULL,
        phone VARCHAR(40) NULL,
        profile_picture VARCHAR(255) NULL,
        is_present TINYINT(1) NOT NULL DEFAULT 1,
        joined_at TIME NULL,
        marked_by INT(6) UNSIGNED NOT NULL,
        marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_register_manual_member (register_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$manual_members_created) return false;

    $overrides_created = $conn->query("CREATE TABLE IF NOT EXISTS organized_attendance_register_member_overrides (
        register_id INT(10) UNSIGNED NOT NULL,
        member_id INT(6) UNSIGNED NOT NULL,
        name_override VARCHAR(255) NULL,
        phone_override VARCHAR(40) NOT NULL,
        profile_picture_override VARCHAR(255) NULL,
        updated_by INT(6) UNSIGNED NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (register_id, member_id),
        KEY idx_register_member_override_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$overrides_created) return false;

    $name_override_column = $conn->query("SHOW COLUMNS FROM organized_attendance_register_member_overrides LIKE 'name_override'");
    if ($name_override_column && $name_override_column->num_rows === 0) {
        if (!$conn->query("ALTER TABLE organized_attendance_register_member_overrides ADD COLUMN name_override VARCHAR(255) NULL AFTER member_id")) return false;
    }

    return true;
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
