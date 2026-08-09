<?php
require_once 'db_connect.php';

$r = $conn->query("SHOW COLUMNS FROM pastors LIKE 'is_approved'");
if ($r->num_rows == 0) {
    $conn->query("ALTER TABLE pastors ADD COLUMN is_approved TINYINT(1) DEFAULT 1 AFTER password");
    echo "Added is_approved column to pastors. Existing pastors are auto-approved.<br>";
} else {
    echo "is_approved column already exists.<br>";
}
$conn->close();
?>
