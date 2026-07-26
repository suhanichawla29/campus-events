<?php
session_start();
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
include 'includes/config.php';
include 'includes/header.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

$query = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6";
if ($search) {
    $query = "SELECT * FROM events WHERE event_date >= CURDATE() AND (title LIKE '%$search%' OR description LIKE '%$search%' OR category LIKE '%$search%') ORDER BY event_date ASC LIMIT 6";
}
$result = mysqli_query($conn, $query);

$events_html = "";
if (mysqli_num_rows($result) > 0) {
    while ($ev = mysqli_fetch_assoc($result)) {
        $img = $ev['image'] ? "uploads/" . $ev['image'] : "assets/images/default-event.jpg";
        $events_html .= '<div class="event-card"><img src="' . $img . '" alt="' . $ev['title'] . '" class="event-card-img"><div class="event-card-body"><h3>' . $ev['title'] . '</h3><p class="event-date">' . date("d M Y", strtotime($ev['event_date'])) . ' at ' . date("h:i A", strtotime($ev['event_time'])) . '</p><p><strong>Venue:</strong> ' . $ev['venue'] . '</p><p><strong>Category:</strong> ' . $ev['category'] . '</p><a href="dashboard.php?view=' . $ev['id'] . '" class="btn btn-small">View Details</a></div></div>';
    }
} else {
    $events_html = '<p class="no-events">No upcoming events at the moment. Check back later!</p>';
}
?>

<section class="hero">
    <div class="hero-content">
        <h1>Welcome to Campus Event Management System</h1>
        <p>Stay updated with all the exciting events happening on campus. Register, participate, and make the most of your college life!</p>
        <a href="#events" class="btn btn-primary">Browse Events</a>
    </div>
</section>

<section class="about-section">
    <h2>About Campus Events</h2>
    <p>Campus events bring students together for learning, fun, and networking. From technical workshops to cultural fests, our platform helps you discover and register for events that match your interests.</p>
</section>

<section class="search-filter" id="events">
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Search events by name or category..." value="<?= $search ?>">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</section>

<section class="events-section">
    <h2>Upcoming Events</h2>
    <div class="event-grid"><?= $events_html ?></div>
</section>

<?php include 'includes/footer.php'; ?>