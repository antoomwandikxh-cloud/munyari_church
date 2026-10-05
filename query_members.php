<?php
$conn = new mysqli('localhost', 'root', '', 'munyari_church');
$res = $conn->query("SELECT first_name, last_name, church_village, is_village_leader, church_role FROM members WHERE church_village LIKE '%koritho%'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
