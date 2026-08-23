<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['view'])) {
    $event_id = (int) $_GET['view'];

    // Fetch the event together with its live registration count in ONE query.
    $event = null;
    $stmt = mysqli_prepare($conn, "SELECT e.*, COUNT(r.id) AS registered_count FROM events e LEFT JOIN registrations r ON r.event_id = e.id WHERE e.id = ? GROUP BY e.id");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $event = $res ? mysqli_fetch_assoc($res) : null;
        }
        mysqli_stmt_close($stmt);
    }
    if (!$event) {
        // Event does not exist -> back to dashboard (existing behaviour).
        header("Location: dashboard.php");
        exit();
    }

    include_once 'includes/event_helpers.php';

    $already_registered = false;
    $check = mysqli_prepare($conn, "SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
    if ($check) {
        mysqli_stmt_bind_param($check, "ii", $user_id, $event_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        $already_registered = mysqli_stmt_num_rows($check) > 0;
        mysqli_stmt_close($check);
    }

    $reg_status = "";   // "" | "success" | "full" | "error"

    if (isset($_POST['register_event']) && !$already_registered) {

        // ---- Safe against overbooking / simultaneous requests -------------
        // A transaction is opened and the event row is locked with
        // SELECT ... FOR UPDATE. Every other registration attempt for the
        // SAME event has to wait for that lock until we commit or roll
        // back, so two users can never take the same last seat.
        if (mysqli_begin_transaction($conn)) {
            $committed = false;

            $lock = mysqli_prepare($conn, "SELECT capacity FROM events WHERE id = ? FOR UPDATE");
            if ($lock) {
                mysqli_stmt_bind_param($lock, "i", $event_id);
                if (mysqli_stmt_execute($lock)) {
                    mysqli_stmt_store_result($lock);
                    if (mysqli_stmt_num_rows($lock) === 1) {
                        mysqli_stmt_bind_result($lock, $db_capacity);
                        mysqli_stmt_fetch($lock);
                        $capacity = max(0, (int) $db_capacity);

                        // Count current registrations while holding the lock.
                        $count_ok  = false;
                        $reg_count = 0;
                        $cnt = mysqli_prepare($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ?");
                        if ($cnt) {
                            mysqli_stmt_bind_param($cnt, "i", $event_id);
                            if (mysqli_stmt_execute($cnt)) {
                                mysqli_stmt_bind_result($cnt, $reg_count);
                                mysqli_stmt_fetch($cnt);
                                $count_ok = true;
                            }
                            mysqli_stmt_close($cnt);
                        }

                        if (!$count_ok) {
                            // Count failed -> nothing is inserted.
                        } elseif ($capacity > 0 && $reg_count >= $capacity) {
                            // Capacity reached -> no insert at all.
                            $reg_status = "full";
                            $committed  = true;   // nothing changed; just release the lock
                        } else {
                            $ins = mysqli_prepare($conn, "INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
                            if ($ins) {
                                mysqli_stmt_bind_param($ins, "ii", $user_id, $event_id);
                                if (mysqli_stmt_execute($ins)) {
                                    $reg_status = "success";
                                    $already_registered = true;
                                    $committed = true;
                                } elseif (mysqli_stmt_errno($ins) == 1062) {
                                    // Unique key (user_id, event_id) hit:
                                    // duplicate form submission / double click.
                                    $already_registered = true;
                                    $committed = true;
                                }
                                mysqli_stmt_close($ins);
                            }
                        }
                    }
                }
                mysqli_stmt_close($lock);
            }

            if ($committed) {
                mysqli_commit($conn);
            } else {
                // Any failure (query error, missing row...) rolls everything back.
                mysqli_rollback($conn);
                if ($reg_status == "") $reg_status = "error";
            }
        } else {
            $reg_status = "error";
        }

        // Always refresh the live count so the page shows real numbers.
        $cnt2 = mysqli_prepare($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ?");
        if ($cnt2) {
            mysqli_stmt_bind_param($cnt2, "i", $event_id);
            if (mysqli_stmt_execute($cnt2)) {
                mysqli_stmt_bind_result($cnt2, $fresh_count);
                if (mysqli_stmt_fetch($cnt2)) {
                    $event['registered_count'] = $fresh_count;
                }
            }
            mysqli_stmt_close($cnt2);
        }
    }

    $seats   = seat_data(isset($event['registered_count']) ? $event['registered_count'] : 0, isset($event['capacity']) ? $event['capacity'] : 0);
    $is_full = !$seats['unlimited'] && $seats['available'] === 0;

    $img = $event['image'] ? "uploads/" . $event['image'] : "";
    include 'includes/header.php';

    $reg_section = "";
    if ($reg_status == "success") $reg_section .= '<div class="alert alert-success">You have successfully registered for this event!</div>';
    elseif ($reg_status == "full") $reg_section .= '<div class="alert alert-error">This event is currently full. Registration is closed.</div>';
    elseif ($reg_status == "error") $reg_section .= '<div class="alert alert-error">Something went wrong.</div>';
    if ($already_registered) {
        $reg_section .= '<div class="alert alert-info">You are already registered for this event.</div>';
    } elseif ($is_full) {
        $reg_section .= '<button type="button" class="btn btn-primary btn-disabled" disabled>Event Full</button>';
    } else {
        $reg_section = '<form method="POST"><button type="submit" name="register_event" class="btn btn-primary">Register for this Event</button></form>' . $reg_section;
    }

    // Seat information component for the details page.
    if ($seats['unlimited']) {
        $seat_component = '<div class="seat-info status-available">'
            . '<div class="seat-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="36" height="36"><path d="M5 11V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4"/><path d="M5 11a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2"/><path d="M6 18v2"/><path d="M18 18v2"/></svg></div>'
            . '<div class="seat-details">'
            . '<div class="seat-headline"><p class="seat-filled">' . $seats['registered'] . ' students registered</p>'
            . '<span class="status-badge ' . $seats['class'] . '">' . $seats['label'] . '</span></div>'
            . '<p class="seat-remaining">Open registration &ndash; no seat limit</p>'
            . '</div></div>';
    } else {
        $seat_component = '<div class="seat-info ' . $seats['class'] . '">'
            . '<div class="seat-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="36" height="36"><path d="M5 11V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4"/><path d="M5 11a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2"/><path d="M6 18v2"/><path d="M18 18v2"/></svg></div>'
            . '<div class="seat-details">'
            . '<div class="seat-headline"><p class="seat-filled"><strong>' . $seats['registered'] . ' of ' . $seats['capacity'] . '</strong> seats filled</p>'
            . '<span class="status-badge ' . $seats['class'] . '">' . $seats['label'] . '</span></div>'
            . '<p class="seat-remaining">' . $seats['available'] . ' seats remaining</p>'
            . '<div class="progress-bar progress-lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $seats['percent'] . '" aria-label="' . $seats['percent'] . '% of seats filled">'
            . '<div class="progress-fill" style="width:' . $seats['percent'] . '%"></div>'
            . '</div>'
            . '<p class="seat-percent">' . $seats['percent'] . '% full</p>'
            . '</div></div>';
    }
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
        <?= $seat_component ?>
        <p><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>
        <div class="event-register-section"><?= $reg_section ?></div>
    </div>

    <?php include 'includes/footer.php';
    exit();
}

include 'includes/header.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// One grouped query: events + live registration count (no queries in a loop).
$events_sql = "SELECT e.*, COUNT(r.id) AS registered_count
               FROM events e
               LEFT JOIN registrations r ON r.event_id = e.id
               WHERE e.event_date >= CURDATE()";
if ($search !== "") {
    $events_sql .= " AND (e.title LIKE ? OR e.category LIKE ?)";
}
$events_sql .= " GROUP BY e.id ORDER BY e.event_date ASC";

$events_result = false;
$events_stmt = mysqli_prepare($conn, $events_sql);
if ($events_stmt) {
    if ($search !== "") {
        $like = "%" . $search . "%";
        mysqli_stmt_bind_param($events_stmt, "ss", $like, $like);
    }
    if (mysqli_stmt_execute($events_stmt)) {
        $events_result = mysqli_stmt_get_result($events_stmt);
    }
}

include_once 'includes/event_helpers.php';

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
if ($events_result && mysqli_num_rows($events_result) > 0) {
    while ($ev = mysqli_fetch_assoc($events_result)) {
        $img = $ev['image'] ? "uploads/" . $ev['image'] : "assets/images/default-event.jpg";
        $events_html .= '<div class="event-card"><img src="' . $img . '" alt="' . htmlspecialchars($ev['title']) . '" class="event-card-img"><div class="event-card-body"><h3>' . htmlspecialchars($ev['title']) . '</h3><p class="event-date">' . date("d M Y", strtotime($ev['event_date'])) . ' at ' . date("h:i A", strtotime($ev['event_time'])) . '</p><p><strong>Venue:</strong> ' . htmlspecialchars($ev['venue']) . '</p><p><strong>Category:</strong> ' . htmlspecialchars($ev['category']) . '</p>'
            . event_capacity_html(isset($ev['registered_count']) ? $ev['registered_count'] : 0, isset($ev['capacity']) ? $ev['capacity'] : 0)
            . '<a href="dashboard.php?view=' . (int) $ev['id'] . '" class="btn btn-small">View &amp; Register</a></div></div>';
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
                <input type="text" name="search" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
        <div class="event-grid"><?= $events_html ?></div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>