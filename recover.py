import json
import os
import re

transcript_path = r"C:\Users\PC\.gemini\antigravity\brain\e28aca94-2bfe-4dfe-9e14-173a1d00e67a\.system_generated\logs\transcript_full.jsonl"

files = {}

with open(transcript_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            step = json.loads(line)
        except:
            continue
        
        if step.get('type') == 'PLANNER_RESPONSE' and 'tool_calls' in step:
            for call in step['tool_calls']:
                tool_name = call.get('name')
                args = call.get('arguments', {})
                
                if tool_name == 'default_api:write_to_file':
                    target = os.path.basename(args.get('TargetFile', ''))
                    if target in ['pastor_action.php', 'pastor_dashboard.php']:
                        files[target] = args.get('CodeContent', '')
                
                elif tool_name == 'default_api:replace_file_content':
                    target = os.path.basename(args.get('TargetFile', ''))
                    if target in files:
                        start = args.get('StartLine', 1)
                        end = args.get('EndLine', 1)
                        replacement = args.get('ReplacementContent', '')
                        lines = files[target].split('\n')
                        # 1-indexed to 0-indexed
                        lines = lines[:start-1] + replacement.split('\n') + lines[end:]
                        files[target] = '\n'.join(lines)
                
                elif tool_name == 'default_api:multi_replace_file_content':
                    target = os.path.basename(args.get('TargetFile', ''))
                    if target in files:
                        # process chunks in reverse order to not mess up line numbers
                        chunks = args.get('ReplacementChunks', [])
                        chunks.sort(key=lambda x: x.get('StartLine', 0), reverse=True)
                        lines = files[target].split('\n')
                        for chunk in chunks:
                            start = chunk.get('StartLine', 1)
                            end = chunk.get('EndLine', 1)
                            replacement = chunk.get('ReplacementContent', '')
                            lines = lines[:start-1] + replacement.split('\n') + lines[end:]
                        files[target] = '\n'.join(lines)

for target, content in files.items():
    with open(target, 'w', encoding='utf-8') as out:
        out.write(content)
        print(f"Recovered {target}")

