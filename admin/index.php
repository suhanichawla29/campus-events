<?php
session_start();
include '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

if ($action == 'delete' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM events WHERE id = '$id'"));
    if ($img['image'] && file_exists("../uploads/" . $img['image'])) { unlink("../uploads/" . $img['image']); }
    mysqli_query($conn, "DELETE FROM events WHERE id = '$id'");
    header("Location: index.php?msg=deleted");
    exit();
}

if ($action == 'add' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
    $event_time = mysqli_real_escape_string($conn, $_POST['event_time']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
    $image_name = "";
    if (isset($_FILES['image']['name']) && $_FILES['image']['name'] != "") {
        $image_name = time() . "_" . $_FILES['image']['name'];
        $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $image_name);
        } else { $add_error = "Only JPG, JPEG, PNG, and GIF files are allowed."; $image_name = ""; }
    }
    if (!isset($add_error)) {
        if (mysqli_query($conn, "INSERT INTO events (title, description, event_date, event_time, venue, category, organizer, image) VALUES ('$title', '$description', '$event_date', '$event_time', '$venue', '$category', '$organizer', '$image_name')")) {
            header("Location: index.php?msg=added");
            exit();
        } else { $add_error = "Something went wrong: " . mysqli_error($conn); }
    }
}

if ($action == 'edit' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['id'])) {
    $eid = mysqli_real_escape_string($conn, $_GET['id']);
    $ev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id = '$eid'"));
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
    $event_time = mysqli_real_escape_string($conn, $_POST['event_time']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
    $image_name = $ev['image'];
    if (isset($_FILES['image']['name']) && $_FILES['image']['name'] != "") {
        $new_name = time() . "_" . $_FILES['image']['name'];
        $ext = strtolower(pathinfo($new_name, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
            if ($ev['image'] && file_exists("../uploads/" . $ev['image'])) { unlink("../uploads/" . $ev['image']); }
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $new_name);
            $image_name = $new_name;
        } else { $edit_error = "Only JPG, JPEG, PNG, and GIF files are allowed."; }
    }
    if (!isset($edit_error)) {
        if (mysqli_query($conn, "UPDATE events SET title='$title', description='$description', event_date='$event_date', event_time='$event_time', venue='$venue', category='$category', organizer='$organizer', image='$image_name' WHERE id='$eid'")) {
            header("Location: index.php?msg=updated");
            exit();
        } else { $edit_error = "Something went wrong: " . mysqli_error($conn); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Campus Events</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<nav class="navbar admin-nav">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">Admin Panel</a>
        <ul class="nav-menu">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="index.php?action=add">Add Event</a></li>
            <li><a href="index.php?action=logout">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container">

<?php if ($action == 'add'): ?>

<div class="admin-dashboard">
    <h2>Add New Event</h2>
    <?php if (isset($add_error)): ?>
        <div class="alert alert-error"><?php echo $add_error; ?></div>
    <?php endif; ?>
    <form method="POST" action="index.php?action=add" enctype="multipart/form-data" class="admin-form">
        <div class="form-group">
            <label>Event Title</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="event_date" required>
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" name="event_time">
            </div>
        </div>
        <div class="form-group">
            <label>Venue</label>
            <input type="text" name="venue">
        </div>
        <div class="form-group">
            <label>Category</label>
            <select name="category">
                <option value="">Select Category</option>
                <option value="Technical">Technical</option>
                <option value="Cultural">Cultural</option>
                <option value="Sports">Sports</option>
                <option value="Workshop">Workshop</option>
                <option value="Seminar">Seminar</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label>Organizer Name</label>
            <input type="text" name="organizer">
        </div>
        <div class="form-group">
            <label>Event Image</label>
            <input type="file" name="image">
        </div>
        <button type="submit" class="btn btn-primary">Add Event</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php elseif ($action == 'edit' && isset($_GET['id'])): ?>

<?php
$eid = mysqli_real_escape_string($conn, $_GET['id']);
$ev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id = '$eid'"));
if (!$ev) { header("Location: index.php"); exit(); }
?>

<div class="admin-dashboard">
    <h2>Edit Event</h2>
    <?php if (isset($edit_error)): ?>
        <div class="alert alert-error"><?php echo $edit_error; ?></div>
    <?php endif; ?>
    <form method="POST" action="index.php?action=edit&id=<?php echo $eid; ?>" enctype="multipart/form-data" class="admin-form">
        <div class="form-group">
            <label>Event Title</label>
            <input type="text" name="title" value="<?php echo $ev['title']; ?>" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"><?php echo $ev['description']; ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="event_date" value="<?php echo $ev['event_date']; ?>" required>
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" name="event_time" value="<?php echo $ev['event_time']; ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Venue</label>
            <input type="text" name="venue" value="<?php echo $ev['venue']; ?>">
        </div>
        <div class="form-group">
            <label>Category</label>
            <select name="category">
                <option value="">Select Category</option>
                <?php foreach (array('Technical', 'Cultural', 'Sports', 'Workshop', 'Seminar', 'Other') as $c): ?>
                    <option value="<?php echo $c; ?>" <?php echo ($ev['category'] == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Organizer Name</label>
            <input type="text" name="organizer" value="<?php echo $ev['organizer']; ?>">
        </div>
        <div class="form-group">
            <label>Event Image</label>
            <input type="file" name="image">
            <?php if ($ev['image']): ?>
                <p class="current-image">Current image: <?php echo $ev['image']; ?></p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Update Event</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php else: ?>

<div class="admin-dashboard">
    <h2>Admin Dashboard</h2>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php if ($_GET['msg'] == 'added'): ?>Event added successfully!
            <?php elseif ($_GET['msg'] == 'updated'): ?>Event updated successfully!
            <?php elseif ($_GET['msg'] == 'deleted'): ?>Event deleted successfully!<?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    $events_result = mysqli_query($conn, "SELECT * FROM events ORDER BY event_date ASC");
    $total_events = mysqli_num_rows($events_result);
    $counts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT (SELECT COUNT(*) FROM users) as users, (SELECT COUNT(*) FROM registrations) as regs"));
    $total_users = $counts['users'];
    $total_regs = $counts['regs'];
    ?>

    <div class="stats">
        <div class="stat-card"><h3><?php echo $total_events; ?></h3><p>Total Events</p></div>
        <div class="stat-card"><h3><?php echo $total_users; ?></h3><p>Registered Students</p></div>
        <div class="stat-card"><h3><?php echo $total_regs; ?></h3><p>Total Registrations</p></div>
    </div>

    <a href="index.php?action=add" class="btn btn-primary">+ Add New Event</a>

    <table class="table admin-table">
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Date</th>
            <th>Time</th>
            <th>Venue</th>
            <th>Category</th>
            <th>Actions</th>
        </tr>
        <?php while ($event = mysqli_fetch_assoc($events_result)): ?>
            <tr>
                <td><?php echo $event['id']; ?></td>
                <td><?php echo $event['title']; ?></td>
                <td><?php echo date("d M Y", strtotime($event['event_date'])); ?></td>
                <td><?php echo date("h:i A", strtotime($event['event_time'])); ?></td>
                <td><?php echo $event['venue']; ?></td>
                <td><?php echo $event['category']; ?></td>
                <td class="actions">
                    <a href="index.php?action=edit&id=<?php echo $event['id']; ?>" class="btn btn-small btn-edit">Edit</a>
                    <a href="index.php?action=delete&id=<?php echo $event['id']; ?>" class="btn btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this event?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php endif; ?>

</div>
<footer class="footer">
    <p>&copy; 2026 Admin - Campus Event Management System</p>
</footer>
</body>
</html>

       
