<?php
$file = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$content = file_get_contents($file);

// 1. Replace announcement handler to also send notifications
$old_handler = '              // Handle Posting Announcement
              if ($_SERVER[\'REQUEST_METHOD\'] == \'POST\' && isset($_POST[\'post_village_announcement\'])) {
                  $msg = $conn->real_escape_string($_POST[\'message\']);
                  $v = $conn->real_escape_string($member[\'church_village\']);
                  $conn->query("INSERT INTO village_announcements (village, leader_id, message) VALUES (\'$v\', $member_id, \'$msg\')");
                  echo "<div style=\'background:rgba(16,185,129,0.1);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;\'>Announcement posted to your village!</div>";
              }';

$new_handler = '              // Handle Posting Announcement
              if ($_SERVER[\'REQUEST_METHOD\'] == \'POST\' && isset($_POST[\'post_village_announcement\'])) {
                  $msg = $conn->real_escape_string(trim($_POST[\'message\']));
                  $v = $conn->real_escape_string($member[\'church_village\']);
                  if (!empty($msg)) {
                      $conn->query("INSERT INTO village_announcements (village, leader_id, message) VALUES (\'$v\', $member_id, \'$msg\')");
                      // Notify all members in this village (excluding the leader who posted)
                      $village_members_q = $conn->query("SELECT id FROM members WHERE church_village = \'$v\' AND is_approved = 1 AND id != $member_id");
                      if ($village_members_q && $village_members_q->num_rows > 0) {
                          $leader_name = htmlspecialchars($member[\'first_name\'] . \' \' . $member[\'last_name\']);
                          $notif_msg = $conn->real_escape_string("New village announcement from your Village Leader in " . $member[\'church_village\'] . " village");
                          while ($vm = $village_members_q->fetch_assoc()) {
                              $vm_id = (int)$vm[\'id\'];
                              $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read) VALUES ($vm_id, \'member\', \'$notif_msg\', 0)");
                          }
                      }
                      echo "<div style=\'background:rgba(16,185,129,0.1);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;\'>? Announcement posted and village members have been notified!</div>";
                  }
              }';

$content = str_replace($old_handler, $new_handler, $content);

// 2. Replace the members table to add profile picture column and sorting
$old_table = '                  <div class="table-responsive">
                      <table class="print-table">
                          <thead>
                              <tr>
                                  <th>Name</th>
                                  <th>Phone</th>
                                  <th>Department</th>
                                  <th>Roles</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php
                              $v = $conn->real_escape_string($member[\'church_village\']);
                              $v_mems = $conn->query("SELECT first_name, last_name, phone, department, church_role FROM members WHERE is_approved = 1 AND church_village = \'$v\' ORDER BY first_name");
                              if ($v_mems && $v_mems->num_rows > 0) {
                                  while($m = $v_mems->fetch_assoc()) {
                                      echo "<tr>";
                                      echo "<td>" . htmlspecialchars($m[\'first_name\'] . \' \' . $m[\'last_name\']) . "</td>";
                                      echo "<td>" . htmlspecialchars($m[\'phone\'] ?? \'\') . "</td>";
                                      echo "<td>" . htmlspecialchars($m[\'department\'] ?? \'\') . "</td>";
                                      echo "<td>" . htmlspecialchars($m[\'church_role\'] ?? \'\') . "</td>";
                                      echo "</tr>";
                                  }
                              } else {
                                  echo "<tr><td colspan=\'4\'>No members found in this village.</td></tr>";
                              }
                              ?>
                          </tbody>
                      </table>
                  </div>';

$new_table = '                  <div class="table-responsive">
                      <table style="width:100%; border-collapse:collapse;">
                          <thead>
                              <tr style="background:#1e3a8a; color:#fff;">
                                  <th style="padding:10px 8px; text-align:left; font-size:11px; text-transform:uppercase;">#</th>
                                  <th style="padding:10px 8px; text-align:left; font-size:11px; text-transform:uppercase;">Photo</th>
                                  <th style="padding:10px 8px; text-align:left; font-size:11px; text-transform:uppercase;">Name</th>
                                  <th style="padding:10px 8px; text-align:left; font-size:11px; text-transform:uppercase;">Phone</th>
                                  <th style="padding:10px 8px; text-align:left; font-size:11px; text-transform:uppercase;">Department</th>
                                  <th style="padding:10px 8px; text-align:left; font-size:11px; text-transform:uppercase;">Role(s)</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php
                              $v = $conn->real_escape_string($member[\'church_village\']);
                              $v_mems = $conn->query("
                                  SELECT id, first_name, last_name, phone, department, church_role, profile_picture, is_village_leader,
                                  CASE
                                      WHEN is_village_leader = 1 THEN 1
                                      WHEN church_role IS NOT NULL AND church_role != \'\' AND LOWER(church_role) != \'member\' THEN 2
                                      ELSE 99
                                  END AS sort_rank
                                  FROM members
                                  WHERE is_approved = 1 AND church_village = \'$v\'
                                  ORDER BY sort_rank ASC, first_name ASC
                              ");
                              if ($v_mems && $v_mems->num_rows > 0) {
                                  $vi = 1;
                                  while ($vm = $v_mems->fetch_assoc()) {
                                      $bg = ($vm[\'is_village_leader\'] == 1) ? \'#eff6ff\' : (($vi % 2 === 0) ? \'#f9fafb\' : \'#fff\');
                                      $fw = ($vm[\'is_village_leader\'] == 1) ? \'700\' : \'400\';
                                      $pic_src = \'uploads/\' . basename($vm[\'profile_picture\'] ?? \'default_avatar.png\');
                                      $badge = \'\';
                                      if ($vm[\'is_village_leader\'] == 1) {
                                          $badge = \'<span style="background:#1e3a8a;color:#fff;font-size:9px;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;margin-left:4px;">Leader</span>\';
                                      } elseif (!empty($vm[\'church_role\']) && strtolower(trim($vm[\'church_role\'])) !== \'member\') {
                                          $badge = \'<span style="background:#10b981;color:#fff;font-size:9px;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;margin-left:4px;">Role</span>\';
                                      }
                                      echo "<tr style=\'background:$bg;\'>";
                                      echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-weight:$fw;\'>" . $vi . "</td>";
                                      echo "<td style=\'padding:8px; border:1px solid #e5e7eb;\'>
                                              <img src=\'" . htmlspecialchars($pic_src) . "\'
                                                   alt=\'\' onerror=\"this.src=\'uploads/default_avatar.png\'\"
                                                   style=\'width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #1e3a8a;\'></td>";
                                      echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-weight:$fw;\'>" . htmlspecialchars(ucfirst($vm[\'first_name\']) . \' \' . ucfirst($vm[\'last_name\'])) . $badge . "</td>";
                                      echo "<td style=\'padding:8px; border:1px solid #e5e7eb;\'>" . htmlspecialchars($vm[\'phone\'] ?? \'\') . "</td>";
                                      echo "<td style=\'padding:8px; border:1px solid #e5e7eb;\'>" . htmlspecialchars($vm[\'department\'] ?? \'\') . "</td>";
                                      echo "<td style=\'padding:8px; border:1px solid #e5e7eb;\'>" . htmlspecialchars($vm[\'church_role\'] ?? \'\') . "</td>";
                                      echo "</tr>";
                                      $vi++;
                                  }
                              } else {
                                  echo "<tr><td colspan=\'6\' style=\'text-align:center;padding:20px;color:#888;\'>No members found in this village.</td></tr>";
                              }
                              ?>
                          </tbody>
                      </table>
                  </div>';

$content = str_replace($old_table, $new_table, $content);

file_put_contents($file, $content);
echo "Success\n";
?>
