<?php
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);
$old = <<<'HTML'
                            <option value="Sunday School">Sunday School</option>
                        </select>
HTML;
$new = <<<'HTML'
                            <option value="Sunday School">Sunday School</option>
                            <option value="Building">Building</option>
                        </select>
HTML;
if (strpos($content, '<option value="Building">Building</option>') === false) {
    $content = str_replace(str_replace("\r", "", $old), str_replace("\r", "", $new), $content);
    file_put_contents($file, $content);
    echo "Added Building to print dropdown in pastor_dashboard.php\n";
} else {
    echo "Already has Building in pastor_dashboard.php\n";
}
?>
