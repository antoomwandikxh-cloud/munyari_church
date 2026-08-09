<?php
require_once 'db_connect.php';

$daily_col = $conn->query("SHOW COLUMNS FROM daily_messages LIKE 'expires_at'");
if ($daily_col && $daily_col->num_rows == 0) {
    $conn->query("ALTER TABLE daily_messages ADD COLUMN expires_at DATETIME NULL AFTER video_file");
    echo "Added expires_at to daily_messages.<br>";
}

$highlight_col = $conn->query("SHOW COLUMNS FROM church_highlights LIKE 'expires_at'");
if ($highlight_col && $highlight_col->num_rows == 0) {
    $conn->query("ALTER TABLE church_highlights ADD COLUMN expires_at DATETIME NULL AFTER caption");
    echo "Added expires_at to church_highlights.<br>";
}

echo "Database update 22 complete.";
?>
