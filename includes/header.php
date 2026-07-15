<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Event Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">CampusEvents</a>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="index.php?logout=1">Logout (<?= $_SESSION['user_name'] ?>)</a></li>
            <?php else: ?>
                <li><a href="auth.php?action=login">Login</a></li>
                <li><a href="auth.php?action=register">Register</a></li>
            <?php endif; ?>
            <li><button class="theme-toggle" onclick="toggleTheme()" id="theme-btn" title="Toggle">🌙</button></li>
        </ul>
    </div>
</nav>
<div class="container">