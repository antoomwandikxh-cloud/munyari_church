<?php
$content = file_get_contents('pastor_dashboard.php');

// We have syntax error at line 1361.
// Let's replace the whole block from line 1350 to 1370 with a generic valid placeholder to see if it fixes it.

$lines = explode("\n", $content);
$lines[1359] = "                        ORDER BY q.created_at DESC\n                    \");\n                    if (\$announcement_queries && \$announcement_queries->num_rows > 0):";

// Delete from 1360 down to where 'render_announcement_image' ends
for ($i = 1360; $i <= 1372; $i++) {
    if (strpos($lines[$i], '<?php foreach ([\'general_church\' => \'General Leaders Chat\']') !== false) {
        break; // Stop deleting if we hit the next block
    }
    $lines[$i] = '';
}

// But wait, the missing endwhile or endif needs to be placed.
// I will just use a script to find and delete the broken PHP segments manually.
