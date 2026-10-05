<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\print_all_leaders.php';
$content = file_get_contents($file);

// Replace header
$old_h = "        <th>Name</th>\n        <th>Role / Position</th>\n        <th>Phone Number</th>\n        <th>Church Village</th>\n        <th>Residence / Area</th>";
$new_h = "        <th>Name</th>\n        <th>Phone Number</th>\n        <th>Church Village</th>\n        <th>Residence / Area</th>\n        <th>Role / Position</th>";
$content = str_replace($old_h, $new_h, $content);
$old_h2 = "        <th>Name</th>\r\n        <th>Role / Position</th>\r\n        <th>Phone Number</th>\r\n        <th>Church Village</th>\r\n        <th>Residence / Area</th>";
$new_h2 = "        <th>Name</th>\r\n        <th>Phone Number</th>\r\n        <th>Church Village</th>\r\n        <th>Residence / Area</th>\r\n        <th>Role / Position</th>";
$content = str_replace($old_h2, $new_h2, $content);

// Replace body
$old_b = "        <td class=\"td-name\"><?=htmlspecialchars(\$r['name'])?></td>\n        <td class=\"td-role\"><?=htmlspecialchars(\$r['role'])?></td>\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['phone'])?></td>\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['village'])?></td>\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['residence'])?></td>";
$new_b = "        <td class=\"td-name\"><?=htmlspecialchars(\$r['name'])?></td>\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['phone'])?></td>\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['village'])?></td>\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['residence'])?></td>\n        <td class=\"td-role\"><?=htmlspecialchars(\$r['role'])?></td>";
$content = str_replace($old_b, $new_b, $content);
$old_b2 = "        <td class=\"td-name\"><?=htmlspecialchars(\$r['name'])?></td>\r\n        <td class=\"td-role\"><?=htmlspecialchars(\$r['role'])?></td>\r\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['phone'])?></td>\r\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['village'])?></td>\r\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['residence'])?></td>";
$new_b2 = "        <td class=\"td-name\"><?=htmlspecialchars(\$r['name'])?></td>\r\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['phone'])?></td>\r\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['village'])?></td>\r\n        <td class=\"td-meta\"><?=htmlspecialchars(\$r['residence'])?></td>\r\n        <td class=\"td-role\"><?=htmlspecialchars(\$r['role'])?></td>";
$content = str_replace($old_b2, $new_b2, $content);

file_put_contents($file, $content);
echo "Done\n";
?>
