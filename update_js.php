<?php
$file = 'register_page.php';
$content = file_get_contents($file);

// 1. In HTML <select name="department" ... onchange="seqUnlock('department','password')"> -> seqUnlock('department','desired_role_pref')
$content = str_replace(
    'onchange="seqUnlock(\'department\',\'password\')" disabled required>',
    'onchange="seqUnlock(\'department\',\'desired_role_pref\')" disabled required>',
    $content
);

// 2. In JS document.getElementById('department').addEventListener('change', function() { seqUnlock('department', 'password'); ... });
$content = str_replace(
    'seqUnlock(\'department\', \'password\');',
    'seqUnlock(\'department\', \'desired_role_pref\');',
    $content
);

// 3. In JS toggleFields function else branch
// We need to show/hide the desired_role_group too.
$content = str_replace(
    'const roleInput = document.getElementById(\'role\');',
    "const roleInput = document.getElementById('role');\n        const desiredRoleGroup = document.getElementById('desired_role_group');",
    $content
);

$content = str_replace(
    'roleGroup.style.display = \'block\';',
    "roleGroup.style.display = 'block';\n            if(desiredRoleGroup) desiredRoleGroup.style.display = 'none';",
    $content
);

$content = str_replace(
    'roleGroup.style.display = \'none\';',
    "roleGroup.style.display = 'none';\n            if(desiredRoleGroup) desiredRoleGroup.style.display = 'block';",
    $content
);

file_put_contents($file, $content);
echo "Updated JS in $file\n";
?>
