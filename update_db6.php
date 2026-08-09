<?php
require_once 'db_connect.php';

echo "Updating database schema for profile pictures...\n";

// Members Table
if ($conn->query("ALTER TABLE members ADD profile_picture VARCHAR(255) DEFAULT 'default_avatar.png'")) {
    echo "Added profile_picture to members table.\n";
} else {
    echo "Error updating members table: " . $conn->error . "\n";
}

// Pastors Table
if ($conn->query("ALTER TABLE pastors ADD profile_picture VARCHAR(255) DEFAULT 'default_avatar.png'")) {
    echo "Added profile_picture to pastors table.\n";
} else {
    echo "Error updating pastors table: " . $conn->error . "\n";
}

echo "Database update complete.\n";
?>
