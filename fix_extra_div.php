<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

// Replace the duplicate div closing
$old = '                }
            </style>
            </div>
        <?php elseif ( == \'my_worship_tasks\'): ?>';

$new = '                }
            </style>
        <?php elseif ( == \'my_worship_tasks\'): ?>';

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Success\n";
?>
