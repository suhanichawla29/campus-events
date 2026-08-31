<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

$user_id = $_SESSION['user_id'];

include_once 'includes/event_helpers.php';

$_SESSION['flash'] = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();

if (isset($_POST['cancel_registration'])) {

    $result = "error";

    if (check_csrf()) {

        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

        if ($event_id > 0) {
            $event = null;
            $stmt = mysqli_prepare($conn, "SELECT id, title, event_date, event_time FROM events WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $event_id);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $event = $res ? mysqli_fetch_assoc($res) : null;
                }
                mysqli_stmt_close($stmt);
            }

            if (!$event) {
                $result = "invalid";
            } else {

                $now = time();
                $start = strtotime($event['event_date'] . " " . ($event['event_time'] ? $event['event_time'] : "00:00:00"));
                $started = $start > 0 && $now >= $start;

                $owns = false;
                $check = mysqli_prepare($conn, "SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
                if ($check) {
                    mysqli_stmt_bind_param($check, "ii", $user_id, $event_id);
                    mysqli_stmt_execute($check);
                    mysqli_stmt_store_result($check);
                    $owns = mysqli_stmt_num_rows($check) > 0;
                    mysqli_stmt_close($check);
                }

                if (!$owns) {
                    $result = "not_registered";
                } else if ($started) {
                    $result = "closed";
                } else {
                    $del = mysqli_prepare($conn, "DELETE FROM registrations WHERE user_id = ? AND event_id = ?");
                    if ($del) {
                        mysqli_stmt_bind_param($del, "ii", $user_id, $event_id);
                        if (mysqli_stmt_execute($del) && mysqli_stmt_affected_rows($del) > 0) {
                            $result = "success";
                            $_SESSION['flash']['title'] = $event['title'];
                        }
                        mysqli_stmt_close($del);
                    }
                }
            }
        }
    }

    $_SESSION['flash']['type'] = $result;

    header("Location: dashboard.php");
    exit();
}

