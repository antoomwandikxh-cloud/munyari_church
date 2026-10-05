<?php
// Do the same for pastor_dashboard.php
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);

if (strpos($content, "womens ministry chairlady") !== false) {
    echo "Women fix: OK in pastor\n";
} else {
    $content = preg_replace(
        "/if \(preg_match\('\/vice women \(chairlady\|chairperson\|chairman\)\/', \\\$r\)\) return \[2, 0\];\s*if \(preg_match\('\/\^women \(chairlady\|chairperson\|chairman\)\$\/', \\\$r\)\) return \[2, 1\];/s",
        "if (preg_match('/^(women chairlady|women chairperson|women chairman|women ministry chairlady|women ministry chairperson|womens ministry chairlady|womens ministry chairperson)$/', \$r)) return [2, 0];\n                            if (preg_match('/vice women(s)? (chairlady|chairperson|chairman)/', \$r)) return [2, 1];",
        $content
    );
    file_put_contents($file, $content);
    echo "Applied regex fix to pastor_dashboard.php\n";
}

// Also universally: if role contains "chairlady/chairman/chairperson" without "vice" and not already caught, 
// treat as department leader and put them high. The real fix is a catch-all at the end of each dept block.
// Actually the main issue is that "Women Ministry Chairlady" doesn't match "^women chairlady$"
// Let's add a broader catch for any chairlady/chairman that contains the dept name

// Women - broaden match
$content = str_replace(
    "if (preg_match('/^(women chairlady|women chairperson|women chairman|women ministry chairlady|women ministry chairperson|womens ministry chairlady|womens ministry chairperson)$/", 
    "if (preg_match('/^(women.*(chairlady|chairperson|chairman)|.*ministry.*(chairlady|chairperson|chairman))$/",
    $content
);

file_put_contents($file, $content);
echo "Broadened women match in pastor\n";
?>
