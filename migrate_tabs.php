<?php
// Script to migrate tabs from pastor_dashboard.php to admin_dashboard.php

$pastor_file = 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php';
$admin_file = 'C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php';

$pastor = file_get_contents($pastor_file);
$admin = file_get_contents($admin_file);

function extract_block($content, $start_marker, $end_marker) {
    $start_pos = strpos($content, $start_marker);
    if ($start_pos === false) return false;
    $end_pos = strpos($content, $end_marker, $start_pos + 1);
    if ($end_pos === false) return false;
    return substr($content, $start_pos, $end_pos - $start_pos);
}

// 1. Extract Tabs Content
$tabs_to_copy = [
    'general_announcements' => ['<?php elseif ($tab == \'general_announcements\'): ?>', '<?php elseif ($tab == \'notifications\'): ?>'],
    'financials' => ['<?php elseif ($tab == \'financials\'): ?>', '<?php elseif ($tab == \'assign_roles\'): ?>'],
    'building_monitoring' => ['<?php elseif ($tab == \'building_monitoring\'): ?>', '<?php elseif ($tab == \'appointments\'): ?>'],
    'appointments' => ['<?php elseif ($tab == \'appointments\'): ?>', '<?php elseif ($tab == \'organized_events\'): ?>']
];

$appended_tabs = "";
foreach ($tabs_to_copy as $tab => $markers) {
    $block = extract_block($pastor, $markers[0], $markers[1]);
    if ($block !== false) {
        $block = str_replace('pastor_dashboard.php', 'admin_dashboard.php', $block);
        $appended_tabs .= $block . "\n";
        echo "Successfully extracted $tab\n";
    } else {
        echo "Failed to extract $tab\n";
    }
}

// 2. Extract and Replace general_leadership
$gl_start = '<?php elseif ($tab == \'general_leadership\'): ?>';
$gl_end_admin = '<?php elseif ($tab == \'settings\'): ?>'; 
$gl_end_pastor = '<?php elseif ($tab == \'choir_songs\'): ?>';

$pastor_gl = extract_block($pastor, $gl_start, $gl_end_pastor);
if ($pastor_gl !== false) {
    $pastor_gl = str_replace('pastor_dashboard.php', 'admin_dashboard.php', $pastor_gl);
    
    $admin_gl_start_pos = strpos($admin, $gl_start);
    $admin_gl_end_pos = strpos($admin, $gl_end_admin, $admin_gl_start_pos);
    
    if ($admin_gl_start_pos !== false && $admin_gl_end_pos !== false) {
        $admin = substr_replace($admin, $pastor_gl, $admin_gl_start_pos, $admin_gl_end_pos - $admin_gl_start_pos);
        echo "Successfully replaced general_leadership\n";
    } else {
        echo "Failed to locate general_leadership in admin_dashboard to replace\n";
    }
} else {
    echo "Failed to extract general_leadership from pastor\n";
}

// 3. Append the new tabs to admin_dashboard.php
// We can append them right before the final endif
$insert_pos = strrpos($admin, '<?php endif; ?>');
if ($insert_pos !== false) {
    $admin = substr_replace($admin, $appended_tabs . "            ", $insert_pos, 0);
    echo "Successfully appended new tabs\n";
} else {
    echo "Failed to find insert position for new tabs\n";
}

// 4. Update Sidebar Links
// Extract links from Pastor
$links = [
    '<a href="?tab=financials"',
    '<a href="?tab=appointments"',
    '<a href="?tab=general_announcements"',
    '<a href="?tab=building_monitoring"'
];

$new_links_html = "";
foreach ($links as $link_start) {
    $start_pos = strpos($pastor, $link_start);
    if ($start_pos !== false) {
        $end_pos = strpos($pastor, '</a>', $start_pos) + 4;
        $link_html = substr($pastor, $start_pos, $end_pos - $start_pos);
        $new_links_html .= "                " . $link_html . "\n";
    }
}

// Insert new links into admin sidebar
$usher_link_end = strpos($admin, '</a>', strpos($admin, '<a href="?tab=usher_monitoring"'));
if ($usher_link_end !== false) {
    $admin = substr_replace($admin, "\n" . $new_links_html, $usher_link_end + 4, 0);
    echo "Successfully appended sidebar links\n";
} else {
    echo "Failed to insert sidebar links\n";
}

file_put_contents($admin_file, $admin);
echo "Admin dashboard updated successfully.\n";
