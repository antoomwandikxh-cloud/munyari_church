<?php
$transcript_path = "C:\\Users\\PC\\.gemini\\antigravity\\brain\\e28aca94-2bfe-4dfe-9e14-173a1d00e67a\\.system_generated\\logs\\transcript_full.jsonl";

$files = [];

$handle = fopen($transcript_path, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $step = json_decode($line, true);
        if (!$step) continue;
        
        if (isset($step['type']) && $step['type'] === 'PLANNER_RESPONSE' && isset($step['tool_calls'])) {
            foreach ($step['tool_calls'] as $call) {
                $tool_name = $call['name'] ?? '';
                $args = $call['arguments'] ?? [];
                
                if ($tool_name === 'default_api:write_to_file') {
                    $target = basename($args['TargetFile'] ?? '');
                    if ($target === 'pastor_action.php' || $target === 'pastor_dashboard.php') {
                        $files[$target] = $args['CodeContent'] ?? '';
                    }
                } elseif ($tool_name === 'default_api:replace_file_content') {
                    $target = basename($args['TargetFile'] ?? '');
                    if (isset($files[$target])) {
                        $start = (int)($args['StartLine'] ?? 1);
                        $end = (int)($args['EndLine'] ?? 1);
                        $replacement = $args['ReplacementContent'] ?? '';
                        $lines = explode("\n", $files[$target]);
                        array_splice($lines, $start - 1, $end - $start + 1, explode("\n", $replacement));
                        $files[$target] = implode("\n", $lines);
                    }
                } elseif ($tool_name === 'default_api:multi_replace_file_content') {
                    $target = basename($args['TargetFile'] ?? '');
                    if (isset($files[$target])) {
                        $chunks = $args['ReplacementChunks'] ?? [];
                        usort($chunks, function($a, $b) {
                            return ($b['StartLine'] ?? 0) - ($a['StartLine'] ?? 0);
                        });
                        $lines = explode("\n", $files[$target]);
                        foreach ($chunks as $chunk) {
                            $start = (int)($chunk['StartLine'] ?? 1);
                            $end = (int)($chunk['EndLine'] ?? 1);
                            $replacement = $chunk['ReplacementContent'] ?? '';
                            array_splice($lines, $start - 1, $end - $start + 1, explode("\n", $replacement));
                        }
                        $files[$target] = implode("\n", $lines);
                    }
                }
            }
        }
    }
    fclose($handle);
}

foreach ($files as $target => $content) {
    file_put_contents($target, $content);
    echo "Recovered $target\n";
}
?>
