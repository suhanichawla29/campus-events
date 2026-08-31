<?php
// Start the session and check that the student is logged in
session_start();
include 'includes/config.php';
include_once 'includes/event_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

$user_id = $_SESSION['user_id'];

// Keep any success or error message from the previous action
$_SESSION['flash'] = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();

// Cancel the student's registration (sent from the confirmation modal)
if (isset($_POST['cancel_registration'])) {
    $result = "error";

    // Only continue if the hidden CSRF token is correct
    if (check_csrf()) {
        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

        if ($event_id > 0) {
            // Find the event the student wants to cancel
            $stmt = mysqli_prepare($conn, "SELECT id, title, event_date, event_time FROM events WHERE id = ?");
            $event = null;
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $event_id);
                if (mysqli_stmt_execute($stmt)) {
                    $event_result = mysqli_stmt_get_result($stmt);
                    $event = $event_result ? mysqli_fetch_assoc($event_result) : null;
                }
                mysqli_stmt_close($stmt);
            }

            if (!$event) {
                $result = "invalid";
            } else {
                // Check whether the event has already started
                $now = time();
                $start = strtotime($event['event_date'] . " " . ($event['event_time'] ? $event['event_time'] : "00:00:00"));
                $started = $start > 0 && $now >= $start;

                // Check that the student is registered for this event
                $owns_registration = false;
                $check_registration = mysqli_prepare($conn, "SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
                if ($check_registration) {
                    mysqli_stmt_bind_param($check_registration, "ii", $user_id, $event_id);
                    mysqli_stmt_execute($check_registration);
                    mysqli_stmt_store_result($check_registration);
                    $owns_registration = mysqli_stmt_num_rows($check_registration) > 0;
                    mysqli_stmt_close($check_registration);
                }

                if (!$owns_registration) {
                    $result = "not_registered";
                } else if ($started) {
                    $result = "closed";
                } else {
                    // Delete only this student's registration for this event
                    $delete_registration = mysqli_prepare($conn, "DELETE FROM registrations WHERE user_id = ? AND event_id = ?");
                    if ($delete_registration) {
                        mysqli_stmt_bind_param($delete_registration, "ii", $user_id, $event_id);
                        if (mysqli_stmt_execute($delete_registration) && mysqli_stmt_affected_rows($delete_registration) > 0) {
                            $result = "success";
                            $_SESSION['flash']['title'] = $event['title'];
                        }
                        mysqli_stmt_close($delete_registration);
                    }
                }
            }
        }
    }

    $_SESSION['flash']['type'] = $result;

    // Redirect after the POST so the page cannot be refreshed twice
    header("Location: dashboard.php");
    exit();
}

