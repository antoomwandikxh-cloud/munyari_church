<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $lines = file($file);
    $out = [];
    foreach ($lines as $i => $line) {
        $out[] = $line;
        // After the second ra[1]/rb[1] comparison, inject dept sub-sort before strcmp
        // We detect the strcmp line in the usort block
        if (strpos($line, "return strcmp(\$a['first_name'].\$a['last_name']") !== false
            && strpos($lines[$i-1] ?? '', 'ra[1]') !== false) {
            // Replace the strcmp line with dept sub-sort + strcmp
            array_pop($out); // Remove the strcmp we just added
            $out[] = "                     // Within plain Members (group 99), sub-sort by department\r\n";
            $out[] = "                     if (\$ra[0] === 99) {\r\n";
            $out[] = "                         \$drank = ['Elders'=>1,'Womens Ministry'=>2,'Youths'=>3,'Sunday School'=>4];\r\n";
            $out[] = "                         \$da = \$drank[\$a['department'] ?? ''] ?? 5;\r\n";
            $out[] = "                         \$db = \$drank[\$b['department'] ?? ''] ?? 5;\r\n";
            $out[] = "                         if (\$da !== \$db) return \$da - \$db;\r\n";
            $out[] = "                     }\r\n";
            $out[] = $line; // re-add the strcmp
        }
    }
    file_put_contents($file, implode('', $out));
    echo "Fixed: $file\n";
}
?>
