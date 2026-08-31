(function() {
    var theme = localStorage.getItem("theme");
    var btn = document.getElementById("theme-btn");
    if (theme === "dark") {
        document.body.classList.add("dark-mode");
        if (btn) btn.innerHTML = "☀️";
    } else {
        document.body.classList.remove("dark-mode");
        if (btn) btn.innerHTML = "🌙";
    }
})();

function toggleTheme() {
    document.body.classList.toggle("dark-mode");
    var btn = document.getElementById("theme-btn");
    if (document.body.classList.contains("dark-mode")) {
        localStorage.setItem("theme", "dark");
        if (btn) btn.innerHTML = "☀️";
    } else {
        localStorage.setItem("theme", "light");
        if (btn) btn.innerHTML = "🌙";
    }
}

function validateRegisterForm() {
    var name = document.getElementById("reg-name").value.trim();
    var email = document.getElementById("reg-email").value.trim();
    var password = document.getElementById("reg-password").value;
    var confirm = document.getElementById("reg-confirm").value;

    if (name == "") {
        alert("Please enter your full name.");
        return false;
    }
    if (email == "") {
        alert("Please enter your email.");
        return false;
    }
    if (email.indexOf("@") == -1 || email.indexOf(".") == -1) {
        alert("Please enter a valid email address.");
        return false;
    }
    if (password.length < 6) {
        alert("Password must be at least 6 characters long.");
        return false;
    }
    if (password != confirm) {
        alert("Passwords do not match.");
        return false;
    }
    return true;
}

function validateLoginForm() {
    var email = document.getElementById("login-email").value.trim();
    var password = document.getElementById("login-password").value;

    if (email == "") {
        alert("Please enter your email.");
        return false;
    }
    if (password == "") {
        alert("Please enter your password.");
        return false;
    }
    return true;
}

(function() {
    var modal = document.getElementById("cancel-modal");
    if (!modal) {
        return;
    }

    var overlay = modal;
    var closeBtn = document.getElementById("cancel-modal-close");
    var keepBtn = document.getElementById("cancel-keep");
    var form = document.getElementById("cancel-form");
    var eventId = document.getElementById("cancel-event-id");
    var eventTitle = document.getElementById("cancel-event-title");
    var eventDate = document.getElementById("cancel-event-date");
    var lastFocused = null;

    var focusableSelector = "button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])";

    function focusables() {
        return Array.prototype.filter.call(
            modal.querySelectorAll(focusableSelector),
            function(el) { return !el.disabled && el.offsetParent !== null; }
        );
    }

    function openModal(id, title, date) {
        eventId.value = id;
        eventTitle.textContent = title;
        eventDate.textContent = date;
        lastFocused = document.activeElement;
        modal.classList.add("show");
        document.body.classList.add("modal-open");
        var first = focusables()[0];
        if (first) {
            first.focus();
        }
    }

    function closeModal() {
        modal.classList.remove("show");
        document.body.classList.remove("modal-open");
        if (lastFocused) {
            lastFocused.focus();
        }
    }

    document.addEventListener("click", function(ev) {
        var trigger = ev.target.closest(".js-cancel-trigger");
        if (trigger) {
            ev.preventDefault();
            var id = trigger.getAttribute("data-event-id");
            var title = trigger.getAttribute("data-event-title");
            var date = trigger.getAttribute("data-event-date");
            openModal(id, title, date);
            return;
        }

        if (ev.target === overlay) {
            closeModal();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", closeModal);
    }
    if (keepBtn) {
        keepBtn.addEventListener("click", closeModal);
    }

    document.addEventListener("keydown", function(ev) {
        if (!modal.classList.contains("show")) {
            return;
        }
        if (ev.key === "Escape") {
            ev.preventDefault();
            closeModal();
            return;
        }
        if (ev.key === "Tab") {
            var list = focusables();
            if (list.length === 0) {
                return;
            }
            var first = list[0];
            var last = list[list.length - 1];
            if (ev.shiftKey && document.activeElement === first) {
                ev.preventDefault();
                last.focus();
            } else if (!ev.shiftKey && document.activeElement === last) {
                ev.preventDefault();
                first.focus();
            }
        }
    });
})();
