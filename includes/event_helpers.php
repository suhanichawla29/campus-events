<?php
// ---------------------------------------------------------------
// Event Capacity / Available Seats - shared helper functions.
// Included by index.php and dashboard.php. Safe to include twice
// because of include_once at the call sites.
// ---------------------------------------------------------------

/**
 * Work out all seat numbers + status for ONE event row.
 * $registered_count comes from COUNT() in the listing query,
 * $capacity from the events.capacity column.
 *
 * Rules:
 *   capacity <= 0 or missing  -> "open registration" (no limit)
 *   available > 10            -> green  "Seats Available"
 *   available 1..10           -> orange "Almost Full"
 *   available == 0            -> red    "Event Full"
 */
function seat_data($registered_count, $capacity) {
    $registered_count = max(0, (int) $registered_count);
    $capacity         = (int) $capacity;

    if ($capacity <= 0) {
        return array(
            'unlimited'  => true,
            'registered' => $registered_count,
            'capacity'   => 0,
            'available'  => null,
            'percent'    => 0,
            'label'      => 'Seats Available',
            'class'      => 'status-available'
        );
    }

    // Never negative, even if registrations accidentally exceed capacity.
    $available = max(0, $capacity - $registered_count);
    $percent   = min(100, (int) round(($registered_count / $capacity) * 100));

    if ($available === 0) {
        $label = 'Event Full';
        $class = 'status-full';
    } elseif ($available <= 10) {
        $label = 'Almost Full';
        $class = 'status-almost-full';
    } else {
        $label = 'Seats Available';
        $class = 'status-available';
    }

    return array(
        'unlimited'  => false,
        'registered' => $registered_count,
        'capacity'   => $capacity,
        'available'  => $available,
        'percent'    => $percent,
        'label'      => $label,
        'class'      => $class
    );
}

/**
 * Compact capacity block used on the small EVENT CARDS
 * (home page + dashboard). Shows seats left, status badge,
 * progress bar and a filled/total summary line.
 */
function event_capacity_html($registered_count, $capacity) {
    $s = seat_data($registered_count, $capacity);

    if ($s['unlimited']) {
        return '<div class="capacity-info">'
             . '<div class="capacity-top">'
             . '<span class="seats-left">Open registration</span>'
             . '<span class="status-badge ' . $s['class'] . '">' . $s['label'] . '</span>'
             . '</div>'
             . '<p class="capacity-summary">' . $s['registered'] . ' registered so far</p>'
             . '</div>';
    }

    return '<div class="capacity-info">'
         . '<div class="capacity-top">'
         . '<span class="seats-left">' . $s['available'] . ' seats left</span>'
         . '<span class="status-badge ' . $s['class'] . '">' . $s['label'] . '</span>'
         . '</div>'
         . '<div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"'
         . ' aria-valuenow="' . $s['percent'] . '"'
         . ' aria-label="' . $s['registered'] . ' of ' . $s['capacity'] . ' seats filled">'
         . '<div class="progress-fill" style="width:' . $s['percent'] . '%"></div>'
         . '</div>'
         . '<p class="capacity-summary">' . $s['registered'] . ' of ' . $s['capacity'] . ' seats filled</p>'
         . '</div>';
}
