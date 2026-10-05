<?php
$old_func = "                        if (strpos(\$d, 'women') !== false || strpos(\$r, 'women') !== false) {
                            if (preg_match('/vice women (chairlady|chairperson|chairman)/', \$r)) return [2, 0];
                            if (preg_match('/^women (chairlady|chairperson|chairman)$/', \$r)) return [2, 1];
                            if (strpos(\$r, 'women secretary') !== false && strpos(\$r, 'vice') === false) return [2, 2];
                            if (strpos(\$r, 'vice women secretary') !== false) return [2, 3];
                            if (strpos(\$r, 'women treasurer') !== false) return [2, 4];
                            return [2, 5];
                        }";

// Fixed: main leader [x,0], vice [x,1], secretary [x,2], vice sec [x,3], treasurer [x,4], sub leaders [x,5], members [x,6]
$new_func = "                        if (strpos(\$d, 'women') !== false || strpos(\$r, 'women') !== false) {
                            if (preg_match('/^(women chairlady|women chairperson|women chairman|women ministry chairlady|women ministry chairperson|women ministry chairman|womens ministry chairlady|womens ministry chairperson|womens ministry chairman)$/', \$r)) return [2, 0];
                            if (preg_match('/vice women (chairlady|chairperson|chairman)/', \$r) || preg_match('/vice womens (chairlady|chairperson|chairman)/', \$r)) return [2, 1];
                            if (strpos(\$r, 'women secretary') !== false && strpos(\$r, 'vice') === false) return [2, 2];
                            if (strpos(\$r, 'vice women secretary') !== false) return [2, 3];
                            if (strpos(\$r, 'women treasurer') !== false) return [2, 4];
                            return [2, 5];
                        }";

// Also fix elders: main chair before vice
$old_elders = "                        if (strpos(\$d, 'elder') !== false || strpos(\$r, 'elder') !== false) {
                            if (preg_match('/^(elder chairman|elder chairperson|elder chairlady)$/', \$r)) return [1, 0];
                            if (strpos(\$r, 'vice elder chair') !== false) return [1, 1];
                            if (strpos(\$r, 'elder secretary') !== false && strpos(\$r, 'vice') === false) return [1, 2];
                            if (strpos(\$r, 'vice elder secretary') !== false) return [1, 3];
                            if (strpos(\$r, 'elder treasurer') !== false) return [1, 4];
                            return [1, 5];
                        }";
$new_elders = "                        if (strpos(\$d, 'elder') !== false || strpos(\$r, 'elder') !== false) {
                            if (preg_match('/^(elders chairman|elders chairperson|elders chairlady|elder chairman|elder chairperson|elder chairlady)$/', \$r)) return [1, 0];
                            if (strpos(\$r, 'vice elder') !== false) return [1, 1];
                            if (strpos(\$r, 'elder secretary') !== false && strpos(\$r, 'vice') === false) return [1, 2];
                            if (strpos(\$r, 'vice elder secretary') !== false) return [1, 3];
                            if (strpos(\$r, 'elder treasurer') !== false) return [1, 4];
                            return [1, 5];
                        }";

// Fix youths
$old_youth = "                        if (strpos(\$d, 'youth') !== false || strpos(\$r, 'youth') !== false) {
                            if (preg_match('/^(youth chairman|youth chairperson|youth chairlady)$/', \$r)) return [3, 0];
                            if (strpos(\$r, 'vice youth chair') !== false) return [3, 1];
                            if (strpos(\$r, 'youth secretary') !== false && strpos(\$r, 'vice') === false) return [3, 2];
                            if (strpos(\$r, 'vice youth secretary') !== false) return [3, 3];
                            if (strpos(\$r, 'youth treasurer') !== false) return [3, 4];
                            if (strpos(\$r, 'mama youth') !== false || strpos(\$r, 'baba youth') !== false) return [3, 5];
                            return [3, 6];
                        }";
$new_youth = "                        if (strpos(\$d, 'youth') !== false || strpos(\$r, 'youth') !== false) {
                            if (preg_match('/^(youth chairman|youth chairperson|youth chairlady|youths chairman|youths chairperson|youths chairlady)$/', \$r)) return [3, 0];
                            if (strpos(\$r, 'vice youth chair') !== false || strpos(\$r, 'vice youths chair') !== false) return [3, 1];
                            if (strpos(\$r, 'youth secretary') !== false && strpos(\$r, 'vice') === false) return [3, 2];
                            if (strpos(\$r, 'vice youth secretary') !== false) return [3, 3];
                            if (strpos(\$r, 'youth treasurer') !== false) return [3, 4];
                            if (strpos(\$r, 'mama youth') !== false || strpos(\$r, 'baba youth') !== false) return [3, 5];
                            return [3, 6];
                        }";

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    $content = str_replace($old_func, $new_func, $content);
    $content = str_replace($old_elders, $new_elders, $content);
    $content = str_replace($old_youth, $new_youth, $content);
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
