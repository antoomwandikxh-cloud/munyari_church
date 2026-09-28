<?php
$files = [
    'admin_dashboard.php' => '<div class="page-header">
                    <h1>Assign Roles</h1>
                    <p>Appoint approved members to church positions or create new roles.</p>
                </div>',
    'pastor_dashboard.php' => '<div class="page-header">
                    <h1>Assign Roles</h1>
                    <p>Appoint approved members to church positions or create new roles.</p>
                </div>'
];

$new_header = '<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h1>Assign Roles</h1>
                        <p>Appoint approved members to church positions or create new roles.</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button onclick="printAllLeaders(\'portrait\')" style="background:#1e3a8a;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Portrait)
                        </button>
                        <button onclick="printAllLeaders(\'landscape\')" style="background:#6366f1;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Landscape)
                        </button>
                    </div>
                </div>';

foreach ($files as $f => $old) {
    $content = file_get_contents($f);
    $content = str_replace("\r", "", $content);
    $old = str_replace("\r", "", $old);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new_header, $content);
        file_put_contents($f, $content);
        echo "Header updated in $f\n";
    } else {
        echo "Could not find header in $f\n";
    }
}
?>
