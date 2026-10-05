<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

// The new fully-featured status badge system (all 3 states)
$badge_helpers = '
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
        badge.style.cssText = "margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;transition:all 0.25s ease;vertical-align:middle;";
        label.appendChild(badge);
    }
    return badge;
}
function setFieldStatus(inputId, status) {
    var badge = getStatusBadge(inputId);
    if (!badge) return;
    if (status === "FILLING") {
        badge.textContent = "FILLING";
        badge.style.backgroundColor = "#fef08a";
        badge.style.color = "#854d0e";
    } else if (status === "FILLED") {
        badge.textContent = "FILLED \u2713";
        badge.style.backgroundColor = "#dcfce7";
        badge.style.color = "#166534";
    } else if (status === "ACTIVATED") {
        badge.textContent = "ACTIVATED";
        badge.style.backgroundColor = "#dbeafe";
        badge.style.color = "#1e40af";
    } else {
        badge.textContent = "";
        badge.style.backgroundColor = "transparent";
        badge.style.color = "transparent";
    }
}
function initFieldBadges() {
    document.querySelectorAll(".form-group input, .form-group select").forEach(function(input) {
        if (input.type === "radio" || input.type === "checkbox" || input.type === "hidden" || input.type === "submit") return;
        input.addEventListener("input", function() {
            setFieldStatus(input.id, input.value.trim().length > 0 ? "FILLING" : "EMPTY");
        });
        input.addEventListener("change", function() {
            if (input.tagName === "SELECT" && input.value !== "") {
                setFieldStatus(input.id, "FILLED");
            }
        });
        input.addEventListener("blur", function() {
            if (input.value.trim().length > 0 && input.checkValidity()) {
                setFieldStatus(input.id, "FILLED");
            } else if (input.value.trim().length === 0) {
                setFieldStatus(input.id, "EMPTY");
            }
        });
    });
}
document.addEventListener("DOMContentLoaded", initFieldBadges);
';

$new_seq_func = '
function seqUnlock(currentId, nextId) {
    var current = document.getElementById(currentId);
    var next = document.getElementById(nextId);
    if (!current || !next) return;
    var filled = current.tagName === "SELECT"
        ? current.value !== ""
        : current.value.trim().length > 0 && current.checkValidity();
    var wasDisabled = next.disabled;
    next.disabled = !filled;
    if (!filled && next.tagName === "INPUT") next.value = "";
    // Status badges
    if (filled) {
        setFieldStatus(currentId, "FILLED");
        if (wasDisabled && !next.disabled && next.value.trim() === "") {
            setFieldStatus(nextId, "ACTIVATED");
        }
    } else {
        setFieldStatus(nextId, "EMPTY");
    }
}
';

// Pattern to match all seqUnlock function definitions (any indentation)
$pattern = '/function seqUnlock\(currentId,\s*nextId\)\s*\{[\s\S]*?\n([ \t]*)\}/';

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // 1. Replace all seqUnlock function bodies
    $c = preg_replace_callback($pattern, function($m) use ($new_seq_func) {
        // Preserve original indentation
        $indent = $m[1];
        return str_replace("\n", "\n$indent", trim($new_seq_func));
    }, $c);
    
    // 2. Remove old .activated-badge blocks that were previously inserted
    $c = preg_replace('/\/\/ --- ADDED: Visual Activated Indicator ---[\s\S]*?\/\/ -----------------------------------------/', '', $c);
    
    // 3. Inject badge helpers + DOMContentLoaded init before </body>
    if (strpos($c, 'function getStatusBadge') === false) {
        $c = str_replace('</body>', "<script>\n$badge_helpers\n</script>\n</body>", $c);
    }
    
    file_put_contents($file, $c);
    echo "Done: $file\n";
}
?>
