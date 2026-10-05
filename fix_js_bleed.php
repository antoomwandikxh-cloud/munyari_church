<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$broken_injection = <<<BROKEN
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
BROKEN;
$broken_injection = str_replace("\r\n", "\n", $broken_injection); // Normalize just in case

$js_func = <<<JS

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
JS;

foreach ($files as $file) {
    $c = file_get_contents($file);
    $c = str_replace("\r\n", "\n", $c);
    
    // Remove the broken injection
    $c = str_replace($broken_injection, "</body>", $c);
    
    // Check if it's already properly inside a script tag
    if (strpos($c, "function filterDeptTable(") === false) {
        // Inject it right before the last </script>
        $pos = strrpos($c, '</script>');
        if ($pos !== false) {
            $c = substr_replace($c, $js_func . "\n", $pos, 0);
        }
    }
    
    // Also, some files are missing a final </body> tag before </html>
    if (strpos($c, '</body>') === false || strrpos($c, '</body>') < strrpos($c, '</html>') - 20) {
        $c = str_replace('</html>', "</body>\n</html>", $c);
    }

    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
?>
