<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Inject the search bar by using regex to bypass whitespace issues
    $c = preg_replace(
        '/<div style="display:flex;gap:10px;">(\s*)<button onclick="printDepartment\(/', 
        '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">$1<input type="text" placeholder="Search <?= htmlspecialchars($dept) ?>..." style="padding:8px 12px; border:1px solid var(--border-color); border-radius:8px; width:220px; font-size:0.85rem;" onkeyup="filterDeptTable(this, \'dept_print_<?= str_replace(\' \', \'\', $dept) ?>\')">$1<button onclick="printDepartment(', 
        $c
    );
    
    file_put_contents($file, $c);
    echo "Injected search inputs via regex in $file\n";
}
?>
