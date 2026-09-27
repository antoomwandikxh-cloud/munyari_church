<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php';
$content = file_get_contents($file);

// Find the start of the Create Custom Role Form
$start_marker = "                    <!-- Create Custom Role Form -->\n                    <div class=\"content-card\" style=\"flex: 1; min-width: 300px;\">";
$start_pos = strpos($content, $start_marker);
if ($start_pos === false) {
    // Try with different line endings
    $start_marker = "                    <!-- Create Custom Role Form -->\r\n                    <div class=\"content-card\" style=\"flex: 1; min-width: 300px;\">";
    $start_pos = strpos($content, $start_marker);
}

if ($start_pos !== false) {
    // Find the end of this block
    // It ends right before "                <!-- Currently Assigned Roles (Hierarchical) -->"
    $end_marker = "                <!-- Currently Assigned Roles (Hierarchical) -->";
    $end_pos = strpos($content, $end_marker, $start_pos);
    
    if ($end_pos !== false) {
        // Also we need to remove the closing div for the flex wrapper
        // The block we want to delete is from $start_pos to $end_pos
        // But let's check what's right before $end_marker to see if we need to remove a closing div
        
        $block_to_delete = substr($content, $start_pos, $end_pos - $start_pos);
        $new_content = substr($content, 0, $start_pos) . substr($content, $end_pos);
        
        // Remove the flex container start tag as well so the Assign Role card expands naturally
        $flex_start = "<div style=\"display: flex; gap: 20px; flex-wrap: wrap;\">";
        if (strpos($new_content, $flex_start) !== false) {
            $new_content = str_replace($flex_start, "<div>", $new_content);
        }
        
        file_put_contents($file, $new_content);
        echo "Successfully removed Manage Roles section";
    } else {
        echo "Could not find end marker";
    }
} else {
    echo "Could not find Create Custom Role Form section";
}
?>
