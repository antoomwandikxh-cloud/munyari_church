<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// Replace the old block that shows other roles
$old = '        <?php
            $raw_roles = explode(\',\', $l[\'church_role\'] ?? \'\');
            $other_roles = [];
            foreach($raw_roles as $r) {
                $r = trim($r);
                if ($r && stripos($r, \'Village Leader\') === false) {
                    $other_roles[] = $r;
                }
            }
            if (!empty($other_roles)) {
                echo \'<div class="lr">\' . htmlspecialchars(implode(\', \', $other_roles)) . \'</div>\';
            }
        ?>';

// We just remove it entirely (replace with empty string)
$content = str_replace($old, '', $content);
file_put_contents($file, $content);
echo "Removed extra roles from print_all_villages.php\n";
?>
