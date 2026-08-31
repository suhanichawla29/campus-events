<?php
session_start();
include '../includes/config.php';

$error = "";
$message = "";

if (!isset($_SESSION['admin_id']) && $_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ? AND password = MD5(?)");
    mysqli_stmt_bind_param($stmt, "ss", $username, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {
        $admin = mysqli_fetch_assoc($result);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
    } else {
        $error = "Invalid admin username or password!";
    }
}

if (!isset($_SESSION['admin_id'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Login - Campus Events</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<div class="admin-login-wrapper">
    <div class="form-container">
        <h2>Admin Login</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="btn btn-primary">Login as Admin</button>
            <p class="form-link"><a href="../index.php">Back to Home</a></p>
        </form>
    </div>
</div>
</body>
</html>
<?php exit(); endif;

if (isset($_POST['add_event'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = $_POST['venue'];
    $category = $_POST['category'];
    $organizer = $_POST['organizer'];
    $image = "";

    if (isset($_FILES['image']['name']) && $_FILES['image']['name']) {
        $image = time() . "_" . $_FILES['image']['name'];
        $extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
        if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $image);
        } else {
            $image = "";
        }
    }

    $capacity = max(0, (int)$_POST['capacity']);
    $stmt = mysqli_prepare($conn, "INSERT INTO events (title, description, event_date, event_time, venue, category, organizer, image, capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssssi", $title, $description, $event_date, $event_time, $venue, $category, $organizer, $image, $capacity);
        if (mysqli_stmt_execute($stmt)) {
            $message = "added";
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_POST['edit_event'])) {
    $event_id = $_POST['event_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = $_POST['venue'];
    $category = $_POST['category'];
    $organizer = $_POST['organizer'];
    $current_image = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM events WHERE id = " . (int)$event_id));
    $image = $current_image['image'];

    if (isset($_FILES['image']['name']) && $_FILES['image']['name']) {
        $image = time() . "_" . $_FILES['image']['name'];
        $extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
        if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
            if ($current_image['image'] && file_exists("../uploads/" . $current_image['image'])) {
                unlink("../uploads/" . $current_image['image']);
            }
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $image);
        } else {
            $image = $current_image['image'];
        }
    }

    $capacity = max(0, (int)$_POST['capacity']);
    $stmt = mysqli_prepare($conn, "UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, venue = ?, category = ?, organizer = ?, image = ?, capacity = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssssii", $title, $description, $event_date, $event_time, $venue, $category, $organizer, $image, $capacity, $event_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "updated";
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $image_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM events WHERE id = $delete_id"));
    if ($image_row['image'] && file_exists("../uploads/" . $image_row['image'])) {
        unlink("../uploads/" . $image_row['image']);
    }
    mysqli_query($conn, "DELETE FROM events WHERE id = $delete_id");
    header("Location: index.php?msg=deleted");
    exit();
}

$events_result = mysqli_query($conn, "SELECT e.*, COUNT(r.id) AS registered_count FROM events e LEFT JOIN registrations r ON r.event_id = e.id GROUP BY e.id ORDER BY e.event_date ASC");
$total_events = mysqli_num_rows($events_result);
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_registrations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM registrations"))['c'];

$events = array();
while ($event = mysqli_fetch_assoc($events_result)) {
    $events[] = $event;
}

$edit_event = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_event = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id = $edit_id"));
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

$message_text = "";
if ($message == "added") $message_text = "Event added successfully!";
elseif ($message == "updated") $message_text = "Event updated successfully!";
elseif ($message == "deleted") $message_text = "Event deleted successfully!";

$categories = array('Technical', 'Cultural', 'Sports', 'Workshop', 'Seminar', 'Other');

function categoryOptions($categories, $selected = "") {
    $options = '<option value="">Select Category</option>';
    foreach ($categories as $category) {
        $is_selected = ($category == $selected) ? ' selected' : '';
        $options .= '<option value="' . $category . '"' . $is_selected . '>' . $category . '</option>';
    }
    return $options;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Panel - Campus Events</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<nav class="navbar admin-nav">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">Admin Panel</a>
        <ul class="nav-menu">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="index.php?add=1">Add Event</a></li>
            <li><a href="../index.php?logout=1">Logout</a></li>
            <li><button class="theme-toggle" onclick="toggleTheme()" id="theme-btn" title="Toggle">🌙</button></li>
        </ul>
    </div>
</nav>
<div class="container">
<div class="admin-dashboard">
    <h2>Admin Dashboard</h2>
    <?php if ($message_text): ?><div class="alert alert-success"><?= $message_text ?></div><?php endif; ?>
    <div class="stats">
        <div class="stat-card"><h3><?= $total_events ?></h3><p>Total Events</p></div>
        <div class="stat-card"><h3><?= $total_users ?></h3><p>Registered Students</p></div>
        <div class="stat-card"><h3><?= $total_registrations ?></h3><p>Total Registrations</p></div>
    </div>
    <?php if (isset($_GET['add'])): ?>
    <div class="admin-form" style="margin-bottom:25px;">
        <h3>Add New Event</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group"><label>Event Title</label><input type="text" name="title" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            <div class="form-row"><div class="form-group"><label>Date</label><input type="date" name="event_date" required></div><div class="form-group"><label>Time</label><input type="time" name="event_time"></div></div>
            <div class="form-group"><label>Venue</label><input type="text" name="venue"></div>
            <div class="form-group"><label>Category</label><select name="category"><?= categoryOptions($categories) ?></select></div>
            <div class="form-row"><div class="form-group"><label>Organizer</label><input type="text" name="organizer"></div><div class="form-group"><label>Seat Capacity</label><input type="number" name="capacity" min="0" max="100000" value="50"><p class="current-image">Total seats available (0 = no limit)</p></div></div>
            <div class="form-group"><label>Event Image</label><input type="file" name="image"></div>
            <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <?php endif; ?>
    <?php if ($edit_event): ?>
    <div class="admin-form" style="margin-bottom:25px;">
        <h3>Edit Event</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?= $edit_event['id'] ?>">
            <div class="form-group"><label>Event Title</label><input type="text" name="title" value="<?= $edit_event['title'] ?>" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?= $edit_event['description'] ?></textarea></div>
            <div class="form-row"><div class="form-group"><label>Date</label><input type="date" name="event_date" value="<?= $edit_event['event_date'] ?>" required></div><div class="form-group"><label>Time</label><input type="time" name="event_time" value="<?= $edit_event['event_time'] ?>"></div></div>
            <div class="form-group"><label>Venue</label><input type="text" name="venue" value="<?= $edit_event['venue'] ?>"></div>
            <div class="form-group"><label>Category</label><select name="category"><?= categoryOptions($categories, $edit_event['category']) ?></select></div>
            <div class="form-row"><div class="form-group"><label>Organizer</label><input type="text" name="organizer" value="<?= $edit_event['organizer'] ?>"></div><div class="form-group"><label>Seat Capacity</label><input type="number" name="capacity" min="0" max="100000" value="<?= isset($edit_event['capacity']) ? (int)$edit_event['capacity'] : 0 ?>"><p class="current-image">Total seats available (0 = no limit)</p></div></div>
            <div class="form-group"><label>Event Image</label><input type="file" name="image"><?php if ($edit_event['image']): ?><p class="current-image">Current: <?= $edit_event['image'] ?></p><?php endif; ?></div>
            <button type="submit" name="edit_event" class="btn btn-primary">Update Event</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <?php endif; ?>
    <table class="table admin-table">
        <tr><th>ID</th><th>Title</th><th>Date</th><th>Time</th><th>Venue</th><th>Category</th><th>Seats (Registered / Capacity)</th><th>Actions</th></tr>
        <?php if (count($events) > 0): ?>
            <?php foreach ($events as $event): ?>
                <?php
                    $capacity = isset($event['capacity']) ? (int)$event['capacity'] : 0;
                    $seats_cell = ($capacity > 0)
                        ? (int)$event['registered_count'] . ' / ' . $capacity
                        : (int)$event['registered_count'] . ' / &infin;';
                ?>
                <tr>
                    <td><?= $event['id'] ?></td>
                    <td><?= $event['title'] ?></td>
                    <td><?= date("d M Y", strtotime($event['event_date'])) ?></td>
                    <td><?= date("h:i A", strtotime($event['event_time'])) ?></td>
                    <td><?= $event['venue'] ?></td>
                    <td><?= $event['category'] ?></td>
                    <td><?= $seats_cell ?></td>
                    <td class="actions">
                        <a href="index.php?edit=<?= $event['id'] ?>" class="btn btn-small btn-edit">Edit</a>
                        <a href="index.php?delete=<?= $event['id'] ?>" class="btn btn-small btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>
</div>
<footer class="footer"><p>&copy; 2026 Admin - Campus Event Management System</p></footer>
<script src="../assets/js/script.js?v=3"></script>
</body>
</html>
