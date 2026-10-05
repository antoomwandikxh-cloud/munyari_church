<?php

$badge_block = '<script>
/* === FIELD STATUS BADGES === */
if (typeof getStatusBadge === "undefined") {
function getStatusBadge(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return null;
    var group = input.closest(".form-group");
    if (!group) return null;
    var label = group.querySelector("label");
    if (!label) return null;
    var badge = label.querySelector(".status-badge");
    if (!badge) {
        badge = document.createElement("span");
        badge.className = "status-badge";
        badge.style.cssText = "margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;transition:all 0.25s;vertical-align:middle;";
        label.appendChild(badge);
    }
    return badge;
}
function setFieldStatus(inputId, status) {
    var badge = getStatusBadge(inputId);
    if (!badge) return;
    if (status === "FILLING") {
        badge.textContent = "FILLING";
        badge.style.backgroundColor = "#fef08a"; badge.style.color = "#854d0e";
    } else if (status === "FILLED") {
        badge.textContent = "\u2713 FILLED";
        badge.style.backgroundColor = "#dcfce7"; badge.style.color = "#166534";
    } else if (status === "ACTIVATED") {
        badge.textContent = "ACTIVATED";
        badge.style.backgroundColor = "#dbeafe"; badge.style.color = "#1e40af";
    } else {
        badge.textContent = "";
        badge.style.backgroundColor = "transparent"; badge.style.color = "transparent";
    }
}
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".form-group input, .form-group select").forEach(function(el) {
        if (["radio","checkbox","hidden","submit","button"].indexOf(el.type) !== -1) return;
        el.addEventListener("input", function() {
            setFieldStatus(el.id, el.value.trim().length > 0 ? "FILLING" : "EMPTY");
        });
        el.addEventListener("change", function() {
            if (el.tagName === "SELECT" && el.value !== "") setFieldStatus(el.id, "FILLED");
        });
        el.addEventListener("blur", function() {
            if (el.value.trim().length > 0 && el.checkValidity()) setFieldStatus(el.id, "FILLED");
            else if (el.value.trim().length === 0) setFieldStatus(el.id, "EMPTY");
        });
    });
});
}
</script>';

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // 1. Remove ALL previously injected copies of getStatusBadge (in <script> tags added by us)
    $c = preg_replace('/<script>\s*\/\*\s*=+\s*FIELD STATUS BADGES\s*=+\s*\*\/[\s\S]*?<\/script>/i', '', $c);
    
    // 2. Remove the old duplicated getStatusBadge blocks injected without markers
    // Find the closing </body> and inject our clean block once just before it
    $c = str_replace('</body>', $badge_block . "\n</body>", $c);
    
    file_put_contents($file, $c);
    echo "Cleaned and injected: $file\n";
}
?>
