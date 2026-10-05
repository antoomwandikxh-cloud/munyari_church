<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// 1. Update the leader card roles
$old_lcard = '        <div class="ln"><?= htmlspecialchars($l[\'first_name\'] . \' \' . $l[\'last_name\']) ?></div>
        <div class="lv" style="color:<?= $vc ?>;"><?= $v ?> — Village Leader</div>
        <div class="lr"><?= htmlspecialchars($l[\'church_role\'] ?: \'Village Leader\') ?></div>';

$new_lcard = '        <div class="ln"><?= htmlspecialchars($l[\'first_name\'] . \' \' . $l[\'last_name\']) ?></div>
        <div class="lv" style="color:<?= $vc ?>;"><?= $v ?> — Village Leader</div>
        <?php
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
$content = str_replace($old_lcard, $new_lcard, $content);

// 2. Center ribbon and replace "Participant" with "Member"
$old_ribbon = '<div class="sec-badge" style="background:<?= $vc ?>;"><?= htmlspecialchars($v) ?> Village — <?= $total ?> Participant<?= $total != 1 ? \'s\' : \'\' ?></div>';
$new_ribbon = '<div style="text-align:center;"><div class="sec-badge" style="background:<?= $vc ?>;"><?= htmlspecialchars($v) ?> Village — <?= $total ?> Member<?= $total != 1 ? \'s\' : \'\' ?></div></div>';
$content = str_replace($old_ribbon, $new_ribbon, $content);

file_put_contents($file, $content);
echo "Updated print_all_villages.php\n";
?>
