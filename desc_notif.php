<?php
require 'db_connect.php';
$r = $conn->query('DESCRIBE notifications');
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Query failed";
}
?>
