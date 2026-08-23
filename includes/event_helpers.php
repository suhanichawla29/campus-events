<?php

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

function capacity_html($seat) {

    $html = '<div class="capacity-info">';
    $html .= '<div class="capacity-top">';

    if ($seat["unlimited"]) {
        $html .= '<span class="seats-left">Open registration</span>';
        $html .= '<span class="status-badge status-available">Seats Available</span>';
        $html .= '</div>';
        $html .= '<p class="capacity-summary">' . $seat["registered"] . ' registered so far</p>';
    } else {
        $html .= '<span class="seats-left">' . $seat["available"] . ' seats left</span>';
        $html .= '<span class="status-badge ' . $seat["class"] . '">' . $seat["label"] . '</span>';
        $html .= '</div>';
        $html .= '<div class="progress-bar" role="progressbar"';
        $html .= ' aria-valuemin="0" aria-valuemax="100"';
        $html .= ' aria-valuenow="' . $seat["percent"] . '"';
        $html .= ' aria-label="' . $seat["registered"] . ' of ' . $seat["capacity"] . ' seats filled">';
        $html .= '<div class="progress-fill" style="width:' . $seat["percent"] . '%"></div>';
        $html .= '</div>';
        $html .= '<p class="capacity-summary">' . $seat["registered"] . ' of ' . $seat["capacity"] . ' seats filled</p>';
    }

    $html .= '</div>';
    return $html;
}
