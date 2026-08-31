<?php

// Work out the seat numbers, fill percentage and status label for one event
function get_seat_info($registered, $capacity) {

    $registered = max(0, (int)$registered);
    $capacity   = (int)$capacity;

    $seat = array(
        "unlimited"  => false,
        "registered" => $registered,
        "capacity"   => $capacity,
        "available"  => 0,
        "percent"    => 0,
        "label"      => "Seats Available",
        "class"      => "status-available"
    );

    // A capacity of 0 means there is no seat limit
    if ($capacity <= 0) {
        $seat["unlimited"] = true;
        return $seat;
    }

    $available = $capacity - $registered;
    if ($available < 0) {
        $available = 0;
    }

    $percent = (int)round(($registered / $capacity) * 100);
    if ($percent > 100) {
        $percent = 100;
    }

    $seat["available"] = $available;
    $seat["percent"]   = $percent;

    if ($available == 0) {
        $seat["label"] = "Event Full";
        $seat["class"] = "status-full";
    } else if ($available <= 10) {
        $seat["label"] = "Almost Full";
        $seat["class"] = "status-almost-full";
    }

    return $seat;
}

// Build the small capacity bar shown on event cards
function capacity_html($seat) {

    $markup = '<div class="capacity-info">';
    $markup .= '<div class="capacity-top">';

    if ($seat["unlimited"]) {
        $markup .= '<span class="seats-left">Open registration</span>';
        $markup .= '<span class="status-badge status-available">Seats Available</span>';
        $markup .= '</div>';
        $markup .= '<p class="capacity-summary">' . $seat["registered"] . ' registered so far</p>';
    } else {
        $markup .= '<span class="seats-left">' . $seat["available"] . ' seats left</span>';
        $markup .= '<span class="status-badge ' . $seat["class"] . '">' . $seat["label"] . '</span>';
        $markup .= '</div>';
        $markup .= '<div class="progress-bar" role="progressbar"';
        $markup .= ' aria-valuemin="0" aria-valuemax="100"';
        $markup .= ' aria-valuenow="' . $seat["percent"] . '"';
        $markup .= ' aria-label="' . $seat["registered"] . ' of ' . $seat["capacity"] . ' seats filled">';
        $markup .= '<div class="progress-fill" style="width:' . $seat["percent"] . '%"></div>';
        $markup .= '</div>';
        $markup .= '<p class="capacity-summary">' . $seat["registered"] . ' of ' . $seat["capacity"] . ' seats filled</p>';
    }

    $markup .= '</div>';
    return $markup;
}

// Build one event card. Used on the home page and the dashboard.
function event_card($event, $button_text) {

    $image = $event['image'] ? "uploads/" . $event['image'] : "assets/images/default-event.jpg";
    $seat = get_seat_info(isset($event['registered_count']) ? $event['registered_count'] : 0,
                          isset($event['capacity']) ? $event['capacity'] : 0);
    ?>

    <div class="event-card">
        <img src="<?= $image ?>" alt="<?= htmlspecialchars($event['title']) ?>" class="event-card-img">

        <div class="event-card-body">
            <h3><?= htmlspecialchars($event['title']) ?></h3>

            <p class="event-date">
                <?= date("d M Y", strtotime($event['event_date'])) ?> at <?= date("h:i A", strtotime($event['event_time'])) ?>
            </p>

            <p><strong>Venue:</strong> <?= htmlspecialchars($event['venue']) ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($event['category']) ?></p>

            <?= capacity_html($seat) ?>

            <a href="dashboard.php?view=<?= (int)$event['id'] ?>" class="btn btn-small"><?= $button_text ?></a>
        </div>
    </div>

    <?php
}

// Create and remember one random token to protect forms from CSRF attacks
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Print the hidden token field inside a form
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Check that the token sent with the form matches the one in the session
function check_csrf() {
    $sent = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!empty($_SESSION['csrf_token']) && is_string($sent) && hash_equals($_SESSION['csrf_token'], $sent)) {
        return true;
    }
    return false;
}
