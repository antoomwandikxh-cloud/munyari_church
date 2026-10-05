<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
$css_oneline = "<style>.role-pills-wrap{display:flex;flex-wrap:wrap;gap:4px;align-items:center;}.role-pill{display:inline-flex;align-items:center;font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap;line-height:1.5;letter-spacing:0.2px;border:1px solid transparent;}.rp-leader{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}.rp-chair{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe;}.rp-vice{background:#fdf4ff;color:#9333ea;border-color:#f0abfc;}.rp-secretary{background:#ecfdf5;color:#059669;border-color:#6ee7b7;}.rp-treasurer{background:#fff7ed;color:#c2410c;border-color:#fdba74;}.rp-elder{background:#f0fdf4;color:#15803d;border-color:#bbf7d0;}.rp-worship{background:#fdf2f8;color:#be185d;border-color:#fbcfe8;}.rp-usher{background:#fefce8;color:#a16207;border-color:#fde047;}.rp-pastor{background:#fef3c7;color:#92400e;border-color:#f59e0b;}.rp-member{background:#f1f5f9;color:#64748b;border-color:#cbd5e1;}.rp-other{background:#f8fafc;color:#475569;border-color:#e2e8f0;}</style>";

foreach ($files as $file) {
    $c = file_get_contents($file);
    if (strpos($c, $css_oneline . "</head><body><main") === false) {
        $c = str_replace("}</head><body><main", "}$css_oneline</head><body><main", $c);
        file_put_contents($file, $c);
        echo "Fixed Sunday school popup in $file\n";
    }
}
?>
