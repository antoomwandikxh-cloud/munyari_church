<?php
require_once "db_connect.php";

$sql1 = "CREATE TABLE IF NOT EXISTS `prayer_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `prayer_date` date NOT NULL,
  `prayer_time` time NOT NULL,
  `location` varchar(255) DEFAULT \"Department Meeting Room\",
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$sql2 = "CREATE TABLE IF NOT EXISTS `prayer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT \"General\",
  `status` enum(\"Active\",\"Answered\") NOT NULL DEFAULT \"Active\",
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql1)) { echo "prayer_schedules table created successfully.<br>"; }
else { echo "Error: " . $conn->error . "<br>"; }

if ($conn->query($sql2)) { echo "prayer_items table created successfully.<br>"; }
else { echo "Error: " . $conn->error . "<br>"; }

echo "Done!";
?>
