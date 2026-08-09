<?php
require_once 'db_connect.php';

// Add description column
$result = $conn->query("SHOW COLUMNS FROM financial_records LIKE 'description'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE financial_records ADD COLUMN description VARCHAR(255) DEFAULT NULL");
    echo "Added description column.<br>";
}

// Add record_date column
$result = $conn->query("SHOW COLUMNS FROM financial_records LIKE 'record_date'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE financial_records ADD COLUMN record_date DATE DEFAULT NULL");
    echo "Added record_date column.<br>";
}

// Add recorded_by column
$result = $conn->query("SHOW COLUMNS FROM financial_records LIKE 'recorded_by'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE financial_records ADD COLUMN recorded_by INT(6) UNSIGNED DEFAULT NULL");
    echo "Added recorded_by column.<br>";
}

echo "Done!";
$conn->close();
?>
