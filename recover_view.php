<?php
$transcript_path = "C:\\Users\\PC\\.gemini\\antigravity\\brain\\e28aca94-2bfe-4dfe-9e14-173a1d00e67a\\.system_generated\\logs\\transcript_full.jsonl";

$files = [
    'pastor_action.php' => [],
    'pastor_dashboard.php' => []
];

$handle = fopen($transcript_path, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $step = json_decode($line, true);
        if (!$step) continue;
        
        if (isset($step['type']) && $step['type'] === 'PLANNER_RESPONSE' && isset($step['content'])) {
            // Check for tool responses in the transcript
            // Actually, tool responses come as separate events or inside the same step?
            // Usually they are in tool_calls or something. 
            // In the transcript_full, the tool response is often in a step with source = SYSTEM and type = TOOL_RESPONSE or something similar.
        }
        
        if (isset($step['source']) && $step['source'] === 'SYSTEM') {
            if (isset($step['tool_calls']) && is_array($step['tool_calls'])) {
                foreach ($step['tool_calls'] as $call) {
                    if (isset($call['name']) && $call['name'] === 'default_api:view_file') {
                        // The response might be in a different field or step. Let's just match the output text in the entire JSON string.
                    }
                }
            }
        }
    }
    fclose($handle);
}

// Easier way: Just preg_match_all on the entire file. It's huge, so read it line by line.
$handle = fopen($transcript_path, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, 'File Path: `file:///C:/xampp/htdocs/munyari_church/') !== false) {
            $step = json_decode($line, true);
            if ($step && isset($step['content'])) {
                // The output of view_file might be in $step['content'] or $step['tool_calls'][0]['output']
                if (preg_match('/File Path: `file:\/\/\/C:\/xampp\/htdocs\/munyari_church\/(pastor_action\.php|pastor_dashboard\.php)`\n.*?\nShowing lines (\d+) to (\d+)\n.*?\n((?:\d+: .*\n?)+)/s', $step['content'], $matches)) {
                    $filename = $matches[1];
                    $start = (int)$matches[2];
                    $end = (int)$matches[3];
                    $lines_text = $matches[4];
                    
                    $parsed_lines = explode("\n", trim($lines_text));
                    foreach ($parsed_lines as $l) {
                        if (preg_match('/^(\d+): (.*)$/', $l, $m)) {
                            $files[$filename][(int)$m[1]] = $m[2];
                        }
                    }
                }
            }
            if (isset($step['tool_calls'])) {
                foreach ($step['tool_calls'] as $call) {
                     if (isset($call['output']) && preg_match('/File Path: `file:\/\/\/C:\/xampp\/htdocs\/munyari_church\/(pastor_action\.php|pastor_dashboard\.php)`\n.*?\nShowing lines (\d+) to (\d+)\n.*?\n((?:\d+: .*\n?)+)/s', $call['output'], $matches)) {
                        $filename = $matches[1];
                        $start = (int)$matches[2];
                        $end = (int)$matches[3];
                        $lines_text = $matches[4];
                        
                        $parsed_lines = explode("\n", trim($lines_text));
                        foreach ($parsed_lines as $l) {
                            if (preg_match('/^(\d+): (.*)$/', $l, $m)) {
                                $files[$filename][(int)$m[1]] = $m[2];
                            }
                        }
                    }
                }
            }
        }
    }
    fclose($handle);
}

foreach ($files as $name => $lines) {
    if (!empty($lines)) {
        ksort($lines);
        $content = implode("\n", $lines);
        file_put_contents($name . ".recovered", $content);
        echo "Recovered pieces of $name\n";
    }
}
?>
