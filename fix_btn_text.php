<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

$content = str_replace(
    'Print List
                    </a>',
    'Print Village Directory
                    </a>',
    $content
);

file_put_contents($file, $content);
echo "Success\n";
?>
