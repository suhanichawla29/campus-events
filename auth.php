<?php
session_start();
include 'includes/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'login';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($action == 'login' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: dashboard.php");
            exit();
        }
    }
    $error = "Invalid email or password!";
}

if ($action == 'register' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Email already registered! Please login.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if (mysqli_query($conn, "INSERT INTO users (full_name, email, phone, password) VALUES ('$full_name', '$email', '$phone', '$hashed')")) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <?php if ($action == 'register'): ?>
        <h2>Student Registration</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <form method="POST" onsubmit="return validateRegisterForm()">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" id="reg-name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="reg-email" required></div>
            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" id="reg-phone"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" id="reg-password" required></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" id="reg-confirm" required></div>
            <button type="submit" class="btn btn-primary">Register</button>
            <p class="form-link">Already have an account? <a href="auth.php?action=login">Login here</a></p>
        </form>
    <?php else: ?>
        <h2>Student Login</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <form method="POST" onsubmit="return validateLoginForm()">
            <div class="form-group"><label>Email</label><input type="email" name="email" id="login-email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" id="login-password" required></div>
            <button type="submit" class="btn btn-primary">Login</button>
            <p class="form-link">Don't have an account? <a href="auth.php?action=register">Register here</a></p>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>