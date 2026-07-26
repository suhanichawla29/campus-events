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