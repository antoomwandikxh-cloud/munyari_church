<?php
$login_file = 'C:\\xampp\\htdocs\\munyari_church\\login.php';
$register_file = 'C:\\xampp\\htdocs\\munyari_church\\register_page.php';

$login = file_get_contents($login_file);
$register = file_get_contents($register_file);

// Replace User Icon Color (Green)
$login = preg_replace(
    '/(<span style="position: absolute; left: 12px; top: 50%; transform: translateY\(-50%\); color: )(var\(--text-muted\))([^>]+><svg[^>]+><path[^>]+d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z")/',
    '${1}#10b981$3',
    $login
);
$register = preg_replace(
    '/(<span style="position: absolute; left: 12px; top: 50%; transform: translateY\(-50%\); color: )(var\(--text-muted\))([^>]+><svg[^>]+><path[^>]+d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z")/',
    '${1}#10b981$3',
    $register
);

// Replace Map Icon Color (Blue/Indigo)
$register = preg_replace(
    '/(<span style="position: absolute; left: 12px; top: 50%; transform: translateY\(-50%\); color: )(var\(--text-muted\))([^>]+><svg[^>]+><path[^>]+d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z")/',
    '${1}#6366f1$3',
    $register
);

// Replace Lock Icon Color (Red)
$login = preg_replace(
    '/(<span style="position: absolute; left: 12px; top: 50%; transform: translateY\(-50%\); color: )(var\(--text-muted\))([^>]+><svg[^>]+><path[^>]+d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z")/',
    '${1}#ef4444$3',
    $login
);
$register = preg_replace(
    '/(<span style="position: absolute; left: 12px; top: 50%; transform: translateY\(-50%\); color: )(var\(--text-muted\))([^>]+><svg[^>]+><path[^>]+d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z")/',
    '${1}#ef4444$3',
    $register
);

file_put_contents($login_file, $login);
file_put_contents($register_file, $register);

echo "Colors updated successfully!";
?>
