<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

// Find the "Registered Worshippers" section header and inject search + print buttons
$old_header = '<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h2 style="margin:0;">Registered Worshippers</h2>
                            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">All members who registered as Worshippers or have the Worshipper role.</p>
                        </div>
                    </div>';

$new_header = '<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <h2 style="margin:0;">Registered Worshippers</h2>
                                <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">All members who registered as Worshippers or have the Worshipper role.</p>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="worshipperSearch" placeholder="Search worshippers..." style="padding:8px 12px; border:1px solid var(--border-color); border-radius:8px; width:220px; font-size:0.85rem;" onkeyup="filterWorshippersTable()">
                            <a href="print_worshippers.php?mode=landscape" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:white;padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(139,92,246,0.3);">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print Landscape
                            </a>
                            <a href="print_worshippers.php?mode=portrait" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#6d28d9,#4c1d95);color:white;padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(109,40,217,0.3);">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print Portrait
                            </a>
                        </div>
                    </div>';

// Also add id to the table for search targeting and JS function
$old_table_open = '<div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Preference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>';

$new_table_open = '<div class="table-responsive" id="worshippersTable">
                        <table>
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Preference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>';

$js_func = <<<JS

function filterWorshippersTable() {
    let input = document.getElementById("worshipperSearch");
    if (!input) return;
    let filter = input.value.toLowerCase();
    let container = document.getElementById("worshippersTable");
    if (!container) return;
    let trs = container.querySelectorAll("tbody tr");
    for (let i = 0; i < trs.length; i++) {
        let text = trs[i].textContent || trs[i].innerText;
        trs[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? "" : "none";
    }
}
JS;

foreach ($files as $file) {
    $c = file_get_contents($file);
    $c = str_replace($old_header, $new_header, $c);
    $c = str_replace($old_table_open, $new_table_open, $c);
    
    // Inject JS function before last </script>
    if (strpos($c, 'function filterWorshippersTable') === false) {
        $pos = strrpos($c, '</script>');
        if ($pos !== false) {
            $c = substr_replace($c, $js_func . "\n", $pos, 0);
        }
    }
    
    file_put_contents($file, $c);
    echo "Updated $file\n";
}
?>
