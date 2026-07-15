<?php
// Database configuration
// Change these values if your MySQL settings are different
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "campus_events_db";

// Create connection
$conn = mysqli_connect($host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>