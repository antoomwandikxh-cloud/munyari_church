<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Add Building to the totals associative array
    $old_assoc = <<<'PHP'
                        $target_depts = [
                            'General Church' => 'General Church',
                            'Youths' => 'Youth Ministry',
                            'Womens Ministry' => "Women's Ministry",
                            'Elders' => 'Elders',
                            'Sunday School' => 'Sunday School'
                        ];
PHP;
    $new_assoc = <<<'PHP'
                        $target_depts = [
                            'General Church' => 'General Church',
                            'Youths' => 'Youth Ministry',
                            'Womens Ministry' => "Women's Ministry",
                            'Elders' => 'Elders',
                            'Sunday School' => 'Sunday School',
                            'Building' => 'Building Dept'
                        ];
PHP;
    $content = str_replace(str_replace("\r", "", $old_assoc), str_replace("\r", "", $new_assoc), $content);

    // Ensure Building is in the <select name="dept" id="finPrintDept">
    $old_select = <<<'HTML'
                            <option value="Sunday School">Sunday School</option>
                        </select>
HTML;
    $new_select = <<<'HTML'
                            <option value="Sunday School">Sunday School</option>
                            <option value="Building">Building</option>
                        </select>
HTML;
    // Check if it's not already there
    if (strpos($content, '<option value="Building">Building</option>') === false) {
        $content = str_replace(str_replace("\r", "", $old_select), str_replace("\r", "", $new_select), $content);
    }
    
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
?>