if (isset($_GET['view'])) {
    $event_id = (int)$_GET['view'];

    $stmt = mysqli_prepare($conn, "SELECT e.*, COUNT(r.id) AS registered_count
                                   FROM events e
                                   LEFT JOIN registrations r ON r.event_id = e.id
                                   WHERE e.id = ?
                                   GROUP BY e.id");
    $event = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $event  = $result ? mysqli_fetch_assoc($result) : null;
        }
        mysqli_stmt_close($stmt);
    }

    if (!$event) {
        header("Location: dashboard.php");
        exit();
    }

    $already_registered = false;
    $check = mysqli_prepare($conn, "SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
    if ($check) {
        mysqli_stmt_bind_param($check, "ii", $user_id, $event_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $already_registered = true;
        }
        mysqli_stmt_close($check);
    }

    $message = "";

    if (isset($_POST['register_event']) && !$already_registered) {

        $done = false;

        mysqli_begin_transaction($conn);

        $lock = mysqli_prepare($conn, "SELECT capacity FROM events WHERE id = ? FOR UPDATE");
        if ($lock) {
            mysqli_stmt_bind_param($lock, "i", $event_id);
            mysqli_stmt_execute($lock);
            mysqli_stmt_store_result($lock);

            if (mysqli_stmt_num_rows($lock) == 1) {

                mysqli_stmt_bind_result($lock, $db_capacity);
                mysqli_stmt_fetch($lock);
                $capacity = max(0, (int)$db_capacity);

                $current_registrations = 0;
                $count_ok = false;

                $count = mysqli_prepare($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ?");
                if ($count) {
                    mysqli_stmt_bind_param($count, "i", $event_id);
                    if (mysqli_stmt_execute($count)) {
                        mysqli_stmt_bind_result($count, $current_registrations);
                        mysqli_stmt_fetch($count);
                        $count_ok = true;
                    }
                    mysqli_stmt_close($count);
                }

                if (!$count_ok) {
                    $done = false;
                } else if ($capacity > 0 && $current_registrations >= $capacity) {
                    $message = "full";
                    $done = true;
                } else {
                    $insert = mysqli_prepare($conn, "INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
                    if ($insert) {
                        mysqli_stmt_bind_param($insert, "ii", $user_id, $event_id);

                        if (mysqli_stmt_execute($insert)) {
                            $message = "success";
                            $already_registered = true;
                            $done = true;
                        } else if (mysqli_stmt_errno($insert) == 1062) {
                            $already_registered = true;
                            $done = true;
                        }
                        mysqli_stmt_close($insert);
                    }
                }
            }

            mysqli_stmt_close($lock);
        }

        if ($done) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
            if ($message == "") {
                $message = "error";
            }
        }

        $fresh = mysqli_prepare($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ?");
        if ($fresh) {
            mysqli_stmt_bind_param($fresh, "i", $event_id);
            if (mysqli_stmt_execute($fresh)) {
                mysqli_stmt_bind_result($fresh, $new_count);
                if (mysqli_stmt_fetch($fresh)) {
                    $event['registered_count'] = $new_count;
                }
            }
            mysqli_stmt_close($fresh);
        }
    }

    $seat    = get_seat_info(isset($event['registered_count']) ? $event['registered_count'] : 0,
                             isset($event['capacity']) ? $event['capacity'] : 0);
    $is_full = (!$seat['unlimited'] && $seat['available'] == 0);

    $ev_start = strtotime($event['event_date'] . " " . ($event['event_time'] ? $event['event_time'] : "00:00:00"));
    $ev_started = $ev_start > 0 && time() >= $ev_start;

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
            <?php if ($ev_started) { ?>
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

$sql = "SELECT e.*, COUNT(r.id) AS registered_count
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id
        WHERE e.event_date >= CURDATE()";

if ($search != "") {
    $sql .= " AND (e.title LIKE ? OR e.category LIKE ?)";
}

$sql .= " GROUP BY e.id ORDER BY e.event_date ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($search != "") {
    $like = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "ss", $like, $like);
}
mysqli_stmt_execute($stmt);
$events_result = mysqli_stmt_get_result($stmt);

$my_events_result = false;
$my_stmt = mysqli_prepare($conn, "SELECT e.*, r.registered_at
                                  FROM registrations r
                                  JOIN events e ON r.event_id = e.id
                                  WHERE r.user_id = ?
                                  ORDER BY e.event_date ASC");
if ($my_stmt) {
    mysqli_stmt_bind_param($my_stmt, "i", $user_id);
    if (mysqli_stmt_execute($my_stmt)) {
        $my_events_result = mysqli_stmt_get_result($my_stmt);
    }
}
?>

<div class="dashboard">

    <h2>Welcome, <?= $_SESSION['user_name'] ?>!</h2>

    <?php
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
                <?php while ($re = mysqli_fetch_assoc($my_events_result)) { ?>
                    <?php
                        $ev_started = strtotime($re['event_date'] . " " . ($re['event_time'] ? $re['event_time'] : "00:00:00"));
                        $ev_cancellable = ($ev_started > 0 && time() < $ev_started);
                    ?>
                    <tr>
                        <td><a href="dashboard.php?view=<?= (int)$re['id'] ?>"><?= htmlspecialchars($re['title']) ?></a></td>
                        <td><?= date("d M Y", strtotime($re['event_date'])) ?></td>
                        <td><?= htmlspecialchars($re['venue']) ?></td>
                        <td><?= date("d M Y", strtotime($re['registered_at'])) ?></td>
                        <td><span class="status-badge status-available">Registered</span></td>
                        <td>
                            <?php if ($ev_cancellable) { ?>
                                <button type="button" class="btn btn-cancel js-cancel-trigger"
                                        data-event-id="<?= (int)$re['id'] ?>"
                                        data-event-title="<?= htmlspecialchars($re['title'], ENT_QUOTES) ?>"
                                        data-event-date="<?= date("d M Y", strtotime($re['event_date'])) ?>">
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
                <?php while ($ev = mysqli_fetch_assoc($events_result)) { ?>
                    <?php
                        $img = $ev['image'] ? "uploads/" . $ev['image'] : "assets/images/default-event.jpg";
                        $seat = get_seat_info($ev['registered_count'], isset($ev['capacity']) ? $ev['capacity'] : 0);
                    ?>

                    <div class="event-card">
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($ev['title']) ?>" class="event-card-img">

                        <div class="event-card-body">
                            <h3><?= htmlspecialchars($ev['title']) ?></h3>

                            <p class="event-date">
                                <?= date("d M Y", strtotime($ev['event_date'])) ?> at <?= date("h:i A", strtotime($ev['event_time'])) ?>
                            </p>

                            <p><strong>Venue:</strong> <?= htmlspecialchars($ev['venue']) ?></p>
                            <p><strong>Category:</strong> <?= htmlspecialchars($ev['category']) ?></p>

                            <?= capacity_html($seat) ?>

                            <a href="dashboard.php?view=<?= (int)$ev['id'] ?>" class="btn btn-small">View &amp; Register</a>
                        </div>
                    </div>

                <?php } ?>
            <?php } else { ?>
                <p class="no-events">No events found.</p>
            <?php } ?>

        </div>
    </section>

</div>

<?php include 'includes/cancel_modal.php'; ?>

<?php include 'includes/footer.php'; ?>
