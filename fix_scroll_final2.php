<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

$old_js = <<<'JS'
                                            const currentRolesTable = document.getElementById('currentlyAssignedRolesTable');
                                            const scrollTarget = targetRow || currentRolesTable;
                                            
                                            if (scrollTarget) {
                                                const mainContent = document.querySelector('.main-content');
                                                if (mainContent) {
                                                    // Calculate scroll position inside the .main-content div
                                                    const rect = scrollTarget.getBoundingClientRect();
                                                    const mainRect = mainContent.getBoundingClientRect();
                                                    const targetY = mainContent.scrollTop + (rect.top - mainRect.top) - 80;
                                                    
                                                    // Scroll the main content container!
                                                    mainContent.scrollTo({top: targetY, behavior: 'smooth'});
                                                } else {
                                                    // Fallback
                                                    scrollTarget.scrollIntoView({behavior: 'smooth', block: 'center'});
                                                }
                                                
                                                if (targetRow) {
                                                    // Flash highlight
                                                    targetRow.style.transition = 'background-color 0.4s';
                                                    targetRow.style.backgroundColor = '#fef3c7';
                                                    targetRow.style.outline = '3px solid #f59e0b';
                                                    setTimeout(() => {
                                                        targetRow.style.backgroundColor = '';
                                                        targetRow.style.outline = '';
                                                    }, 5000);
                                                }
                                            }
JS;

$new_js = <<<'JS'
                                            const currentRolesTable = document.getElementById('currentlyAssignedRolesTable');
                                            const scrollTarget = targetRow || currentRolesTable;
                                            
                                            if (scrollTarget) {
                                                // Try multiple scroll methods to guarantee it works on all devices
                                                
                                                // 1. Native scrollIntoView (works 99% of the time without native alerts)
                                                try {
                                                    scrollTarget.scrollIntoView({behavior: 'smooth', block: 'center'});
                                                } catch(e) {
                                                    scrollTarget.scrollIntoView();
                                                }
                                                
                                                // 2. Fallback: absolute window scroll
                                                setTimeout(() => {
                                                    const rect = scrollTarget.getBoundingClientRect();
                                                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                                                    const targetY = rect.top + scrollTop - 100;
                                                    window.scrollTo({top: targetY, behavior: 'smooth'});
                                                }, 50);
                                                
                                                if (targetRow) {
                                                    // Flash highlight
                                                    targetRow.style.transition = 'background-color 0.4s';
                                                    targetRow.style.backgroundColor = '#fef3c7';
                                                    targetRow.style.outline = '3px solid #f59e0b';
                                                    setTimeout(() => {
                                                        targetRow.style.backgroundColor = '';
                                                        targetRow.style.outline = '';
                                                    }, 6000);
                                                }
                                            }
JS;

$content = str_replace(str_replace("\r", "", $old_js), str_replace("\r", "", $new_js), $content);
file_put_contents($file, $content);
echo "Restored scrollIntoView and window.scrollTo fallback\n";
?>
