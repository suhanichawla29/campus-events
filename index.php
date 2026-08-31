<?php
session_start();
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
include 'includes/config.php';
include 'includes/header.php';
include_once 'includes/event_helpers.php';

$search = "";
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$query = "SELECT e.*, COUNT(r.id) AS registered_count
          FROM events e
          LEFT JOIN registrations r ON r.event_id = e.id
          WHERE e.event_date >= CURDATE()";

if ($search != "") {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.category LIKE ?)";
}

$query .= " GROUP BY e.id ORDER BY e.event_date ASC LIMIT 6";

$stmt = mysqli_prepare($conn, $query);
if ($search != "") {
    $like = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
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
    <div class="event-grid">

        <?php if ($result && mysqli_num_rows($result) > 0) { ?>
            <?php while ($event = mysqli_fetch_assoc($result)) { ?>
                <?= event_card($event, "View Details") ?>
            <?php } ?>
        <?php } else { ?>
            <p class="no-events">No upcoming events at the moment. Check back later!</p>
        <?php } ?>

    </div>
</section>

<?php include 'includes/footer.php'; ?>
