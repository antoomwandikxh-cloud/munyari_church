<?php
$c = file_get_contents('admin_dashboard.php');

// Inject setFieldStatus_filling at start of oninput with seqUnlock
$c = preg_replace_callback(
    '/oninput="([^"]*seqUnlock[^"]*)"/i',
    function($m) {
        $v = $m[1];
        if (strpos($v, 'setFieldStatus_filling') === false) {
            $v = "setFieldStatus_filling(this.id); " . $v;
        }
        return 'oninput="' . $v . '"';
    },
    $c
);

// Add onblur to inputs with seqUnlock in oninput (no existing onblur)
$c = preg_replace_callback(
    '/<input([^>]*oninput="[^"]*seqUnlock[^"]*"[^>]*)>/i',
    function($m) {
        $attrs = $m[1];
        if (strpos($attrs, 'onblur') === false) {
            $attrs .= " onblur=\"setFieldStatus_blur(this)\"";
        }
        return '<input' . $attrs . '>';
    },
    $c
);

file_put_contents('admin_dashboard.php', $c);
echo "Done\n";
?>
