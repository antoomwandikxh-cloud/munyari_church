<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/highlight_retention.php';

$deleted_count = cleanup_expired_church_highlights($conn);
echo "Deleted $deleted_count expired church highlight file(s)." . PHP_EOL;
