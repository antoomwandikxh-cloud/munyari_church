<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    $old = "var displayNames = (viceName && viceName !== 'N/A') ? (leaderName + ' / ' + viceName) : leaderName;";
    $new = "var displayNames = (viceName && viceName !== 'N/A') ? (leaderName + ' / ' + viceName) : leaderName;\n    var sigRoleTitle = (viceName && viceName !== 'N/A') ? (leaderRole + ' / VICE') : leaderRole;";
    $content = str_replace($old, $new, $content);
    
    $old2 = "w.document.write('<div style=\"text-align:center;\"><div style=\"font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;\">' + leaderRole + ' / VICE</div>');";
    $new2 = "w.document.write('<div style=\"text-align:center;\"><div style=\"font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;\">' + sigRoleTitle.toUpperCase() + '</div>');";
    $content = str_replace($old2, $new2, $content);
    
    file_put_contents($f, $content);
    echo "Updated $f\n";
}
?>
