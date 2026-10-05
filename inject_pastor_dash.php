<?php
$f = 'C:\xampp\htdocs\munyari_church\pastor_dashboard.php';
$c = file_get_contents($f);

$old = <<<'CODE'
                    $mem = $conn->query("SELECT pending_church_village, pending_desired_role_pref FROM members WHERE id = $update_id")->fetch_assoc();
                    if ($mem && $mem['pending_church_village']) {
                        if ($action === 'approve') {
                            $new_v = $conn->real_escape_string($mem['pending_church_village']);
                            $new_drp = $mem['pending_desired_role_pref'] ? "'" . $conn->real_escape_string($mem['pending_desired_role_pref']) . "'" : "NULL";
                            $conn->query("UPDATE members SET church_village = '$new_v', desired_role_pref = $new_drp, pending_church_village = NULL, pending_desired_role_pref = NULL WHERE id = $update_id");
                            
                            $msg = $conn->real_escape_string("Your request to update your church village to $new_v has been approved.");
                            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($update_id, 'member', '$msg')");
                            echo "<div style='background:rgba(16,185,129,0.1);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;'>Update approved!</div>";
                        } else if ($action === 'reject') {
CODE;

$new = <<<'CODE'
                    $mem = $conn->query("SELECT first_name, last_name, pending_church_village, pending_desired_role_pref FROM members WHERE id = $update_id")->fetch_assoc();
                    if ($mem && $mem['pending_church_village']) {
                        if ($action === 'approve') {
                            $new_v = $conn->real_escape_string($mem['pending_church_village']);
                            $new_drp_val = $mem['pending_desired_role_pref'];
                            $new_drp = $new_drp_val ? "'" . $conn->real_escape_string($new_drp_val) . "'" : "NULL";
                            $conn->query("UPDATE members SET church_village = '$new_v', desired_role_pref = $new_drp, pending_church_village = NULL, pending_desired_role_pref = NULL WHERE id = $update_id");
                            
                            $msg = $conn->real_escape_string("Your request to update your church village to $new_v has been approved.");
                            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($update_id, 'member', '$msg')");
                            
                            if ($new_drp_val === 'Worshipper') {
                                $wl_name  = $conn->real_escape_string($mem['first_name'] . ' ' . $mem['last_name']);
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

                            echo "<div style='background:rgba(16,185,129,0.1);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;'>Update approved!</div>";
                        } else if ($action === 'reject') {
CODE;

$c = str_replace($old, $new, $c);
file_put_contents($f, $c);
echo "Updated pastor_dashboard.php\n";
?>
