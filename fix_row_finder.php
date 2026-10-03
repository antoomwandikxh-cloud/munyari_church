<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

$old_js = <<<'JS'
                                            const highlightRole = selected.value.toLowerCase();
                                            const rows = currentRolesTable.querySelectorAll('tbody tr');
                                            let targetRow = null;
                                            rows.forEach(row => {
                                                if (row.innerText.toLowerCase().includes(highlightRole)) {
                                                    targetRow = row;
                                                    row.style.transition = 'background-color 0.5s';
                                                    row.style.backgroundColor = '#fef3c7';
                                                    setTimeout(() => row.style.backgroundColor = '', 3000);
                                                }
                                            });
JS;

$new_js = <<<'JS'
                                            const roleParam = 'role=' + encodeURIComponent(selected.value);
                                            // The URL uses urlencode which might convert spaces to +, but encodeURIComponent converts to %20.
                                            // Let's use a simpler approach: check if href includes the role name directly or just search all links
                                            const expectedVal = selected.value.toLowerCase();
                                            let targetRow = null;
                                            const removeLinks = currentRolesTable.querySelectorAll('a[href*="action=remove_role"]');
                                            removeLinks.forEach(link => {
                                                // url in href is like: admin_dashboard.php?...&role=Youth+Chairperson
                                                const urlParams = new URL(link.href, window.location.origin).searchParams;
                                                const linkRole = urlParams.get('role');
                                                if (linkRole && linkRole.toLowerCase() === expectedVal) {
                                                    targetRow = link.closest('tr');
                                                    targetRow.style.transition = 'background-color 0.5s';
                                                    targetRow.style.backgroundColor = '#fef3c7';
                                                    setTimeout(() => targetRow.style.backgroundColor = '', 3000);
                                                }
                                            });
JS;

$content = str_replace(str_replace("\r", "", $old_js), str_replace("\r", "", $new_js), $content);
file_put_contents($file, $content);
echo "Updated JS row finder\n";
?>
