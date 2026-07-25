<?php
session_start();
include 'config.php';

function eventCard($e) {
    $img = $e['image'] ? "uploads/" . $e['image'] : "assets/images/default-event.jpg";
    $date = date("d M Y", strtotime($e['event_date']));
    $time = date("h:i A", strtotime($e['event_time']));
    echo '<div class="event-card">';
    echo '<img src="' . $img . '" alt="' . $e['title'] . '" class="event-card-img">';
    echo '<div class="event-card-body">';
    echo '<h3>' . $e['title'] . '</h3>';
    echo '<p class="event-date">' . $date . ' at ' . $time . '</p>';
    echo '<p><strong>Venue:</strong> ' . $e['venue'] . '</p>';
    echo '<p><strong>Category:</strong> ' . $e['category'] . '</p>';
    if (isset($e['organizer']) && $e['organizer']) { echo '<p><strong>Organizer:</strong> ' . $e['organizer'] . '</p>'; }
    echo '<a href="index.php?page=detail&id=' . $e['id'] . '" class="btn btn-small">';
    echo isset($e['btn_text']) ? $e['btn_text'] : 'View Details';
    echo '</a></div></div>';
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

if ($page == 'login' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: index.php?page=dashboard");
            exit();
        }
    }
    $login_error = "Invalid email or password!";
}

if ($page == 'register' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    if ($password !== $confirm_password) {
        $reg_error = "Passwords do not match!";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $reg_error = "Email already registered! Please login.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if (mysqli_query($conn, "INSERT INTO users (full_name, email, phone, password) VALUES ('$full_name', '$email', '$phone', '$hashed')")) {
                $reg_success = "Registration successful! You can now login.";
            } else {
                $reg_error = "Something went wrong. Please try again.";
            }
        }
    }
}

if ($page == 'logout') { session_destroy(); header("Location: index.php"); exit(); }
if ($page == 'dashboard' && !isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Event Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">CampusEvents</a>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php?page=events">Events</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="index.php?page=dashboard">Dashboard</a></li>
                <li><a href="index.php?page=logout">Logout (<?php echo $_SESSION['user_name']; ?>)</a></li>
            <?php else: ?>
                <li><a href="index.php?page=login">Login</a></li>
                <li><a href="index.php?page=register">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<div class="container">

<?php if ($page == 'home'): ?>

<section class="hero">
    <div class="hero-content">
        <h1>Welcome to Campus Event Management System</h1>
        <p>Stay updated with all the exciting events happening on campus. Register, participate, and make the most of your college life!</p>
        <a href="index.php?page=events" class="btn btn-primary">Browse Events</a>
    </div>
</section>

<section class="about-section">
    <h2>About Campus Events</h2>
    <p>Campus events bring students together for learning, fun, and networking. From technical workshops to cultural fests, our platform helps you discover and register for events that match your interests.</p>
</section>

<section class="events-section">
    <h2>Upcoming Events</h2>
    <div class="event-grid">
        <?php $result = mysqli_query($conn, "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6"); ?>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($e = mysqli_fetch_assoc($result)): eventCard($e); endwhile; ?>
        <?php else: ?>
            <p class="no-events">No upcoming events at the moment. Check back later!</p>
        <?php endif; ?>
    </div>
</section>

<?php elseif ($page == 'events'): ?>

<div class="page-header"><h2>All Events</h2></div>

<?php
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$cat_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$q = "SELECT * FROM events WHERE 1=1";
if ($search) { $q .= " AND (title LIKE '%$search%' OR description LIKE '%$search%')"; }
if ($cat_filter) { $q .= " AND category = '$cat_filter'"; }
$ev_result = mysqli_query($conn, $q . " ORDER BY event_date ASC");
$events_arr = array();
$categories = array();
while ($row = mysqli_fetch_assoc($ev_result)) {
    $events_arr[] = $row;
    if ($row['category'] && !in_array($row['category'], $categories)) { $categories[] = $row['category']; }
}
?>

<div class="search-filter">
    <form method="GET" action="">
        <input type="hidden" name="page" value="events">
        <input type="text" name="search" placeholder="Search events..." value="<?php echo $search; ?>">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c; ?>" <?php echo ($cat_filter == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<div class="event-grid">
    <?php if (count($events_arr) > 0): ?>
        <?php foreach ($events_arr as $e): eventCard($e); endforeach; ?>
    <?php else: ?>
        <p class="no-events">No events found.</p>
    <?php endif; ?>
</div>

<?php elseif ($page == 'detail'): ?>

<?php
if (!isset($_GET['id'])) { header("Location: index.php?page=events"); exit(); }
$eid = mysqli_real_escape_string($conn, $_GET['id']);
$e = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id = '$eid'"));
if (!$e) { echo "<p>Event not found.</p>"; }
else {
    $already = false;
    $reg_status = "";
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        if (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM registrations WHERE user_id = '$uid' AND event_id = '$eid'"))) { $already = true; }
        if (isset($_POST['register_event']) && !$already) {
            if (mysqli_query($conn, "INSERT INTO registrations (user_id, event_id) VALUES ('$uid', '$eid')")) { $reg_status = "success"; $already = true; }
            else { $reg_status = "error"; }
        }
    }
?>
    <div class="event-detail">
        <?php if ($e['image']): ?><img src="uploads/<?php echo $e['image']; ?>" alt="<?php echo $e['title']; ?>" class="event-detail-img"><?php endif; ?>
        <h1><?php echo $e['title']; ?></h1>
        <div class="event-info">
            <p><strong>Date:</strong> <?php echo date("d M Y", strtotime($e['event_date'])); ?></p>
            <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($e['event_time'])); ?></p>
            <p><strong>Venue:</strong> <?php echo $e['venue']; ?></p>
            <p><strong>Category:</strong> <?php echo $e['category']; ?></p>
            <p><strong>Organizer:</strong> <?php echo $e['organizer']; ?></p>
        </div>
        <div class="event-description"><h3>About this Event</h3><p><?php echo nl2br($e['description']); ?></p></div>
        <div class="event-register-section">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($reg_status == "success"): ?><div class="alert alert-success">You have successfully registered for this event!</div>
                <?php elseif ($reg_status == "error"): ?><div class="alert alert-error">Something went wrong. Please try again.</div><?php endif; ?>
                <?php if ($already): ?><div class="alert alert-info">You are already registered for this event.</div>
                <?php else: ?><form method="POST"><button type="submit" name="register_event" class="btn btn-primary">Register for this Event</button></form><?php endif; ?>
            <?php else: ?><p><a href="index.php?page=login" class="btn btn-primary">Login to Register</a></p><?php endif; ?>
        </div>
    </div>
<?php } ?>

