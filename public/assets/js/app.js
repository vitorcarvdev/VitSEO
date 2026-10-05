(function () {
    var root = document.querySelector("[data-tabs]");
    if (root) {
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
        var panels = Array.prototype.slice.call(root.querySelectorAll('[role="tabpanel"]'));

        function activate(index) {
            tabs.forEach(function (tab, i) {
                var selected = i === index;
                tab.setAttribute("aria-selected", selected ? "true" : "false");
                tab.tabIndex = selected ? 0 : -1;
            });
            panels.forEach(function (panel, i) {
                if (i === index) {
                    panel.removeAttribute("hidden");
                } else {
                    panel.setAttribute("hidden", "");
                }
            });
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener("click", function () {
                activate(index);
                tab.focus();
            });
            tab.addEventListener("keydown", function (event) {
                var next = index;
                if (event.key === "ArrowRight") next = (index + 1) % tabs.length;
                if (event.key === "ArrowLeft") next = (index - 1 + tabs.length) % tabs.length;
                if (next !== index) {
                    event.preventDefault();
                    activate(next);
                    tabs[next].focus();
                }
            });
        });
    }

    var form = document.getElementById("audit-form");
    if (form) {
        form.addEventListener("submit", function () {
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = "Analisando…";
            }
            document.body.classList.add("is-analyzing");
        });
    }

    var aiButton = document.getElementById("ai-adjust");
    if (aiButton && !aiButton.disabled) {
        aiButton.addEventListener("click", function () {
            var source = document.getElementById("ai-prompt");
            if (!source) {
                return;
            }
            var prompt = "";
            try {
                prompt = JSON.parse(source.textContent || "");
            } catch (error) {
                return;
            }
            if (!prompt) {
                return;
            }
            var showCopied = function () {
                var note = document.getElementById("ai-feedback");
                if (!note) {
                    return;
                }
                note.hidden = false;
                window.setTimeout(function () {
                    note.hidden = true;
                }, 2000);
            };
            var fallbackCopy = function () {
                var field = document.createElement("textarea");
                field.value = prompt;
                field.setAttribute("readonly", "");
                field.style.position = "fixed";
                field.style.left = "-9999px";
                document.body.appendChild(field);
                field.select();
                var copied = false;
                try {
                    copied = document.execCommand("copy");
                } catch (error) {
                    copied = false;
                }
                field.remove();
                if (copied) {
                    showCopied();
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(prompt).then(showCopied).catch(fallbackCopy);
            } else {
                fallbackCopy();
            }
        });
    }

    window.addEventListener("pageshow", function () {
        document.body.classList.remove("is-analyzing");
        var button = document.querySelector('#audit-form button[type="submit"]');
        if (button) {
            button.disabled = false;
            button.textContent = "Analisar site";
        }
    });
}());
