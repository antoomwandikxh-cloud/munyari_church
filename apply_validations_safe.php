<?php
$files = [
    'admin_dashboard.php',
    'pastor_dashboard.php',
    'member_dashboard.php'
];

$search_script = <<<HTML
    <script>
        window.tabNotificationBadges = <?= json_encode(\$tab_badges) ?>;
    </script>
HTML;

$replace_script = <<<HTML
    <?php
    \$all_phones = [];
    \$ph_res = \$conn->query("SELECT phone FROM members WHERE phone IS NOT NULL AND phone != ''");
    if(\$ph_res) { while(\$row = \$ph_res->fetch_assoc()){ \$all_phones[] = \$row['phone']; } }
    ?>
    <script>
        window.tabNotificationBadges = <?= json_encode(\$tab_badges) ?>;
        window.registeredPhones = <?= json_encode(\$all_phones) ?>;
    </script>
HTML;

foreach ($files as $f) {
    $c = file_get_contents($f);
    
    // Inject the PHP phones array safely at the bottom
    if (strpos($c, '$all_phones = [];') === false) {
        $c = str_replace($search_script, $replace_script, $c);
    }
    
    // Fix regex patterns
    $c = str_replace('pattern="[A-Za-z0-9\s,.-]+"', 'pattern="[A-Za-z\s]+"', $c);
    $c = str_replace('pattern="[A-Za-z\s,.-]+"', 'pattern="[A-Za-z\s]+"', $c);
    
    // Remove duplicated validateInput and seqUnlock blocks
    $c = preg_replace('/function validateInput\(input, type\)\s*\{.*?\n                \}\s*/s', '', $c);
    $c = preg_replace('/function seqUnlock\(currentId, nextId\)\s*\{.*?\n                \}\s*/s', '', $c);
    
    // There are some global ones at the end without indentation, let's remove them too
    $c = preg_replace('/function validateInput\(input, type\)\s*\{.*?\n    \}\s*/s', '', $c);

    // Let's just remove any standalone validateInput from the files
    $c = preg_replace('/<script>\s*function validateInput\(input, type\)\s*\{.*?\n    \}\s*<\/script>\s*/s', '', $c);
    
    file_put_contents($f, $c);
    echo "$f processed.\n";
}
?>
