<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $lines = file($file);
    foreach ($lines as $i => &$line) {
        // Fix the women block lines that are swapped
        // Line with vice returning [2,0] -> should be [2,1]
        if (strpos($line, "preg_match('/vice women (chairlady|chairperson|chairman)/'") !== false && strpos($line, 'return [2, 0]') !== false) {
            $line = str_replace('return [2, 0]', 'return [2, 1]', $line);
            echo "Fixed vice women line " . ($i+1) . " in $file\n";
        }
        // Line with main chair returning [2,1] -> should be [2,0]
        if (strpos($line, "preg_match('/^women (chairlady|chairperson|chairman)$/") !== false && strpos($line, 'return [2, 1]') !== false) {
            $line = str_replace('return [2, 1]', 'return [2, 0]', $line);
            // Also broaden the pattern
            $line = str_replace(
                "preg_match('/^women (chairlady|chairperson|chairman)$/'",
                "preg_match('/(^women.*(chairlady|chairperson|chairman)$|^womens.*(chairlady|chairperson|chairman)$|^.*ministry.*(chairlady|chairperson|chairman)$)/'",
                $line
            );
            echo "Fixed main women chair line " . ($i+1) . " in $file\n";
        }
    }
    file_put_contents($file, implode('', $lines));
}
?>
