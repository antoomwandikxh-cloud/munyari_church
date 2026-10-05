<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$old_buttons = '<div style="display:flex;gap:10px;">
                                    <button onclick="printDepartment(';

$new_buttons = '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                    <input type="text" placeholder="Search <?= htmlspecialchars($dept) ?>..." style="padding:8px 12px; border:1px solid var(--border-color); border-radius:8px; width:220px; font-size:0.85rem;" onkeyup="filterDeptTable(this, \'dept_print_<?= str_replace(\' \', \'\', $dept) ?>\')">
                                    <button onclick="printDepartment(';

$js_func = <<<JS
<script>
function filterDeptTable(inputElement, tableContainerId) {
    let filter = inputElement.value.toLowerCase();
    let container = document.getElementById(tableContainerId);
    if (!container) return;
    let trs = container.querySelectorAll("tbody tr");
    for (let i = 0; i < trs.length; i++) {
        let text = trs[i].textContent || trs[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            trs[i].style.display = "";
        } else {
            trs[i].style.display = "none";
        }
    }
}
</script>
</body>
JS;

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Inject the search bar
    $c = str_replace($old_buttons, $new_buttons, $c);
    
    // Inject the JS function before </body>
    if (strpos($c, 'function filterDeptTable') === false) {
        // Find the last </body>
        $pos = strrpos($c, '</body>');
        if ($pos !== false) {
            $c = substr_replace($c, $js_func, $pos, 7);
        }
    }
    
    file_put_contents($file, $c);
    echo "Added search to departments in $file\n";
}
?>
