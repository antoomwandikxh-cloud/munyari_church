<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

// Check if the fix took effect - look for the fixed women pattern
if (strpos($content, "womens ministry chairlady") !== false) {
    echo "Women fix: OK\n";
} else {
    echo "Women fix: MISSING - applying regex fix\n";
    
    // Fix with regex - swap the vice/main order
    $content = preg_replace(
        "/if \(preg_match\('\/vice women \(chairlady\|chairperson\|chairman\)\/', \\\$r\)\) return \[2, 0\];\s*if \(preg_match\('\/\^women \(chairlady\|chairperson\|chairman\)\$\/', \\\$r\)\) return \[2, 1\];/s",
        "if (preg_match('/^(women chairlady|women chairperson|women chairman|women ministry chairlady|women ministry chairperson|womens ministry chairlady|womens ministry chairperson)$/', \$r)) return [2, 0];\n                            if (preg_match('/vice women(s)? (chairlady|chairperson|chairman)/', \$r)) return [2, 1];",
        $content
    );
    file_put_contents($file, $content);
    echo "Applied regex fix to admin_dashboard.php\n";
}
?>
