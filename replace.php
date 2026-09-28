<?php
$content = file_get_contents('role_departments.php');
$old = <<<'EOT'
        CASE 
            WHEN LOWER($column) LIKE '%youth chairperson%' OR LOWER($column) LIKE '%women chairlady%' OR LOWER($column) LIKE '%elder chairman%' OR LOWER($column) LIKE '%sunday school patron%' OR LOWER($column) LIKE '%general church secretary%' THEN 1
            WHEN LOWER($column) LIKE '%vice youth chairperson%' OR LOWER($column) LIKE '%vice women chairlady%' OR LOWER($column) LIKE '%vice elder chairman%' OR LOWER($column) LIKE '%vice sunday school patron%' THEN 2
            WHEN LOWER($column) LIKE '%youth secretary%' OR LOWER($column) LIKE '%women secretary%' OR LOWER($column) LIKE '%elder secretary%' OR LOWER($column) LIKE '%sunday school secretary%' THEN 3
            WHEN LOWER($column) LIKE '%vice church secretary%' OR LOWER($column) LIKE '%vice youth secretary%' OR LOWER($column) LIKE '%vice women secretary%' OR LOWER($column) LIKE '%vice elder secretary%' OR LOWER($column) LIKE '%vice sunday school secretary%' THEN 4
            WHEN LOWER($column) LIKE '%youth treasurer%' OR LOWER($column) LIKE '%women treasurer%' OR LOWER($column) LIKE '%elder treasurer%' OR LOWER($column) LIKE '%sunday school treasurer%' OR (LOWER($column) LIKE '%treasurer%' AND LOWER($column) NOT LIKE '%building treasurer%') THEN 5
            WHEN LOWER($column) LIKE '%mama youth%' OR LOWER($column) LIKE '%baba youth%' THEN 6
            WHEN LOWER($column) LIKE '%head usher%' THEN 10
            WHEN LOWER($column) LIKE '%usher%' AND LOWER($column) NOT LIKE '%head usher%' THEN 11
            WHEN LOWER($column) LIKE '%building chairperson%' THEN 12
            WHEN LOWER($column) LIKE '%vice building chairperson%' THEN 13
            WHEN LOWER($column) LIKE '%building secretary%' THEN 14
            WHEN LOWER($column) LIKE '%vice building secretary%' THEN 15
            WHEN LOWER($column) LIKE '%building treasurer%' THEN 16
            WHEN LOWER($column) LIKE '%organizing secretary%' THEN 20
            WHEN LOWER($column) LIKE '%discipline master%' THEN 21
            WHEN LOWER($column) LIKE '%graduands secretary%' THEN 22
            WHEN LOWER($column) LIKE '%prayer coordinator%' THEN 23
            WHEN LOWER($column) LIKE '%sport secretary%' OR LOWER($column) LIKE '%sports secretary%' THEN 24
            WHEN LOWER($column) LIKE '%choir leader%' THEN 25
            WHEN LOWER(TRIM(COALESCE($column, ''))) = 'member' THEN 90
            WHEN TRIM(COALESCE($column, '')) = '' THEN 90
            ELSE 40
        END
EOT;

$old = str_replace("\r", "", $old);
$content = str_replace("\r", "", $content);

$new_repl = <<<'EOT'
        CASE 
            WHEN LOWER($column) REGEXP '(^|, *)(youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)( *,|$)' THEN 1
            WHEN LOWER($column) REGEXP '(^|, *)vice (youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)( *,|$)' THEN 2
            WHEN LOWER($column) REGEXP '(^|, *)(general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary( *,|$)' THEN 3
            WHEN LOWER($column) REGEXP '(^|, *)vice (general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary( *,|$)' THEN 4
            WHEN LOWER($column) REGEXP '(^|, *)(youth |women |elder |sunday school |building )?treasurer( *,|$)' THEN 5
            WHEN LOWER($column) REGEXP '(^|, *)vice (youth |women |elder |sunday school |building )?treasurer( *,|$)' THEN 6
            WHEN LOWER($column) REGEXP '(^|, *)(mama youth|baba youth)( *,|$)' THEN 7
            WHEN LOWER($column) REGEXP '(^|, *)head usher( *,|$)' THEN 10
            WHEN LOWER($column) REGEXP '(^|, *)usher( *,|$)' THEN 11
            WHEN LOWER($column) REGEXP '(^|, *)discipline master( *,|$)' THEN 21
            WHEN LOWER($column) REGEXP '(^|, *)prayer coordinator( *,|$)' THEN 23
            WHEN LOWER($column) REGEXP '(^|, *)choir leader( *,|$)' THEN 25
            WHEN LOWER(TRIM(COALESCE($column, ''))) = 'member' THEN 90
            WHEN TRIM(COALESCE($column, '')) = '' THEN 90
            ELSE 40
        END
EOT;

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new_repl, $content);
    file_put_contents('role_departments.php', $content);
    echo "Success\n";
} else {
    echo "Could not find old text\n";
}
?>
