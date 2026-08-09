<?php
$content = file_get_contents('test_assign_roles.php');
$lines = explode("\n", $content);
$tokens = token_get_all($content);
$braces = 0;
$control_structs = [];

foreach ($tokens as $token) {
    if (!is_array($token)) {
        if ($token === '{') {
            $braces++;
            $control_structs[] = ['{', $line];
        } elseif ($token === '}') {
            $braces--;
            array_pop($control_structs);
        }
    } else {
        $line = $token[2];
        if ($token[1] === '{') {
            $braces++;
            $control_structs[] = ['{', $line];
        } elseif ($token[1] === '}') {
            $braces--;
            array_pop($control_structs);
        }
    }
}
echo "Braces remaining open: $braces\n";
if ($braces > 0) {
    foreach ($control_structs as $s) {
        $l = $s[1];
        echo "Line $l: " . trim($lines[$l - 1]) . "\n";
    }
}
?>
