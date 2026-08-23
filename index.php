<?php
session_start();
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
include 'includes/config.php';
include 'includes/header.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// One grouped query: events + live registration count (no queries in a loop).
$sql = "SELECT e.*, COUNT(r.id) AS registered_count
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id
        WHERE e.event_date >= CURDATE()";
if ($search !== "") {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.category LIKE ?)";
}
$sql .= " GROUP BY e.id ORDER BY e.event_date ASC LIMIT 6";

$result = false;
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if ($search !== "") {
        $like = "%" . $search . "%";
        mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
    }
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
    }
}

include_once 'includes/event_helpers.php';

$events_html = "";
if ($result && mysqli_num_rows($result) > 0) {
    while ($ev = mysqli_fetch_assoc($result)) {
        $img = $ev['image'] ? "uploads/" . $ev['image'] : "assets/images/default-event.jpg";
        $events_html .= '<div class="event-card"><img src="' . $img . '" alt="' . htmlspecialchars($ev['title']) . '" class="event-card-img"><div class="event-card-body"><h3>' . htmlspecialchars($ev['title']) . '</h3><p class="event-date">' . date("d M Y", strtotime($ev['event_date'])) . ' at ' . date("h:i A", strtotime($ev['event_time'])) . '</p><p><strong>Venue:</strong> ' . htmlspecialchars($ev['venue']) . '</p><p><strong>Category:</strong> ' . htmlspecialchars($ev['category']) . '</p>'
            . event_capacity_html(isset($ev['registered_count']) ? $ev['registered_count'] : 0, isset($ev['capacity']) ? $ev['capacity'] : 0)
            . '<a href="dashboard.php?view=' . (int) $ev['id'] . '" class="btn btn-small">View Details</a></div></div>';
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
        <input type="text" name="search" placeholder="Search events by name or category..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</section>

<section class="events-section">
    <h2>Upcoming Events</h2>
    <div class="event-grid"><?= $events_html ?></div>
</section>

<?php include 'includes/footer.php'; ?>