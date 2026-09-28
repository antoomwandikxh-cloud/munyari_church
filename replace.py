import sys

with open('role_departments.php', 'r', encoding='utf-8') as f:
    content = f.read()

old = '''        CASE 
            WHEN LOWER(\) LIKE '%youth chairperson%' OR LOWER(\) LIKE '%women chairlady%' OR LOWER(\) LIKE '%elder chairman%' OR LOWER(\) LIKE '%sunday school patron%' OR LOWER(\) LIKE '%general church secretary%' THEN 1
            WHEN LOWER(\) LIKE '%vice youth chairperson%' OR LOWER(\) LIKE '%vice women chairlady%' OR LOWER(\) LIKE '%vice elder chairman%' OR LOWER(\) LIKE '%vice sunday school patron%' THEN 2
            WHEN LOWER(\) LIKE '%youth secretary%' OR LOWER(\) LIKE '%women secretary%' OR LOWER(\) LIKE '%elder secretary%' OR LOWER(\) LIKE '%sunday school secretary%' THEN 3
            WHEN LOWER(\) LIKE '%vice church secretary%' OR LOWER(\) LIKE '%vice youth secretary%' OR LOWER(\) LIKE '%vice women secretary%' OR LOWER(\) LIKE '%vice elder secretary%' OR LOWER(\) LIKE '%vice sunday school secretary%' THEN 4
            WHEN LOWER(\) LIKE '%youth treasurer%' OR LOWER(\) LIKE '%women treasurer%' OR LOWER(\) LIKE '%elder treasurer%' OR LOWER(\) LIKE '%sunday school treasurer%' OR (LOWER(\) LIKE '%treasurer%' AND LOWER(\) NOT LIKE '%building treasurer%') THEN 5
            WHEN LOWER(\) LIKE '%mama youth%' OR LOWER(\) LIKE '%baba youth%' THEN 6
            WHEN LOWER(\) LIKE '%head usher%' THEN 10
            WHEN LOWER(\) LIKE '%usher%' AND LOWER(\) NOT LIKE '%head usher%' THEN 11
            WHEN LOWER(\) LIKE '%building chairperson%' THEN 12
            WHEN LOWER(\) LIKE '%vice building chairperson%' THEN 13
            WHEN LOWER(\) LIKE '%building secretary%' THEN 14
            WHEN LOWER(\) LIKE '%vice building secretary%' THEN 15
            WHEN LOWER(\) LIKE '%building treasurer%' THEN 16
            WHEN LOWER(\) LIKE '%organizing secretary%' THEN 20
            WHEN LOWER(\) LIKE '%discipline master%' THEN 21
            WHEN LOWER(\) LIKE '%graduands secretary%' THEN 22
            WHEN LOWER(\) LIKE '%prayer coordinator%' THEN 23
            WHEN LOWER(\) LIKE '%sport secretary%' OR LOWER(\) LIKE '%sports secretary%' THEN 24
            WHEN LOWER(\) LIKE '%choir leader%' THEN 25
            WHEN LOWER(TRIM(COALESCE(\, ''))) = 'member' THEN 90
            WHEN TRIM(COALESCE(\, '')) = '' THEN 90
            ELSE 40
        END'''

new_repl = '''        CASE 
            WHEN LOWER(\) REGEXP '(^|, *)(youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)( *,|$)' THEN 1
            WHEN LOWER(\) REGEXP '(^|, *)vice (youth |women |elder |sunday school |building )?(chairman|chairperson|chairlady|patron)( *,|$)' THEN 2
            WHEN LOWER(\) REGEXP '(^|, *)(general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary( *,|$)' THEN 3
            WHEN LOWER(\) REGEXP '(^|, *)vice (general church |youth |women |elder |sunday school |building |organizing |sport |sports |graduands |choir |prayer )?secretary( *,|$)' THEN 4
            WHEN LOWER(\) REGEXP '(^|, *)(youth |women |elder |sunday school |building )?treasurer( *,|$)' THEN 5
            WHEN LOWER(\) REGEXP '(^|, *)vice (youth |women |elder |sunday school |building )?treasurer( *,|$)' THEN 6
            WHEN LOWER(\) REGEXP '(^|, *)(mama youth|baba youth)( *,|$)' THEN 7
            WHEN LOWER(\) REGEXP '(^|, *)head usher( *,|$)' THEN 10
            WHEN LOWER(\) REGEXP '(^|, *)usher( *,|$)' THEN 11
            WHEN LOWER(\) REGEXP '(^|, *)discipline master( *,|$)' THEN 21
            WHEN LOWER(\) REGEXP '(^|, *)prayer coordinator( *,|$)' THEN 23
            WHEN LOWER(\) REGEXP '(^|, *)choir leader( *,|$)' THEN 25
            WHEN LOWER(TRIM(COALESCE(\, ''))) = 'member' THEN 90
            WHEN TRIM(COALESCE(\, '')) = '' THEN 90
            ELSE 40
        END'''

if old in content:
    content = content.replace(old, new_repl)
    with open('role_departments.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("Success")
else:
    print("Could not find old text")
