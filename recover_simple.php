<?php
$transcript_path = "C:\\Users\\PC\\.gemini\\antigravity\\brain\\e28aca94-2bfe-4dfe-9e14-173a1d00e67a\\.system_generated\\logs\\transcript_full.jsonl";

$handle = fopen($transcript_path, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $step = json_decode($line, true);
        if (!$step) continue;
        
        if (isset($step['type']) && $step['type'] === 'PLANNER_RESPONSE' && isset($step['tool_calls'])) {
            foreach ($step['tool_calls'] as $call) {
                if (($call['name'] ?? '') === 'default_api:write_to_file') {
                    $args = $call['arguments'] ?? [];
                    $target = basename($args['TargetFile'] ?? '');
                    if ($target === 'pastor_action.php' || $target === 'pastor_dashboard.php') {
                        file_put_contents($target . ".backup", $args['CodeContent']);
                        echo "Found base for $target\n";
                    }
                }
            }
        }
    }
    fclose($handle);
}
?>
