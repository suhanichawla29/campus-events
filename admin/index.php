<?php
session_start();
include '../includes/config.php';

$error = "";
$msg_text = "";

// Handle login
if (!isset($_SESSION['admin_id']) && $_SERVER["REQUEST_METHOD"] == "POST") {
    $u = mysqli_real_escape_string($conn, $_POST['username']);
    $p = $_POST['password'];
    $r = mysqli_query($conn, "SELECT * FROM admins WHERE username = '$u' AND password = MD5('$p')");
    if (mysqli_num_rows($r) == 1) {
        $a = mysqli_fetch_assoc($r);
        $_SESSION['admin_id'] = $a['id'];
        $_SESSION['admin_username'] = $a['username'];
    } else {
        $error = "Invalid admin username or password!";
    }
}

// If not logged in, show login page
if (!isset($_SESSION['admin_id'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Login - Campus Events</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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

// --- Admin Dashboard ---

// Handle add
if (isset($_POST['add_event'])) {
    $t = mysqli_real_escape_string($conn, $_POST['title']);
    $d = mysqli_real_escape_string($conn, $_POST['description']);
    $ed = mysqli_real_escape_string($conn, $_POST['event_date']);
    $et = mysqli_real_escape_string($conn, $_POST['event_time']);
    $v = mysqli_real_escape_string($conn, $_POST['venue']);
    $c = mysqli_real_escape_string($conn, $_POST['category']);
    $o = mysqli_real_escape_string($conn, $_POST['organizer']);
    $img = "";
    if (isset($_FILES['image']['name']) && $_FILES['image']['name']) {
        $img = time() . "_" . $_FILES['image']['name'];
        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg','jpeg','png','gif'))) {
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $img);
        } else { $img = ""; }
    }
    if (mysqli_query($conn, "INSERT INTO events (title, description, event_date, event_time, venue, category, organizer, image) VALUES ('$t','$d','$ed','$et','$v','$c','$o','$img')")) $msg_text = "added";
}

// Handle edit
if (isset($_POST['edit_event'])) {
    $eid = mysqli_real_escape_string($conn, $_POST['event_id']);
    $t = mysqli_real_escape_string($conn, $_POST['title']);
    $d = mysqli_real_escape_string($conn, $_POST['description']);
    $ed = mysqli_real_escape_string($conn, $_POST['event_date']);
    $et = mysqli_real_escape_string($conn, $_POST['event_time']);
    $v = mysqli_real_escape_string($conn, $_POST['venue']);
    $c = mysqli_real_escape_string($conn, $_POST['category']);
    $o = mysqli_real_escape_string($conn, $_POST['organizer']);
    $img_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM events WHERE id='$eid'"));
    $img = $img_row['image'];
    if (isset($_FILES['image']['name']) && $_FILES['image']['name']) {
        $img = time() . "_" . $_FILES['image']['name'];
        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg','jpeg','png','gif'))) {
            if ($img_row['image'] && file_exists("../uploads/" . $img_row['image'])) unlink("../uploads/" . $img_row['image']);
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $img);
        } else { $img = $img_row['image']; }
    }
    if (mysqli_query($conn, "UPDATE events SET title='$t',description='$d',event_date='$ed',event_time='$et',venue='$v',category='$c',organizer='$o',image='$img' WHERE id='$eid'")) $msg_text = "updated";
}

// Handle delete
if (isset($_GET['delete'])) {
    $did = mysqli_real_escape_string($conn, $_GET['delete']);
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM events WHERE id='$did'"));
    if ($r['image'] && file_exists("../uploads/" . $r['image'])) unlink("../uploads/" . $r['image']);
    mysqli_query($conn, "DELETE FROM events WHERE id='$did'");
    header("Location: index.php?msg=deleted");
    exit();
}

$events_result = mysqli_query($conn, "SELECT * FROM events ORDER BY event_date ASC");
$total_events = mysqli_num_rows($events_result);
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_regs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM registrations"))['c'];

$edit_event = null;
if (isset($_GET['edit'])) {
    $eid = mysqli_real_escape_string($conn, $_GET['edit']);
    $edit_event = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id='$eid'"));
}

if (isset($_GET['msg'])) $msg_text = $_GET['msg'];
$msg_map = array('added'=>'Event added successfully!','updated'=>'Event updated successfully!','deleted'=>'Event deleted successfully!');

$table_rows = "";
while ($ev = mysqli_fetch_assoc($events_result)) {
    $table_rows .= '<tr><td>' . $ev['id'] . '</td><td>' . $ev['title'] . '</td><td>' . date("d M Y",strtotime($ev['event_date'])) . '</td><td>' . date("h:i A",strtotime($ev['event_time'])) . '</td><td>' . $ev['venue'] . '</td><td>' . $ev['category'] . '</td><td class="actions"><a href="index.php?edit=' . $ev['id'] . '" class="btn btn-small btn-edit">Edit</a> <a href="index.php?delete=' . $ev['id'] . '" class="btn btn-small btn-delete" onclick="return confirm(\'Are you sure?\')">Delete</a></td></tr>';
}
$cats = array('Technical','Cultural','Sports','Workshop','Seminar','Other');
function cat_opts($cats,$s="") { $h='<option value="">Select Category</option>'; foreach($cats as $c) { $h.='<option value="'.$c.'"'.($c==$s?' selected':'').'>'.$c.'</option>'; } return $h; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Panel - Campus Events</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
    <?php if ($msg_text && isset($msg_map[$msg_text])): ?><div class="alert alert-success"><?= $msg_map[$msg_text] ?></div><?php endif; ?>
    <div class="stats">
        <div class="stat-card"><h3><?= $total_events ?></h3><p>Total Events</p></div>
        <div class="stat-card"><h3><?= $total_users ?></h3><p>Registered Students</p></div>
        <div class="stat-card"><h3><?= $total_regs ?></h3><p>Total Registrations</p></div>
    </div>
    <?php if (isset($_GET['add'])): ?>
    <div class="admin-form" style="margin-bottom:25px;">
        <h3>Add New Event</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group"><label>Event Title</label><input type="text" name="title" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            <div class="form-row"><div class="form-group"><label>Date</label><input type="date" name="event_date" required></div><div class="form-group"><label>Time</label><input type="time" name="event_time"></div></div>
            <div class="form-group"><label>Venue</label><input type="text" name="venue"></div>
            <div class="form-group"><label>Category</label><select name="category"><?= cat_opts($cats) ?></select></div>
            <div class="form-group"><label>Organizer</label><input type="text" name="organizer"></div>
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
            <div class="form-group"><label>Category</label><select name="category"><?= cat_opts($cats, $edit_event['category']) ?></select></div>
            <div class="form-group"><label>Organizer</label><input type="text" name="organizer" value="<?= $edit_event['organizer'] ?>"></div>
            <div class="form-group"><label>Event Image</label><input type="file" name="image"><?php if ($edit_event['image']): ?><p class="current-image">Current: <?= $edit_event['image'] ?></p><?php endif; ?></div>
            <button type="submit" name="edit_event" class="btn btn-primary">Update Event</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <?php endif; ?>
    <table class="table admin-table">
        <tr><th>ID</th><th>Title</th><th>Date</th><th>Time</th><th>Venue</th><th>Category</th><th>Actions</th></tr>
        <?= $table_rows ?>
    </table>
</div>
</div>
<footer class="footer"><p>&copy; 2026 Admin - Campus Event Management System</p></footer>
<script src="../assets/js/script.js"></script>
</body>
</html>