<?php elseif ($page == 'login'): ?>

<div class="form-container">
    <h2>Student Login</h2>
    <?php if (isset($login_error)): ?><div class="alert alert-error"><?php echo $login_error; ?></div><?php endif; ?>
    <form method="POST" action="">
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
        <button type="submit" class="btn btn-primary">Login</button>
        <p class="form-link">Don't have an account? <a href="index.php?page=register">Register here</a></p>
    </form>
</div>

<?php elseif ($page == 'register'): ?>

<div class="form-container">
    <h2>Student Registration</h2>
    <?php if (isset($reg_error)): ?><div class="alert alert-error"><?php echo $reg_error; ?></div><?php endif; ?>
    <?php if (isset($reg_success)): ?><div class="alert alert-success"><?php echo $reg_success; ?></div><?php endif; ?>
    <form method="POST" action="">
        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Phone Number</label><input type="text" name="phone"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" minlength="6" required></div>
        <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
        <button type="submit" class="btn btn-primary">Register</button>
        <p class="form-link">Already have an account? <a href="index.php?page=login">Login here</a></p>
    </form>
</div>

<?php elseif ($page == 'dashboard'): ?>

<?php
$uid = $_SESSION['user_id'];
$ds = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$eq = "SELECT * FROM events WHERE event_date >= CURDATE()" . ($ds ? " AND (title LIKE '%$ds%' OR category LIKE '%$ds%')" : "") . " ORDER BY event_date ASC";
$regs = mysqli_query($conn, "SELECT e.*, r.registered_at FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.user_id = '$uid' ORDER BY e.event_date ASC");
?>

<div class="dashboard">
    <h2>Welcome, <?php echo $_SESSION['user_name']; ?>!</h2>

    <section class="dashboard-section">
        <h3>My Registered Events</h3>
        <?php if (mysqli_num_rows($regs) > 0): ?>
            <table class="table">
                <tr><th>Event</th><th>Date</th><th>Venue</th><th>Registered On</th></tr>
                <?php while ($r = mysqli_fetch_assoc($regs)): ?>
                    <tr>
                        <td><a href="index.php?page=detail&id=<?php echo $r['id']; ?>"><?php echo $r['title']; ?></a></td>
                        <td><?php echo date("d M Y", strtotime($r['event_date'])); ?></td>
                        <td><?php echo $r['venue']; ?></td>
                        <td><?php echo date("d M Y", strtotime($r['registered_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?><p class="no-events">You haven't registered for any events yet.</p><?php endif; ?>
    </section>

    <section class="dashboard-section">
        <h3>Browse & Register for Events</h3>
        <div class="search-filter">
            <form method="GET" action="">
                <input type="hidden" name="page" value="dashboard">
                <input type="text" name="search" placeholder="Search by name or category..." value="<?php echo $ds; ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
        <div class="event-grid">
            <?php $evs = mysqli_query($conn, $eq); ?>
            <?php if (mysqli_num_rows($evs) > 0): ?>
                <?php while ($e = mysqli_fetch_assoc($evs)): $e['btn_text'] = 'View &amp; Register'; eventCard($e); endwhile; ?>
            <?php else: ?><p class="no-events">No events found.</p><?php endif; ?>
        </div>
    </section>
</div>

<?php endif; ?>

</div>
<footer class="footer">
    <p>&copy; 2026 Campus Event Management System. All rights reserved.</p>
</footer>
</body>
</html>
