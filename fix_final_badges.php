<?php
// The ONE clean badge block to add at end of file
$badge_block = '
<script>
/* FIELD-STATUS-BADGES-v1 */
if (typeof window._fieldBadgesInit === "undefined") {
    window._fieldBadgesInit = true;
    function getStatusBadge(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        var grp = el.closest(".form-group");
        if (!grp) return null;
        var lbl = grp.querySelector("label");
        if (!lbl) return null;
        var b = lbl.querySelector(".sb");
        if (!b) {
            b = document.createElement("span");
            b.className = "sb";
            b.style.cssText = "margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;transition:all 0.2s;vertical-align:middle;display:inline-block;";
            lbl.appendChild(b);
        }
        return b;
    }
    function setFieldStatus(id, s) {
        var b = getStatusBadge(id); if (!b) return;
        if (s==="FILLING")    { b.textContent="FILLING";      b.style.background="#fef08a"; b.style.color="#854d0e"; }
        else if (s==="FILLED"){ b.textContent="\u2713 FILLED"; b.style.background="#dcfce7"; b.style.color="#166534"; }
        else if (s==="ACTIVATED"){ b.textContent="ACTIVATED"; b.style.background="#dbeafe"; b.style.color="#1e40af"; }
        else { b.textContent=""; b.style.background="transparent"; b.style.color="transparent"; }
    }
    function seqUnlock(curId, nxtId) {
        var cur = document.getElementById(curId), nxt = document.getElementById(nxtId);
        if (!cur || !nxt) return;
        var filled = cur.tagName==="SELECT" ? cur.value!=="" : cur.value.trim().length>0 && cur.checkValidity();
        var wasDis = nxt.disabled;
        nxt.disabled = !filled;
        if (!filled && nxt.tagName==="INPUT") nxt.value = "";
        if (filled) {
            setFieldStatus(curId, "FILLED");
            if (wasDis && !nxt.disabled && nxt.value.trim()==="") setFieldStatus(nxtId, "ACTIVATED");
        } else { setFieldStatus(nxtId, "EMPTY"); }
    }
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".form-group input, .form-group select").forEach(function(el) {
            if (["radio","checkbox","hidden","submit","button"].indexOf(el.type)!==-1) return;
            el.addEventListener("input", function(){ setFieldStatus(el.id, el.value.trim().length>0?"FILLING":"EMPTY"); });
            el.addEventListener("change", function(){ if(el.tagName==="SELECT"&&el.value!=="") setFieldStatus(el.id,"FILLED"); });
            el.addEventListener("blur", function(){
                if(el.value.trim().length>0&&el.checkValidity()) setFieldStatus(el.id,"FILLED");
                else if(el.value.trim().length===0) setFieldStatus(el.id,"EMPTY");
            });
        });
    });
}
</script>';

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);

    // 1. Strip out ALL previously inserted badge blocks (tagged with FIELD-STATUS-BADGES or our comments)
    $c = preg_replace('/<script>\s*\/\*[\s\S]*?FIELD[- ]STATUS[- ]BADGES[\s\S]*?\*\/[\s\S]*?<\/script>/i', '', $c);
    
    // 2. Also strip old un-tagged getStatusBadge script injections (injected inside <script> tags near </body>)
    // Find the <script> block containing getStatusBadge (even if untagged)
    $c = preg_replace('/<script>\s*(?:\/\*[^*]*\*\/)?\s*(?:if\s*\(typeof[^)]+\)\s*\{)?\s*function getStatusBadge[\s\S]*?<\/script>/i', '', $c);

    // 3. Ensure inline seqUnlock in <script> sections within the page still reference the global one.
    // Replace all inline function definitions of seqUnlock to just call global (remove redefinitions)
    // But keep them if they're the only copy. Better: replace duplicate inline seqUnlock BODIES  
    // with a stub that delegates to global version.
    // Count occurrences
    preg_match_all('/function seqUnlock\(/', $c, $matches);
    $count = count($matches[0]);
    
    // If more than 1 definition, strip all but keep just one (via the global in our block)
    if ($count > 0) {
        // Remove ALL inline seqUnlock function definitions (they will be replaced by the global one)
        $c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?(?:setFieldStatus\(nextId,\s*"EMPTY"\);?\s*\}|\}\s*\n)/m', '/* seqUnlock handled globally */', $c);
    }

    // 4. Add one clean copy before </body>
    $c = str_replace('</body>', $badge_block . "\n</body>", $c);

    file_put_contents($file, $c);
    echo "Done: $file\n";
}
?>
