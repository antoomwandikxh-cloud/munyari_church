<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // 1. Fix colspan from 8 to 9 in the print header title row
    $content = str_replace(
        '<th colspan="8" style="border:none; padding: 0; font-weight:normal; background:white;">',
        '<th colspan="9" style="border:none; padding: 0; font-weight:normal; background:white;">',
        $content
    );
    
    // 2. Ensure $status_str and $status_color are set INSIDE the print foreach loop
    // They are already set — just add vertical-align:middle to the dot image
    // and fix the status td to also ensure vertical alignment
    $content = str_replace(
        '<td style="padding:5px 8px;border:1px solid #ddd;color:<?= $status_color ?>;font-weight:600;">
                                        <img src="data:image/svg+xml;base64,<?= base64_encode(\'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="\'.  $status_color.\'"/></svg>\') ?>" style="width:11px;height:11px;vertical-align:middle;margin-right:4px;" alt=""> <?= $status_str ?>
                                    </td>',
        '<td style="padding:5px 8px;border:1px solid #ddd;color:<?= $status_color ?>;font-weight:600;white-space:nowrap;vertical-align:middle;">
                                        <span style="display:inline-flex;align-items:center;gap:4px;"><img src="data:image/svg+xml;base64,<?= base64_encode(\'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="\'. $status_color .\'"/></svg>\') ?>" style="width:11px;height:11px;flex-shrink:0;" alt=""><?= $status_str ?></span>
                                    </td>',
        $content
    );
    
    file_put_contents($file, $content);
    echo "Fixed alignment in $file\n";
}
?>
