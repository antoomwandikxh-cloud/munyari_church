<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Replace the two JS-based print buttons with direct links to print_village_members.php
    $old = "onclick=\"printVillageTable('village_table_<?= str_replace(' ', '_', \$v) ?>', '<?= \$v ?>', 'landscape')\" style=\"display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#2563eb,#6366f1);color:white;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;\">Print Landscape</button>";
    $new = "href=\"print_village_members.php?village=<?= urlencode(\$v) ?>&orientation=landscape\" target=\"_blank\" style=\"display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#2563eb,#6366f1);color:white;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;text-decoration:none;\">Print Landscape</a>";
    
    $old2 = "onclick=\"printVillageTable('village_table_<?= str_replace(' ', '_', \$v) ?>', '<?= \$v ?>', 'portrait')\" style=\"display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4f46e5,#4338ca);color:white;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;\">Print Portrait</button>";
    $new2 = "href=\"print_village_members.php?village=<?= urlencode(\$v) ?>&orientation=portrait\" target=\"_blank\" style=\"display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4f46e5,#4338ca);color:white;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;text-decoration:none;\">Print Portrait</a>";
    
    // Replace <button ... > with <a ... > 
    $c = str_replace('<button ' . $old, '<a ' . $new, $c);
    $c = str_replace('<button ' . $old2, '<a ' . $new2, $c);
    
    file_put_contents($file, $c);
    echo "Fixed buttons in $file\n";
}
?>
