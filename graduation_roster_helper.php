<?php

if (!function_exists('ensure_graduation_roster_schema')) {
    function ensure_graduation_roster_schema($conn) {
        static $checked = false;
        if ($checked) return true;

        $created = $conn->query("CREATE TABLE IF NOT EXISTS graduation_list (
            id INT(6) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id INT(6) UNSIGNED NOT NULL,
            department VARCHAR(50) NOT NULL,
            graduation_year INT(4) NOT NULL,
            graduation_date DATE NULL,
            added_by INT(6) UNSIGNED NOT NULL,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if (!$created) return false;

        $column = $conn->query("SHOW COLUMNS FROM graduation_list LIKE 'graduation_date'");
        if (!$column) return false;
        if ($column->num_rows === 0) {
            if ($conn->query("ALTER TABLE graduation_list ADD COLUMN graduation_date DATE NULL AFTER graduation_year") !== true) return false;
        }

        $orphaned = $conn->query("DELETE graduation_row FROM graduation_list graduation_row
            LEFT JOIN members member_record ON member_record.id = graduation_row.member_id
            WHERE member_record.id IS NULL");
        if (!$orphaned) return false;

        $roster_unique_index = $conn->query("SHOW INDEX FROM graduation_list WHERE Key_name = 'uq_graduation_member_department_year'");
        if (!$roster_unique_index) return false;
        if ($roster_unique_index->num_rows === 0) {
            $deduplicated = $conn->query("DELETE duplicate_row FROM graduation_list duplicate_row
                INNER JOIN graduation_list keeper_row
                    ON keeper_row.member_id = duplicate_row.member_id
                    AND keeper_row.graduation_year = duplicate_row.graduation_year
                    AND LOWER(TRIM(keeper_row.department)) = LOWER(TRIM(duplicate_row.department))
                    AND keeper_row.id < duplicate_row.id");
            if (!$deduplicated) return false;

            if ($conn->query("ALTER TABLE graduation_list ADD UNIQUE KEY uq_graduation_member_department_year (member_id, department, graduation_year)") !== true) return false;
        }
        $checked = true;
        return true;
    }
}

if (!function_exists('ensure_graduation_announcement_audio_schema')) {
    function ensure_graduation_announcement_audio_schema($conn) {
        static $checked = false;
        if ($checked) return true;

        $column = $conn->query("SHOW COLUMNS FROM department_announcements LIKE 'audio_path'");
        if (!$column) return false;
        if ($column->num_rows === 0) {
            if ($conn->query("ALTER TABLE department_announcements ADD COLUMN audio_path VARCHAR(255) NULL AFTER image_path") !== true) return false;
        }

        $checked = true;
        return true;
    }
}
