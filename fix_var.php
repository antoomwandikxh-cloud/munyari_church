<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    
    $old = <<<'EOT'
    var displayNames = (viceName && viceName !== 'N/A') ? (leaderName + ' / ' + viceName) : leaderName;
    var sigRoleTitle = (viceName && viceName !== 'N/A') ? (leaderRole + ' / VICE') : leaderRole;
    var leaderPic  = leaderSpan ? leaderSpan.getAttribute('data-pic') : 'uploads/default_avatar.png';
    var leaderRole = leaderSpan ? leaderSpan.getAttribute('data-role') : 'Chairperson';
EOT;
    $old = str_replace("\r", "", $old);
    
    $new = <<<'EOT'
    var leaderPic  = leaderSpan ? leaderSpan.getAttribute('data-pic') : 'uploads/default_avatar.png';
    var leaderRole = leaderSpan ? leaderSpan.getAttribute('data-role') : 'Chairperson';
    var vicePic    = leaderSpan ? leaderSpan.getAttribute('data-vice-pic') : 'uploads/default_avatar.png';
    var viceRole   = leaderSpan ? leaderSpan.getAttribute('data-vice-role') : 'Vice Chairperson';
    
    var displayNames = (viceName && viceName !== 'N/A') ? (leaderName + ' / ' + viceName) : leaderName;
    var sigRoleTitle = (viceName && viceName !== 'N/A') ? (leaderRole + ' / VICE') : leaderRole;
EOT;
    
    $content = str_replace("\r", "", $content);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($f, $content);
        echo "Replaced var ordering in $f\n";
    } else {
        echo "Could not find text in $f\n";
    }
}
?>
