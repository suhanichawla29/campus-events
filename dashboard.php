<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Event Detail View ---
if (isset($_GET['view'])) {
    $event_id = mysqli_real_escape_string($conn, $_GET['view']);
    $result = mysqli_query($conn, "SELECT * FROM events WHERE id = '$event_id'");
    if (mysqli_num_rows($result) == 0) {
        header("Location: dashboard.php");
        exit();
    }
    $event = mysqli_fetch_assoc($result);
    $already_registered = false;
    $reg_status = "";
    $check = mysqli_query($conn, "SELECT id FROM registrations WHERE user_id = '$user_id' AND event_id = '$event_id'");
    if (mysqli_num_rows($check) > 0) $already_registered = true;
    if (isset($_POST['register_event']) && !$already_registered) {
        if (mysqli_query($conn, "INSERT INTO registrations (user_id, event_id) VALUES ('$user_id', '$event_id')")) {
            $reg_status = "success";
            $already_registered = true;
        } else {
            $reg_status = "error";
        }
    }
    $img = $event['image'] ? "uploads/" . $event['image'] : "";
    include 'includes/header.php';

    $reg_section = "";
    if ($reg_status == "success") $reg_section = '<div class="alert alert-success">You have successfully registered for this event!</div>';
    elseif ($reg_status == "error") $reg_section = '<div class="alert alert-error">Something went wrong.</div>';
    if ($already_registered) $reg_section .= '<div class="alert alert-info">You are already registered for this event.</div>';
    if (!$already_registered) $reg_section = '<form method="POST"><button type="submit" name="register_event" class="btn btn-primary">Register for this Event</button></form>';
    ?>

    <div class="event-detail">
        <?php if ($img): ?><img src="<?= $img ?>" alt="<?= $event['title'] ?>" class="event-detail-img"><?php endif; ?>
        <h1><?= $event['title'] ?></h1>
        <div class="event-info">
            <p><strong>Date:</strong> <?= date("d M Y", strtotime($event['event_date'])) ?></p>
            <p><strong>Time:</strong> <?= date("h:i A", strtotime($event['event_time'])) ?></p>
            <p><strong>Venue:</strong> <?= $event['venue'] ?></p>
            <p><strong>Category:</strong> <?= $event['category'] ?></p>
            <p><strong>Organizer:</strong> <?= $event['organizer'] ?></p>
        </div>
        <div class="event-description">
            <h3>About this Event</h3>
            <p><?= nl2br($event['description']) ?></p>
        </div>
        <p><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>
        <div class="event-register-section"><?= $reg_section ?></div>
    </div>

    <?php include 'includes/footer.php';
    exit();
}

// --- Dashboard View ---
include 'includes/header.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";
$events_query = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC";
if ($search) $events_query = "SELECT * FROM events WHERE event_date >= CURDATE() AND (title LIKE '%$search%' OR category LIKE '%$search%') ORDER BY event_date ASC";
$events_result = mysqli_query($conn, $events_query);

$registered_result = mysqli_query($conn, "SELECT e.*, r.registered_at FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.user_id = '$user_id' ORDER BY e.event_date ASC");

$reg_table = "";
if (mysqli_num_rows($registered_result) > 0) {
    $reg_table = '<table class="table"><tr><th>Event</th><th>Date</th><th>Venue</th><th>Registered On</th></tr>';
    while ($re = mysqli_fetch_assoc($registered_result)) {
        $reg_table .= '<tr><td><a href="dashboard.php?view=' . $re['id'] . '">' . $re['title'] . '</a></td><td>' . date("d M Y", strtotime($re['event_date'])) . '</td><td>' . $re['venue'] . '</td><td>' . date("d M Y", strtotime($re['registered_at'])) . '</td></tr>';
    }
    $reg_table .= '</table>';
} else {
    $reg_table = '<p class="no-events">You haven\'t registered for any events yet.</p>';
}

$events_html = "";
if (mysqli_num_rows($events_result) > 0) {
    while ($ev = mysqli_fetch_assoc($events_result)) {
        $img = $ev['image'] ? "uploads/" . $ev['image'] : "assets/images/default-event.jpg";
        $events_html .= '<div class="event-card"><img src="' . $img . '" alt="' . $ev['title'] . '" class="event-card-img"><div class="event-card-body"><h3>' . $ev['title'] . '</h3><p class="event-date">' . date("d M Y", strtotime($ev['event_date'])) . ' at ' . date("h:i A", strtotime($ev['event_time'])) . '</p><p><strong>Venue:</strong> ' . $ev['venue'] . '</p><p><strong>Category:</strong> ' . $ev['category'] . '</p><a href="dashboard.php?view=' . $ev['id'] . '" class="btn btn-small">View &amp; Register</a></div></div>';
    }
} else {
    $events_html = '<p class="no-events">No events found.</p>';
}
?>

<div class="dashboard">
    <h2>Welcome, <?= $_SESSION['user_name'] ?>!</h2>
    <section class="dashboard-section">
        <h3>My Registered Events</h3>
        <?= $reg_table ?>
    </section>
    <section class="dashboard-section">
        <h3>Browse & Register for Events</h3>
        <div class="search-filter">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Search events..." value="<?= $search ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
        <div class="event-grid"><?= $events_html ?></div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
