<?php
require_once 'db_connect.php';
require_once 'graduation_roster_helper.php';

if (ensure_graduation_roster_schema($conn)) {
    echo "Graduation dates are ready.";
} else {
    http_response_code(500);
    echo "Could not update the graduation roster schema.";
}
?>
