<?php
$f = 'C:\xampp\htdocs\munyari_church\member_dashboard.php';
$c = file_get_contents($f);

$old = <<<'CODE'
        // Notify worship leaders/pastor/admin if new worshipper
        if ($drp === 'Worshipper') {
            $wl_name  = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
            $wl_notif = $conn->real_escape_string("New worshipper $wl_name from $cv village has joined the worship team.");
            $wleaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND LOWER(TRIM(church_role)) IN ('worship leader','vice worship leader')");
            if ($wleaders) { while ($wl = $wleaders->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$wl['id']},'member','$wl_notif')"); } }
            $pastors_q = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors_q) { while ($p = $pastors_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$p['id']},'pastor','$wl_notif')"); } }
            $admins_q = $conn->query("SELECT id FROM admins");
            if ($admins_q) { while ($a = $admins_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$a['id']},'admin','$wl_notif')"); } }
        }
CODE;

$new = <<<'CODE'
        // Notify worship leaders/pastor/admin if new worshipper
        if ($drp === 'Worshipper') {
            $wl_name  = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
            $wc_res = $conn->query("SELECT COUNT(*) as c FROM members WHERE is_approved=1 AND (LOWER(TRIM(church_role)) LIKE '%worshipper%' OR LOWER(TRIM(department)) LIKE '%worship%' OR desired_role_pref='Worshipper')");
            $wc = $wc_res ? $wc_res->fetch_assoc()['c'] : 0;
            $wl_notif = $conn->real_escape_string("A new worshipper ($wl_name) has joined! Total worshippers is now $wc.");
            
            $wleaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND LOWER(TRIM(church_role)) IN ('worship leader','vice worship leader')");
            if ($wleaders) { while ($wl = $wleaders->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$wl['id']},'member','$wl_notif')"); } }
            $pastors_q = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors_q) { while ($p = $pastors_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$p['id']},'pastor','$wl_notif')"); } }
            $admins_q = $conn->query("SELECT id FROM admins");
            if ($admins_q) { while ($a = $admins_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$a['id']},'admin','$wl_notif')"); } }
        }
CODE;

$c = str_replace($old, $new, $c);
file_put_contents($f, $c);
echo "Updated member_dashboard.php\n";
?>