// Show the full details of one event
if (isset($_GET['view'])) {
    $event_id = (int)$_GET['view'];

    // Find the event and count how many students have registered for it
    $stmt = mysqli_prepare($conn, "SELECT e.*, COUNT(r.id) AS registered_count
                                   FROM events e
                                   LEFT JOIN registrations r ON r.event_id = e.id
                                   WHERE e.id = ?
                                   GROUP BY e.id");
    $event = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        if (mysqli_stmt_execute($stmt)) {
            $event_result = mysqli_stmt_get_result($stmt);
            $event = $event_result ? mysqli_fetch_assoc($event_result) : null;
        }
        mysqli_stmt_close($stmt);
    }

    // If the event does not exist, go back to the dashboard
    if (!$event) {
        header("Location: dashboard.php");
        exit();
    }

    // Check whether this student is already registered for the event
    $already_registered = false;
    $check_registration = mysqli_prepare($conn, "SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
    if ($check_registration) {
        mysqli_stmt_bind_param($check_registration, "ii", $user_id, $event_id);
        mysqli_stmt_execute($check_registration);
        mysqli_stmt_store_result($check_registration);
        if (mysqli_stmt_num_rows($check_registration) > 0) {
            $already_registered = true;
        }
        mysqli_stmt_close($check_registration);
    }

    $message = "";

    // Register this student for the event (sent from the Register button)
    if (isset($_POST['register_event']) && !$already_registered) {
        $done = false;

        // Lock the event row so two students cannot take the last seat at once
        mysqli_begin_transaction($conn);

        $lock_statement = mysqli_prepare($conn, "SELECT capacity FROM events WHERE id = ? FOR UPDATE");
        if ($lock_statement) {
            mysqli_stmt_bind_param($lock_statement, "i", $event_id);
            mysqli_stmt_execute($lock_statement);
            mysqli_stmt_store_result($lock_statement);

            if (mysqli_stmt_num_rows($lock_statement) == 1) {
                mysqli_stmt_bind_result($lock_statement, $db_capacity);
                mysqli_stmt_fetch($lock_statement);
                $capacity = max(0, (int)$db_capacity);

                $current_registrations = 0;
                $count_ok = false;

                // Count how many students have already registered
                $count_statement = mysqli_prepare($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ?");
                if ($count_statement) {
                    mysqli_stmt_bind_param($count_statement, "i", $event_id);
                    if (mysqli_stmt_execute($count_statement)) {
                        mysqli_stmt_bind_result($count_statement, $current_registrations);
                        mysqli_stmt_fetch($count_statement);
                        $count_ok = true;
                    }
                    mysqli_stmt_close($count_statement);
                }

                if (!$count_ok) {
                    $done = false;
                } else if ($capacity > 0 && $current_registrations >= $capacity) {
                    // The event is full, so registration is closed
                    $message = "full";
                    $done = true;
                } else {
                    // Save the new registration
                    $insert_statement = mysqli_prepare($conn, "INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
                    if ($insert_statement) {
                        mysqli_stmt_bind_param($insert_statement, "ii", $user_id, $event_id);

                        if (mysqli_stmt_execute($insert_statement)) {
                            $message = "success";
                            $already_registered = true;
                            $done = true;
                        } else if (mysqli_stmt_errno($insert_statement) == 1062) {
                            // The same student tried to register twice at the same moment
                            $already_registered = true;
                            $done = true;
                        }
                        mysqli_stmt_close($insert_statement);
                    }
                }
            }

            mysqli_stmt_close($lock_statement);
        }

        if ($done) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
            if ($message == "") {
                $message = "error";
            }
        }

        // Get the fresh registration count so the seats update on the page
        $fresh_count = mysqli_prepare($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ?");
        if ($fresh_count) {
            mysqli_stmt_bind_param($fresh_count, "i", $event_id);
            if (mysqli_stmt_execute($fresh_count)) {
                mysqli_stmt_bind_result($fresh_count, $new_count);
                if (mysqli_stmt_fetch($fresh_count)) {
                    $event['registered_count'] = $new_count;
                }
            }
            mysqli_stmt_close($fresh_count);
        }
    }

    // Work out the seat numbers and full/available status
    $seat = get_seat_info(isset($event['registered_count']) ? $event['registered_count'] : 0,
                          isset($event['capacity']) ? $event['capacity'] : 0);
    $is_full = (!$seat['unlimited'] && $seat['available'] == 0);

    $event_start = strtotime($event['event_date'] . " " . ($event['event_time'] ? $event['event_time'] : "00:00:00"));
    $event_started = $event_start > 0 && time() >= $event_start;

    include 'includes/header.php';
?>

<div class="event-detail">

    <?php if ($event['image']) { ?>
        <img src="uploads/<?= $event['image'] ?>" alt="<?= htmlspecialchars($event['title']) ?>" class="event-detail-img">
    <?php } ?>

    <h1><?= htmlspecialchars($event['title']) ?></h1>

    <div class="event-info">
        <p><strong>Date:</strong> <?= date("d M Y", strtotime($event['event_date'])) ?></p>
        <p><strong>Time:</strong> <?= date("h:i A", strtotime($event['event_time'])) ?></p>
        <p><strong>Venue:</strong> <?= htmlspecialchars($event['venue']) ?></p>
        <p><strong>Category:</strong> <?= htmlspecialchars($event['category']) ?></p>
        <p><strong>Organizer:</strong> <?= htmlspecialchars($event['organizer']) ?></p>
    </div>

    <div class="event-description">
        <h3>About this Event</h3>
        <p><?= nl2br($event['description']) ?></p>
    </div>

    <?php if ($seat['unlimited']) { ?>

        <div class="seat-info status-available">
            <div class="seat-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="36" height="36"><path d="M5 11V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4"/><path d="M5 11a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2"/><path d="M6 18v2"/><path d="M18 18v2"/></svg>
            </div>
            <div class="seat-details">
                <div class="seat-headline">
                    <p class="seat-filled"><?= $seat['registered'] ?> students registered</p>
                    <span class="status-badge status-available">Seats Available</span>
                </div>
                <p class="seat-remaining">Open registration &ndash; no seat limit</p>
            </div>
        </div>

    <?php } else { ?>

        <div class="seat-info <?= $seat['class'] ?>">
            <div class="seat-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="36" height="36"><path d="M5 11V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4"/><path d="M5 11a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2"/><path d="M6 18v2"/><path d="M18 18v2"/></svg>
            </div>
            <div class="seat-details">
                <div class="seat-headline">
                    <p class="seat-filled"><strong><?= $seat['registered'] ?> of <?= $seat['capacity'] ?></strong> seats filled</p>
                    <span class="status-badge <?= $seat['class'] ?>"><?= $seat['label'] ?></span>
                </div>
                <p class="seat-remaining"><?= $seat['available'] ?> seats remaining</p>
                <div class="progress-bar progress-lg" role="progressbar"
                     aria-valuemin="0" aria-valuemax="100"
                     aria-valuenow="<?= $seat['percent'] ?>"
                     aria-label="<?= $seat['percent'] ?>% of seats filled">
                    <div class="progress-fill" style="width: <?= $seat['percent'] ?>%;"></div>
                </div>
                <p class="seat-percent"><?= $seat['percent'] ?>% full</p>
            </div>
        </div>

    <?php } ?>

    <p><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>

    <div class="event-register-section">

        <?php if (!$already_registered && !$is_full) { ?>
            <form method="POST">
                <button type="submit" name="register_event" class="btn btn-primary">Register for this Event</button>
            </form>
        <?php } ?>

        <?php if ($message == "success") { ?>
            <div class="alert alert-success">You have successfully registered for this event!</div>
        <?php } elseif ($message == "full") { ?>
            <div class="alert alert-error">This event is currently full. Registration is closed.</div>
        <?php } elseif ($message == "error") { ?>
            <div class="alert alert-error">Something went wrong.</div>
        <?php } ?>

        <?php if ($already_registered) { ?>
            <?php if ($event_started) { ?>
                <div class="alert alert-info">Cancellation is no longer available for this event.</div>
            <?php } else { ?>
                <div class="alert alert-info">You are already registered for this event.</div>
                <button type="button" class="btn btn-cancel js-cancel-trigger"
                        data-event-id="<?= (int)$event['id'] ?>"
                        data-event-title="<?= htmlspecialchars($event['title'], ENT_QUOTES) ?>"
                        data-event-date="<?= date("d M Y", strtotime($event['event_date'])) ?>">
                    Cancel Registration
                </button>
            <?php } ?>
        <?php } elseif ($is_full) { ?>
            <button type="button" class="btn btn-primary btn-disabled" disabled>Event Full</button>
        <?php } ?>

    </div>

</div>

<?php include 'includes/cancel_modal.php'; ?>

<?php include 'includes/footer.php';
exit();
}

include 'includes/header.php';

$search = "";
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Find upcoming events, optionally matching the search term
$query = "SELECT e.*, COUNT(r.id) AS registered_count
          FROM events e
          LEFT JOIN registrations r ON r.event_id = e.id
          WHERE e.event_date >= CURDATE()";

if ($search != "") {
    $query .= " AND (e.title LIKE ? OR e.category LIKE ?)";
}

$query .= " GROUP BY e.id ORDER BY e.event_date ASC";

$stmt = mysqli_prepare($conn, $query);
if ($search != "") {
    $like = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "ss", $like, $like);
}
mysqli_stmt_execute($stmt);
$events_result = mysqli_stmt_get_result($stmt);

// Find the events this student has registered for
$my_events_result = false;
$my_events_stmt = mysqli_prepare($conn, "SELECT e.*, r.registered_at
                                         FROM registrations r
                                         JOIN events e ON r.event_id = e.id
                                         WHERE r.user_id = ?
                                         ORDER BY e.event_date ASC");
if ($my_events_stmt) {
    mysqli_stmt_bind_param($my_events_stmt, "i", $user_id);
    if (mysqli_stmt_execute($my_events_stmt)) {
        $my_events_result = mysqli_stmt_get_result($my_events_stmt);
    }
}
?>

<div class="dashboard">

    <h2>Welcome, <?= $_SESSION['user_name'] ?>!</h2>

    <?php
        // Show the message left after the last cancellation attempt
        $flash_type  = isset($_SESSION['flash']['type']) ? $_SESSION['flash']['type'] : "";
        $flash_title = isset($_SESSION['flash']['title']) ? $_SESSION['flash']['title'] : "";

        if ($flash_type != "") {
            if ($flash_type == "success") {
                $flash_msg = 'Your registration for &ldquo;' . htmlspecialchars($flash_title) . '&rdquo; has been cancelled successfully. One seat is now available.';
                $flash_class = "alert-success";
            } elseif ($flash_type == "not_registered") {
                $flash_msg = "You are not currently registered for this event.";
                $flash_class = "alert-error";
            } elseif ($flash_type == "closed") {
                $flash_msg = "Cancellation is no longer available because this event has already started.";
                $flash_class = "alert-error";
            } elseif ($flash_type == "invalid") {
                $flash_msg = "Unable to process the cancellation request. Please try again.";
                $flash_class = "alert-error";
            } else {
                $flash_msg = "Unable to process the cancellation request. Please try again.";
                $flash_class = "alert-error";
            }
            echo '<div class="alert ' . $flash_class . '">' . $flash_msg . '</div>';
            unset($_SESSION['flash']);
        }
    ?>

    <section class="dashboard-section">
        <h3>My Registered Events</h3>

        <?php if ($my_events_result && mysqli_num_rows($my_events_result) > 0) { ?>
            <div class="table-wrap">
            <table class="table">
                <tr><th>Event</th><th>Date</th><th>Venue</th><th>Registered On</th><th>Status</th><th>Action</th></tr>
                <?php while ($registration = mysqli_fetch_assoc($my_events_result)) { ?>
                    <?php
                        $event_start = strtotime($registration['event_date'] . " " . ($registration['event_time'] ? $registration['event_time'] : "00:00:00"));
                        $can_cancel = ($event_start > 0 && time() < $event_start);
                    ?>
                    <tr>
                        <td><a href="dashboard.php?view=<?= (int)$registration['id'] ?>"><?= htmlspecialchars($registration['title']) ?></a></td>
                        <td><?= date("d M Y", strtotime($registration['event_date'])) ?></td>
                        <td><?= htmlspecialchars($registration['venue']) ?></td>
                        <td><?= date("d M Y", strtotime($registration['registered_at'])) ?></td>
                        <td><span class="status-badge status-available">Registered</span></td>
                        <td>
                            <?php if ($can_cancel) { ?>
                                <button type="button" class="btn btn-cancel js-cancel-trigger"
                                        data-event-id="<?= (int)$registration['id'] ?>"
                                        data-event-title="<?= htmlspecialchars($registration['title'], ENT_QUOTES) ?>"
                                        data-event-date="<?= date("d M Y", strtotime($registration['event_date'])) ?>">
                                    Cancel Registration
                                </button>
                            <?php } else { ?>
                                <button type="button" class="btn btn-cancel btn-cancel-disabled" disabled title="Cancellation is no longer available for this event.">Cancel Registration</button>
                                <span class="cancel-closed-note">Cancellation is no longer available for this event.</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            </div>
        <?php } else { ?>
            <p class="no-events">You haven't registered for any events yet.</p>
        <?php } ?>

    </section>

    <section class="dashboard-section">
        <h3>Browse & Register for Events</h3>

        <div class="search-filter">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>

        <div class="event-grid">

            <?php if ($events_result && mysqli_num_rows($events_result) > 0) { ?>
                <?php while ($event = mysqli_fetch_assoc($events_result)) { ?>
                    <?= event_card($event, "View &amp; Register") ?>
                <?php } ?>
            <?php } else { ?>
                <p class="no-events">No events found.</p>
            <?php } ?>

        </div>
    </section>

</div>

<?php include 'includes/cancel_modal.php'; ?>

<?php include 'includes/footer.php'; ?>
