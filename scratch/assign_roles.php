            <?php elseif ($tab == 'assign_roles'): ?>
                <?php
                // --- INITIALIZATION & HIERARCHY ---
                $required_roles = [
                    'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder',
                    'Head Usher', 'Usher', 'Building Chairman', 'Vice Building Chairman', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer',
                    'Youth Chairman', 'Vice Youth Chairman', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth',
                    'Women Chairlady', 'Vice Women Chairlady', 'Women Secretary', 'Vice Women Secretary', 'Women Treasurer',
                    'Elder Chairman', 'Vice Elder Chairman', 'Elder Secretary', 'Vice Elder Secretary', 'Elder Treasurer',
                    'Sunday School Patron', 'Vice Sunday School Patron', 'Sunday School Secretary', 'Vice Sunday School Secretary', 'Sunday School Treasurer'
                ];
                foreach ($required_roles as $required_role) {
                    $safe_required_role = $conn->real_escape_string($required_role);
                    $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$safe_required_role')");
                }
                
                $church_roles = $conn->query("SELECT * FROM church_roles WHERE role_name NOT IN (SELECT church_role FROM members WHERE church_role IS NOT NULL AND church_role != '') ORDER BY role_name ASC");
                $all_roles_list = $conn->query("SELECT * FROM church_roles ORDER BY role_name ASC");

                // Define hierarchy structure
                $common_subsidiary_roles = ['Organizing Secretary (Subsidiary)', 'Discipline Master (Subsidiary)', 'Prayer Coordinator (Subsidiary)', 'Choir Leader (Subsidiary)'];
                $youth_sunday_subsidiary_roles = array_merge($common_subsidiary_roles, ['Graduands Secretary (Subsidiary)', 'Sports Secretary (Subsidiary)']);
                $hierarchy = [
                    'General Church' => [
                        'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder'
                    ],
                    'Other Ministry Leaders' => [
                        'Head Usher', 'Usher', 'Building Chairman', 'Vice Building Chairman', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer'
                    ],
                    'Youth Ministry' => array_merge([
                        'Youth Chairman', 'Vice Youth Chairman', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth'
                    ], $youth_sunday_subsidiary_roles),
                    'Womens Ministry' => array_merge([
                        'Women Chairlady', 'Vice Women Chairlady', 'Women Secretary', 'Vice Women Secretary', 'Women Treasurer'
                    ], $common_subsidiary_roles),
                    'Elders' => array_merge([
                        'Elder Chairman', 'Vice Elder Chairman', 'Elder Secretary', 'Vice Elder Secretary', 'Elder Treasurer'
                    ], $common_subsidiary_roles),
                    'Sunday School' => array_merge([
                        'Sunday School Patron', 'Vice Sunday School Patron', 'Sunday School Secretary', 'Vice Sunday School Secretary', 'Sunday School Treasurer'
                    ], $youth_sunday_subsidiary_roles)
                ];
                $hierarchy_department_names = [
                    'Youth Ministry' => 'Youths',
                    'Womens Ministry' => 'Womens Ministry',
                    'Elders' => 'Elders',
                    'Sunday School' => 'Sunday School',
                ];

                $all_hierarchy_roles = [];
                foreach ($hierarchy as $roles) {
                    foreach ($roles as $role_name) {
                        $all_hierarchy_roles[normalize_role_name($role_name)] = $role_name;
                    }
                }
                foreach ($all_hierarchy_roles as $role_name) {
                    $safe_role_name = $conn->real_escape_string($role_name);
                    $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$safe_role_name')");
                }

                $assigned_members_for_availability = [];
                $assigned_global_roles = [];
                $head_usher_already_assigned = false;
                $assigned_lookup_result = $conn->query("SELECT church_role, department FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                if ($assigned_lookup_result) {
                    while ($assigned_lookup_row = $assigned_lookup_result->fetch_assoc()) {
                        $assigned_members_for_availability[] = $assigned_lookup_row;
                        // Split combined roles (e.g. "Head Usher, Vice Youth Secretary") and track each one
                        $individual_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $assigned_lookup_row['church_role'] ?? ''))));
                        foreach ($individual_roles as $ind_role) {
                            $norm = normalize_role_name($ind_role);
                            $assigned_global_roles[$norm] = true;
                            if ($norm === 'head usher') {
                                $head_usher_already_assigned = true;
                            }
                        }
                    }
                }

                $role_is_available = function($role_name, $context_department = '') use ($assigned_members_for_availability, $assigned_global_roles, $head_usher_already_assigned) {
                    $normalized_role = normalize_role_name($role_name);

                    // Head Usher: strictly one — hide from dropdown if already assigned
                    if ($normalized_role === 'head usher') {
                        return !$head_usher_already_assigned;
                    }

                    // Usher: always available (unlimited appointments)
                    if ($normalized_role === 'usher') {
                        return true;
                    }

                    if (is_subsidiary_department_role($role_name) && !empty($context_department)) {
                        foreach ($assigned_members_for_availability as $assigned_row) {
                            // Check each individual role in potentially combined roles
                            $row_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $assigned_row['church_role'] ?? ''))));
                            foreach ($row_roles as $row_role) {
                                if (
                                    normalize_role_name($row_role) === $normalized_role &&
                                    department_matches($assigned_row['department'] ?? '', $context_department)
                                ) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    }

                    return empty($assigned_global_roles[$normalized_role]);
                };
                ?>
                <div class="page-header">
                    <h1>Assign Roles</h1>
                    <p>Appoint approved members to church positions or create new roles.</p>
                </div>
                
                <!-- Pending Chairman Appointments -->
                <?php
                $pending_roles = $conn->query("SELECT id, first_name, last_name, department, pending_role FROM members WHERE pending_role IS NOT NULL AND pending_role != ''");
                if ($pending_roles && $pending_roles->num_rows > 0):
                ?>
                <div class="content-card" style="margin-bottom: 30px; border-left: 4px solid var(--primary);">
                    <h2 style="color: var(--primary);">Pending Subsidiary Appointments</h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">These members have been nominated by their department chairmen for subsidiary roles.</p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Department</th>
                                    <th>Proposed Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($pr = $pending_roles->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($pr['first_name'] . ' ' . $pr['last_name']) ?></td>
                                        <td><span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><?= htmlspecialchars($pr['department']) ?></span></td>
                                        <td style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($pr['pending_role']) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 10px;">
                                                <a href="pastor_action.php?action=approve_appointment&id=<?= $pr['id'] ?>" class="btn-action btn-approve" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Approve</a>
                                                <a href="pastor_action.php?action=reject_appointment&id=<?= $pr['id'] ?>" class="btn-action btn-reject" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;" onclick="return confirm('Reject this appointment?');">Reject</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Assign Role Form -->
                    <div class="content-card" style="flex: 1; min-width: 300px; margin-bottom: 20px;">
                        <h2>Assign to Member</h2>

                        <!-- Popup toast for taken role -->
                        <div id="headUsherToast" style="display:none; position:fixed; top:30px; left:50%; transform:translateX(-50%); z-index:9999; background:#1e293b; color:#fff; border-radius:14px; padding:18px 28px; box-shadow:0 8px 32px rgba(0,0,0,0.25); max-width:400px; width:90%; text-align:center; animation: fadeInDown 0.3s ease;">
                            <div style="font-size:2rem; margin-bottom:8px;">🔒</div>
                            <strong id="toastRoleTitle" style="font-size:1rem; display:block; margin-bottom:6px;">Role Already Assigned</strong>
                            <p id="toastRoleBody" style="font-size:0.88rem; color:#94a3b8; margin:0 0 14px;">This role is already taken. Remove the current holder first before reassigning.</p>
                            <button onclick="document.getElementById('headUsherToast').style.display='none'; document.getElementById('assignRoleSelect').value='';" style="background:var(--primary,#2563eb); color:#fff; border:none; border-radius:8px; padding:8px 22px; font-size:0.9rem; cursor:pointer; font-weight:600;">OK, Got It</button>
                        </div>
                        <style>@keyframes fadeInDown{from{opacity:0;transform:translateX(-50%) translateY(-18px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}</style>

                        <form method="POST" action="pastor_action.php?action=assign_role">
                            <div class="form-group">
                                <label>First Select Role</label>
                                <select name="role" id="assignRoleSelect" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php 
                                    foreach ($hierarchy as $dept => $roles) {
                                        $context_department = $hierarchy_department_names[$dept] ?? '';
                                        $options_html = '';
                                        foreach ($roles as $expected_role) {
                                            $is_taken = !$role_is_available($expected_role, $context_department);
                                            $val   = htmlspecialchars($expected_role);
                                            $allowed_json = htmlspecialchars(json_encode(role_assignment_departments($expected_role, $context_department)));
                                            $asgn_dept = htmlspecialchars($context_department);
                                            $label = htmlspecialchars($expected_role);
                                            if ($is_taken) {
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed_json' data-assignment-department=\"$asgn_dept\" data-taken=\"1\" style=\"color:#9ca3af;\">$label (Taken)</option>";
                                            } else {
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed_json' data-assignment-department=\"$asgn_dept\">$label</option>";
                                            }
                                        }
                                        if ($options_html !== '') {
                                            echo "<optgroup label=\"" . htmlspecialchars($dept) . "\">$options_html</optgroup>";
                                        }
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="assignment_department" id="assignRoleDepartment" value="">
                                <small id="assignRoleHint" style="display:block; margin-top:6px; color: var(--text-muted);">Choose a role first to open the correct members.</small>
                            </div>
                            <div class="form-group">
                                <label>Then Select Member</label>
                                <select name="member_id" id="assignMemberSelect" class="form-control" required disabled>
                                    <option value="">-- Select a role first --</option>
                                    <?php 
                                    $approved_members->data_seek(0);
                                    while($m = $approved_members->fetch_assoc()): ?>
                                        <option value="<?= $m['id'] ?>" data-department="<?= htmlspecialchars($m['department'] ?? '') ?>">
                                            <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> - <?= htmlspecialchars($m['department'] ?? 'No Department') ?> (Current: <?= htmlspecialchars(role_display_label($m['church_role'] ?? 'Member', $m['department'] ?? null)) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit">Assign Role</button>
                        </form>
                        <script>
                            (function() {
                                const roleSelect = document.getElementById('assignRoleSelect');
                                const memberSelect = document.getElementById('assignMemberSelect');
                                const departmentInput = document.getElementById('assignRoleDepartment');
                                const hint = document.getElementById('assignRoleHint');
                                if (!roleSelect || !memberSelect) return;
                                const originalOptions = Array.from(memberSelect.querySelectorAll('option[data-department]')).map(o => o.cloneNode(true));
                                
                                roleSelect.addEventListener('change', function() {
                                    const sel = this.options[this.selectedIndex];
                                    if (!sel || !sel.value) {
                                        memberSelect.disabled = true;
                                        memberSelect.innerHTML = '<option value="">-- Select a role first --</option>';
                                        hint.innerText = "Choose a role first to open the correct members.";
                                        hint.style.color = "var(--text-muted)";
                                        departmentInput.value = "";
                                        return;
                                    }
                                    
                                    if (sel && sel.dataset.taken === '1') {
                                        this.value = '';
                                        const tBody = document.getElementById('toastRoleBody');
                                        const rName = sel.text.replace(' (Taken)', '');
                                        tBody.innerText = `The role of ${rName} is already taken. Remove the current holder first before reassigning.`;
                                        document.getElementById('headUsherToast').style.display = 'block';
                                        
                                        memberSelect.disabled = true;
                                        memberSelect.innerHTML = '<option value="">-- Select a role first --</option>';
                                        departmentInput.value = "";
                                        return;
                                    }

                                    memberSelect.disabled = false;
                                    let allowedDepts = [];
                                    try {
                                        if (sel.dataset.allowed) {
                                            allowedDepts = JSON.parse(sel.dataset.allowed) || [];
                                        }
                                    } catch(e) {}
                                    
                                    departmentInput.value = sel.dataset.assignmentDepartment || '';

                                    memberSelect.innerHTML = '<option value="">-- Select Member --</option>';
                                    let count = 0;
                                    originalOptions.forEach(opt => {
                                        if (!opt.value) return; 
                                        const optDept = opt.dataset.department || '';
                                        let isAllowed = false;
                                        if (allowedDepts.length === 0) {
                                            isAllowed = true;
                                        } else {
                                            let allowedLower = allowedDepts.map(d => d.toLowerCase());
                                            let optLower = optDept.toLowerCase();
                                            if (allowedLower.includes(optLower)) isAllowed = true;
                                            if (allowedLower.includes('youths') && optLower === 'youth ministry') isAllowed = true;
                                            if (allowedLower.includes('womens ministry') && optLower === 'women ministry') isAllowed = true;
                                            if (allowedLower.includes('elders') && optLower === 'elders') isAllowed = true;
                                        }

                                        if (isAllowed) {
                                            memberSelect.appendChild(opt.cloneNode(true));
                                            count++;
                                        }
                                    });

                                    if (count === 0) {
                                        hint.innerText = `No eligible members found. Role requires: ${allowedDepts.length ? allowedDepts.join(', ') : 'Any'}`;
                                        hint.style.color = "var(--danger)";
                                    } else {
                                        hint.innerText = `Found ${count} eligible member(s) for this role.`;
                                        hint.style.color = "#10b981";
                                    }
                                });
                            })();
                        </script>
                    </div>

                    <!-- Create Custom Role Form & Manage Roles -->
                    <div class="content-card" style="flex: 1; min-width: 300px;">
                        <h2>Manage Roles</h2>
                        <form method="POST" action="pastor_action.php?action=create_role" style="margin-bottom: 20px;">
                            <div class="form-group">
                                <label>New Role Name</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="new_role" class="form-control" placeholder="e.g. Vice Youth Chairman" required style="margin-bottom:0;">
                                    <button type="submit" class="btn" style="background:var(--secondary); color:white; border:none; border-radius:var(--radius-md); font-weight:600; cursor:pointer; padding: 0 20px;">Save</button>
                                </div>
                            </div>
                        </form>
                        
                        <div style="border-top: 1px solid var(--border-color); padding-top: 15px;">
                            <h3 style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 15px;">All Roles Overview</h3>
                            <?php 
                            // Organize all roles into hierarchy
                            $all_categorized = [];
                            $all_uncategorized = [];
                            $all_roles_list->data_seek(0);
                            while($r = $all_roles_list->fetch_assoc()) {
                                $r_name = trim($r['role_name']);
                                $placed = false;
                                foreach($hierarchy as $dept => $roles) {
                                    foreach($roles as $expected_role) {
                                        if (strtolower($r_name) === strtolower($expected_role)) {
                                            $all_categorized[$dept][$expected_role] = $r;
                                            $placed = true;
                                            break 2;
                                        }
                                    }
                                }
                                if (!$placed) {
                                    $all_uncategorized[] = $r;
                                }
                            }

                            foreach ($hierarchy as $dept => $roles) {
                                if (!empty($all_categorized[$dept])) {
                                    echo "<div style='margin-bottom: 15px;'>";
                                    echo "<div style='font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;'>$dept</div>";
                                    echo "<div style='display: flex; flex-wrap: wrap; gap: 8px;'>";
                                    foreach ($roles as $expected_role) {
                                        if (isset($all_categorized[$dept][$expected_role])) {
                                            $r = $all_categorized[$dept][$expected_role];
                                            echo "<div style='background: var(--bg-main); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-main);'>";
                                            echo htmlspecialchars($r['role_name']);
                                            echo "<a href='pastor_action.php?action=delete_role&role_id=" . $r['id'] . "' onclick=\"return confirm('Are you sure you want to remove this role from the system?');\" style='color: var(--danger); text-decoration: none; font-weight: bold; font-size: 1rem; line-height: 1;'>&times;</a>";
                                            echo "</div>";
                                        }
                                    }
                                    echo "</div></div>";
                                }
                            }
                            if (!empty($all_uncategorized)) {
                                echo "<div style='margin-bottom: 15px;'>";
                                echo "<div style='font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;'>Other Roles</div>";
                                echo "<div style='display: flex; flex-wrap: wrap; gap: 8px;'>";
                                foreach ($all_uncategorized as $r) {
                                    echo "<div style='background: var(--bg-main); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-main);'>";
                                    echo htmlspecialchars($r['role_name']);
                                    echo "<a href='pastor_action.php?action=delete_role&role_id=" . $r['id'] . "' onclick=\"return confirm('Are you sure you want to remove this role from the system?');\" style='color: var(--danger); text-decoration: none; font-weight: bold; font-size: 1rem; line-height: 1;'>&times;</a>";
                                    echo "</div>";
                                }
                                echo "</div></div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- NEW: Leaders Needed -->
                <div class="content-card" style="margin-bottom: 30px;">
                    <h2 style="margin-bottom: 20px;">Required Leaders Table</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px;">
                        <?php 
                        $assigned_role_rows = [];
                        $assigned_lookup = $conn->query("SELECT first_name, last_name, department, church_role FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                        if ($assigned_lookup) {
                            while ($assigned = $assigned_lookup->fetch_assoc()) {
                                $assigned_role_rows[] = $assigned;
                            }
                        }
                        ?>
                        <?php foreach ($hierarchy as $dept => $roles): ?>
                            <div style="margin-top: 18px;">
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                    <h3 style="margin:0; color: var(--primary); font-size:1rem;"><?= htmlspecialchars($dept) ?></h3>
                                    <span class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-color);"><?= count($roles) ?> roles</span>
                                </div>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Leader Role</th>
                                                <th>Allowed Members</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($roles as $expected_role): ?>
                                                <?php
                                                $context_department = $hierarchy_department_names[$dept] ?? '';
                                                $assigned_names = [];
                                                foreach ($assigned_role_rows as $assigned_role_row) {
                                                    $member_roles_arr = array_filter(array_map('trim', explode(',', str_replace('&', ',', $assigned_role_row['church_role'] ?? ''))));
                                                    $has_expected_role = false;
                                                    foreach ($member_roles_arr as $m_role) {
                                                        if (normalize_role_name($m_role) === normalize_role_name($expected_role)) {
                                                            $has_expected_role = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$has_expected_role) {
                                                        continue;
                                                    }
                                                    if (
                                                        is_subsidiary_department_role($expected_role) &&
                                                        !empty($context_department) &&
                                                        !department_matches($assigned_role_row['department'] ?? '', $context_department)
                                                    ) {
                                                        continue;
                                                    }
                                                    $assigned_names[] = trim($assigned_role_row['first_name'] . ' ' . $assigned_role_row['last_name']);
                                                }
                                                $allowed_departments = role_assignment_departments($expected_role);
                                                $allowed_label = empty($allowed_departments) ? 'Entire Church' : implode(', ', $allowed_departments);
                                                if (is_subsidiary_department_role($expected_role) && !empty($context_department)) {
                                                    $allowed_label = $context_department;
                                                }
                                                ?>
                                                <tr>
                                                    <td style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars(role_display_label($expected_role)) ?></td>
                                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($allowed_label) ?></td>
                                                    <td>
                                                        <?php if (!empty($assigned_names)): ?>
                                                            <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981;">Assigned: <?= htmlspecialchars(implode(', ', $assigned_names)) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: rgba(245,158,11,0.14); color: var(--warning);">Open</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="content-card">
                    <h2 style="margin-bottom: 20px;">Currently Assigned Roles</h2>
                    <?php 
                    $assigned_roles_q = $conn->query("SELECT id, first_name, last_name, department, church_role FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                    
                    // Organize fetched members into the hierarchy structure
                    $categorized_members = [];
                    $uncategorized = [];
                    
                    while($ar = $assigned_roles_q->fetch_assoc()) {
                        $member_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $ar['church_role'] ?? ''))));
                        foreach ($member_roles as $role_clean) {
                            $placed = false;
                            foreach($hierarchy as $dept => $roles) {
                                $expected_department = $hierarchy_department_names[$dept] ?? '';
                                $member_department = trim($ar['department'] ?? '');
                                $is_youth_advisor_for_youth = $dept === 'Youth Ministry' && is_youth_advisor_role($role_clean);
                                if ($expected_department && !$is_youth_advisor_for_youth) {
                                    $member_aliases = array_map('strtolower', department_aliases($member_department));
                                    $expected_aliases = array_map('strtolower', department_aliases($expected_department));
                                    if (empty(array_intersect($member_aliases, $expected_aliases))) {
                                        continue;
                                    }
                                }
                                foreach($roles as $expected_role) {
                                    if (strtolower($role_clean) === strtolower($expected_role)) {
                                        $temp_ar = $ar;
                                        $temp_ar['displayed_role'] = $expected_role;
                                        $categorized_members[$dept][$expected_role][] = $temp_ar;
                                        $placed = true;
                                        break 2;
                                    }
                                }
                            }
                            if (!$placed) {
                                $temp_ar = $ar;
                                $temp_ar['displayed_role'] = $role_clean;
                                $uncategorized[] = $temp_ar;
                            }
                        }
                    }

                    $has_any_roles = false;
                    foreach ($hierarchy as $dept => $roles) {
                        if (!empty($categorized_members[$dept])) {
                            $has_any_roles = true;
                            echo "<div style='margin-bottom: 30px;'>";
                            echo "<h3 style='font-size: 1.1rem; color: var(--primary); margin-bottom: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 5px;'>$dept</h3>";
                            echo "<div class='table-responsive'><table>";
                            echo "<thead><tr><th style='width: 40%;'>Assigned Role</th><th>Member</th><th>Action</th></tr></thead><tbody>";
                            
                            foreach ($roles as $expected_role) {
                                if (!empty($categorized_members[$dept][$expected_role])) {
                                    foreach ($categorized_members[$dept][$expected_role] as $member_data) {
                                        $role_label = role_display_label($expected_role, $member_data['department'] ?? null);
                                        echo "<tr>";
                                        echo "<td><span class='badge' style='background: var(--primary); color: white; font-weight: bold;'>" . htmlspecialchars($role_label) . "</span></td>";
                                        echo "<td style='font-weight: 500;'>" . htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']) . "</td>";
                                        echo "<td><a href='pastor_action.php?action=remove_role&id=" . $member_data['id'] . "&role=" . urlencode($expected_role) . "' onclick=\"return confirm('Remove this role from " . htmlspecialchars($member_data['first_name']) . "?');\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>";
                                        echo "</tr>";
                                    }
                                }
                            }
                            echo "</tbody></table></div></div>";
                        }
                    }

                    // Render roles that are not part of the required leadership hierarchy
                    if (!empty($uncategorized)) {
                        $has_any_roles = true;
                        echo "<div style='margin-bottom: 30px;'>";
                        echo "<h3 style='font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 5px;'>Other Roles</h3>";
                        echo "<div class='table-responsive'><table>";
                        echo "<thead><tr><th style='width: 40%;'>Assigned Role</th><th>Member</th><th>Action</th></tr></thead><tbody>";
                        foreach ($uncategorized as $member_data) {
                            $disp_role = $member_data['displayed_role'] ?? $member_data['church_role'];
                            $role_label = role_display_label($disp_role, $member_data['department'] ?? null);
                            echo "<tr>";
                            echo "<td><span class='badge' style='background: var(--text-muted); color: white; font-weight: bold;'>" . htmlspecialchars($role_label) . "</span></td>";
                            echo "<td style='font-weight: 500;'>" . htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']) . "</td>";
                            echo "<td><a href='pastor_action.php?action=remove_role&id=" . $member_data['id'] . "&role=" . urlencode($disp_role) . "' onclick=\"return confirm('Remove this role from " . htmlspecialchars($member_data['first_name']) . "?');\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table></div></div>";
                    }

                    if (!$has_any_roles) {
                        echo "<p style='color: var(--text-muted); text-align: center; padding: 30px 0;'>No roles have been assigned yet.</p>";
                    }
                    ?>
                </div>
