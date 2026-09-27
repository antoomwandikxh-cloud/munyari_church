<?php
$file = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$lines = file($file);

// Step 1: Remove lines 2527-2533 (0-indexed: 2526-2532) - the village link near desired_roles
// Lines are (1-indexed): 2527 to 2533
array_splice($lines, 2526, 7);  // remove 7 lines starting at index 2526
echo "After step 1: " . count($lines) . " lines\n";

// Now line 2858-2859 shifted by -7, so they're now at 2851-2852
// Step 2: Insert village panel block AFTER line 2851 (the endif for My Worship) which is now at index 2850
// Find the exact line
$insert_after = -1;
for ($i = 2840; $i < 2880; $i++) {
    if (isset($lines[$i]) && strpos($lines[$i], 'endif; // end has_church_village gate') !== false) {
        $insert_after = $i;
        break;
    }
}
echo "Insert after line (0-indexed): $insert_after\n";

if ($insert_after > 0) {
    $village_block = <<<'VBLOCK'

                <?php if ($member['is_village_leader'] == 1): ?>
                <!-- Church Village Panel -->
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #10b981; margin-top: 10px;">Church Village Panel</div>
                <a href="?tab=manage_church_village" class="sidebar-link <?= $tab == 'manage_church_village' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Church Village
                    <?php if (!empty($tab_badges['manage_church_village'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['manage_church_village'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

VBLOCK;
    array_splice($lines, $insert_after + 1, 0, [$village_block]);
    echo "Village panel inserted after index $insert_after\n";
}

file_put_contents($file, implode('', $lines));
echo "Done. Total lines: " . count($lines) . "\n";
?>
