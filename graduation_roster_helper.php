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
        $checked = true;
        return true;
    }
}
