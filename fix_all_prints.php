<?php
$files = [
    'print_all_leaders.php',
    'print_all_villages.php',
    'print_dept_financials.php',
    'print_financials.php',
    'print_pastor_reports.php',
    'print_volunteers.php',
];

foreach ($files as $file) {
    $c = file_get_contents($file);
    $changed = false;

    // 1. Page margin: from 0 to 8mm
    foreach ([
        '@page { margin: 0; }',
        '@page { margin:0; }',
        '@page{margin:0;}',
        '@page { margin: 0 }',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, '@page { margin: 8mm; }', $c);
            $changed = true;
        }
    }

    // 2. Body padding tighten in print
    foreach ([
        'body { padding: 15mm !important; }',
        'body{padding:15mm!important;}',
        'body { padding:15mm !important; }',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'body { padding: 0 !important; }', $c);
            $changed = true;
        }
    }

    // 3. Body margin in print
    foreach ([
        'body { margin: 12mm; padding: 0; max-width: 100%; }',
        'body{margin:12mm;padding:0;max-width:100%;}',
        'body { margin:12mm; padding:0; max-width:100%; }',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'body { margin: 0; padding: 0; max-width: 100%; }', $c);
            $changed = true;
        }
    }

    // 4. Table th padding
    foreach ([
        'th { padding: 9px 8px;',
        'th{padding:9px 8px;',
        'th { padding:9px 8px;',
        'th{padding: 9px 8px;',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'th { padding: 5px 6px;', $c);
            $changed = true;
        }
    }

    // 5. Table td padding
    foreach ([
        'td { padding: 8px;',
        'td{padding:8px;',
        'td { padding:8px;',
        'td{padding: 8px;',
        'td { border: 1px solid #ccc; padding: 8px;',
        'td { border:1px solid #ccc; padding:8px;',
        'td{border:1px solid #ccc;padding:8px;',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, str_replace('8px', '4px 6px', $old), $c);
            $changed = true;
        }
    }

    // 6. table font-size 12px -> 10px
    foreach ([
        'font-size: 12px',
        'font-size:12px',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'font-size: 10px', $c);
            $changed = true;
        }
    }

    // 7. th font-size 11px -> 9px
    foreach ([
        'font-size: 11px',
        'font-size:11px',
    ] as $old) {
        // Only in th context - replace globally
        $c = str_replace($old, 'font-size: 9px', $c);
        $changed = true;
    }

    // 8. Member photo thumbnails - standardize to 30px
    $photo_patterns = [
        'width: 36px; height: 36px;',
        'width:36px;height:36px;',
        'width: 26px; height: 26px;',
        'width:26px;height:26px;',
        'width: 40px; height: 40px;',
        'width:40px;height:40px;',
        'width:35px;height:35px;',
        'width: 35px; height: 35px;',
    ];
    foreach ($photo_patterns as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'width: 30px; height: 30px;', $c);
            $changed = true;
        }
    }

    // 9. Header margin-bottom
    foreach ([
        'margin-bottom: 24px;',
        'margin-bottom:24px;',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'margin-bottom: 8px;', $c);
            $changed = true;
        }
    }

    // 10. Header padding-bottom
    foreach ([
        'padding-bottom: 18px;',
        'padding-bottom:18px;',
    ] as $old) {
        if (strpos($c, $old) !== false) {
            $c = str_replace($old, 'padding-bottom: 6px;', $c);
            $changed = true;
        }
    }

    if ($changed) {
        file_put_contents($file, $c);
        echo "Fixed: $file\n";
    } else {
        echo "No changes: $file\n";
    }
}
?>
