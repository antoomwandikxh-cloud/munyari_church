<?php
$content = file_get_contents('role_departments.php');

$new_func = <<<'EOT'
function get_best_role_from_string($church_role_str) {
    if (empty($church_role_str)) return 'Member';
    $roles = array_map('trim', explode(',', $church_role_str));
    $best_role = 'Leader';
    $best_rank = 999;
    foreach ($roles as $r) {
        if ($r === '') continue;
        $l = strtolower($r);
        $rank = 50;
        
        if (preg_match('/^(youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)$/', $l)) $rank = 1;
        elseif (preg_match('/^vice (youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)$/', $l)) $rank = 2;
        elseif (preg_match('/^(general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary$/', $l)) $rank = 3;
        elseif (preg_match('/^vice (general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary$/', $l)) $rank = 4;
        elseif (preg_match('/^(youth |women |elder |sunday school |building )?treasurer$/', $l)) $rank = 5;
        elseif (preg_match('/^vice (youth |women |elder |sunday school |building )?treasurer$/', $l)) $rank = 6;
        elseif (preg_match('/^(mama youth|baba youth)$/', $l)) $rank = 7;
        elseif (preg_match('/^head usher$/', $l)) $rank = 10;
        elseif (preg_match('/^usher$/', $l)) $rank = 11;
        elseif (preg_match('/^discipline master$/', $l)) $rank = 21;
        elseif (preg_match('/^prayer coordinator$/', $l)) $rank = 23;
        elseif (preg_match('/^choir leader$/', $l)) $rank = 25;
        elseif ($l === 'member') $rank = 90;
        
        if ($rank < $best_rank) {
            $best_rank = $rank;
            $best_role = $r;
        }
    }
    return $best_role;
}

function role_rank_case_sql($column = 'church_role') {
EOT;

$content = str_replace("function role_rank_case_sql(\$column = 'church_role') {", $new_func, $content);
file_put_contents('role_departments.php', $content);
echo "Added function to role_departments.php\n";
?>
