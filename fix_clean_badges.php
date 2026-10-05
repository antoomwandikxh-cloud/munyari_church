<?php
$badge_script = '
<script>
/* FIELD-STATUS-BADGES-v1 */
if (!window._fsb) { window._fsb = true;
function getStatusBadge(id) {
    var el = document.getElementById(id); if (!el) return null;
    var g = el.closest(".form-group"); if (!g) return null;
    var lbl = g.querySelector("label"); if (!lbl) return null;
    var b = lbl.querySelector(".sb");
    if (!b) { b = document.createElement("span"); b.className="sb";
        b.style.cssText="margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;transition:all 0.2s;vertical-align:middle;display:inline-block;";
        lbl.appendChild(b); }
    return b;
}
function setFieldStatus(id, s) {
    var b = getStatusBadge(id); if (!b) return;
    if      (s==="FILLING")   { b.textContent="FILLING";      b.style.background="#fef08a"; b.style.color="#854d0e"; }
    else if (s==="FILLED")    { b.textContent="\u2713 FILLED"; b.style.background="#dcfce7"; b.style.color="#166534"; }
    else if (s==="ACTIVATED") { b.textContent="ACTIVATED";    b.style.background="#dbeafe"; b.style.color="#1e40af"; }
    else { b.textContent=""; b.style.background="transparent"; b.style.color="transparent"; }
}
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".form-group input,.form-group select").forEach(function(el) {
        if (["radio","checkbox","hidden","submit","button"].indexOf(el.type)!==-1) return;
        el.addEventListener("input",  function(){ setFieldStatus(el.id, el.value.trim().length>0?"FILLING":"EMPTY"); });
        el.addEventListener("change", function(){ if(el.tagName==="SELECT"&&el.value!=="") setFieldStatus(el.id,"FILLED"); });
        el.addEventListener("blur",   function(){
            if(el.value.trim().length>0&&el.checkValidity()) setFieldStatus(el.id,"FILLED");
            else if(!el.value.trim().length) setFieldStatus(el.id,"EMPTY");
        });
    });
});
}
</script>';

// New seqUnlock body (with status logic)
$new_body_indented = '(currentId, nextId) {
    var cur = document.getElementById(currentId), nxt = document.getElementById(nextId);
    if (!cur || !nxt) return;
    var filled = cur.tagName==="SELECT" ? cur.value!=="" : cur.value.trim().length>0 && cur.checkValidity();
    var wasDis = nxt.disabled;
    nxt.disabled = !filled;
    if (!filled && nxt.tagName==="INPUT") nxt.value = "";
    if (typeof setFieldStatus==="function") {
        if (filled) {
            setFieldStatus(currentId, "FILLED");
            if (wasDis && !nxt.disabled && nxt.value.trim()==="") setFieldStatus(nextId, "ACTIVATED");
        } else { setFieldStatus(nextId, "EMPTY"); }
    }
}';

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Replace all seqUnlock function bodies — match various indentation levels
    $c = preg_replace_callback(
        '/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?^\s*\}/m',
        function($m) use ($new_body_indented) {
            // Detect indent level of the original function keyword
            preg_match('/^(\s*)function/', $m[0], $ind);
            $indent = $ind[1] ?? '';
            // Indent each line of new body
            $lines = explode("\n", $new_body_indented);
            $indented = implode("\n" . $indent, $lines);
            return $indent . "function seqUnlock" . $indented;
        },
        $c
    );
    
    // Add badge script once before </body>
    $c = str_replace('</body>', $badge_script . "\n</body>", $c);
    
    file_put_contents($file, $c);
    
    // Verify only one getStatusBadge now
    $count = substr_count($c, 'function getStatusBadge');
    echo "Done: $file — getStatusBadge count: $count\n";
}
?>
