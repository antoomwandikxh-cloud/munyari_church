content = open('admin_dashboard.php', 'r', encoding='utf-8').read()

old1 = '<div class="table-responsive" id="village_table_<?= str_replace('' '', ''_'', $v) ?>">\n                        <table>'

new1 = '''<div class="table-responsive" id="village_table_<?= str_replace(' ', '_', $v) ?>">
                        <div class="print-leader-profile" style="display:none; text-align:center; margin-bottom:20px;">
                            <?php if (!empty($v_leaders[0])): ?>
                                <img src="uploads/<?= htmlspecialchars($v_leaders[0]['profile_picture'] ?? 'default_avatar.png') ?>" style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid <?= $v_color ?>; margin-bottom:8px;">
                                <div style="font-size:1.1rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-bottom:2px;"><?= htmlspecialchars($v_leaders[0]['first_name'] . ' ' . $v_leaders[0]['last_name']) ?></div>
                                <div style="font-size:0.85rem; font-weight:600; color:<?= $v_color ?>; margin-bottom:4px;"><?= $v ?> Village Leader</div>
                                <div style="font-size:0.75rem; color:#64748b; font-weight:600;"><?= htmlspecialchars($v_leaders[0]['phone']) ?></div>
                            <?php else: ?>
                                <img src="uploads/default_avatar.png" style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid #cbd5e1; margin-bottom:8px; opacity:0.7; filter:grayscale(100%);">
                                <div style="font-size:1.1rem; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:2px;">(Not Assigned)</div>
                                <div style="font-size:0.85rem; font-weight:600; color:#64748b; margin-bottom:4px;"><?= $v ?> Village Leader</div>
                            <?php endif; ?>
                        </div>
                        <table>'''

old2 = "var theadHTML = container.querySelector('thead') ? container.querySelector('thead').outerHTML : '';\n        var tbodyHTML = container.querySelector('tbody') ? container.querySelector('tbody').outerHTML : '';"
new2 = old2 + "\n        var leaderHTML = container.querySelector('.print-leader-profile') ? container.querySelector('.print-leader-profile').outerHTML.replace('display:none', 'display:block') : '';"

old3 = "w.document.write('</div>');\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');"
new3 = "w.document.write('</div>');\n        w.document.write(leaderHTML);\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');"

# Try replacements
content = content.replace(old1, new1).replace(old1.replace('\n', '\r\n'), new1)
content = content.replace(old2, new2).replace(old2.replace('\n', '\r\n'), new2)
content = content.replace(old3, new3).replace(old3.replace('\n', '\r\n'), new3)

open('admin_dashboard.php', 'w', encoding='utf-8').write(content)
print("Done admin")
