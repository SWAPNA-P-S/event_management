<?php

include "auth_student.php";
include "db.php";

date_default_timezone_set("Asia/Kolkata");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$user_id = $_SESSION["user_id"];

$registration_id = $_POST["registration_id"] ?? "";
$latitude = $_POST["latitude"] ?? "";
$longitude = $_POST["longitude"] ?? "";


/* Basic validation */

if (empty($registration_id)) {
    die("Registration not found.");
}

if ($latitude === "" || $longitude === "") {
    die("Please detect your location first.");
}

if (!isset($_FILES["event_photo"]) ||
    $_FILES["event_photo"]["error"] !== UPLOAD_ERR_OK) {

    die("Please upload an event photo.");
}


/* Get registration + event */

$stmt = $conn->prepare(
    "SELECT
        r.registration_id,
        e.event_id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.fee,
        e.latitude AS venue_latitude,
        e.longitude AS venue_longitude
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     WHERE r.registration_id = ?
     AND r.user_id = ?"
);

$stmt->bind_param(
    "ii",
    $registration_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid registration.");
}

$event = $result->fetch_assoc();

$stmt->close();


/* Only FREE events */

if ((float)$event["fee"] > 0) {
    die("This attendance method is only for free events.");
}


/* Check event date */

$today = date("Y-m-d");

if ($today !== $event["event_date"]) {
    die("Attendance is available only on the event date.");
}


/* Check event time */

$current_time = date("H:i:s");

if (
    $current_time < $event["start_time"] ||
    $current_time > $event["end_time"]
) {
    die("Attendance is available only during the event time.");
}


/* Check venue coordinates */

if (
    $event["venue_latitude"] === null ||
    $event["venue_longitude"] === null
) {
    die("Event venue location is not configured.");
}


/* Calculate distance using Haversine formula */

$student_lat = (float)$latitude;
$student_lon = (float)$longitude;

$venue_lat = (float)$event["venue_latitude"];
$venue_lon = (float)$event["venue_longitude"];

$earth_radius = 6371000;


/* Convert degrees to radians */

$lat1 = deg2rad($student_lat);
$lat2 = deg2rad($venue_lat);

$delta_lat = deg2rad($venue_lat - $student_lat);
$delta_lon = deg2rad($venue_lon - $student_lon);


/* Haversine */

$a =
    sin($delta_lat / 2) * sin($delta_lat / 2)
    +
    cos($lat1) *
    cos($lat2) *
    sin($delta_lon / 2) *
    sin($delta_lon / 2);

$c = 2 * atan2(
    sqrt($a),
    sqrt(1 - $a)
);

$distance = $earth_radius * $c;


/*
    Attendance radius:
    Student must be within 200 metres
*/

$allowed_radius = 200;

if ($distance > $allowed_radius) {

    die(
        "Attendance verification failed. " .
        "You are approximately " .
        round($distance) .
        " metres away from the event venue."
    );

}


/* Check duplicate attendance */

$check = $conn->prepare(
    "SELECT
        attendance_id,
        verification_status
     FROM attendance
     WHERE registration_id = ?
     AND attendance_date = ?"
);

$check->bind_param(
    "is",
    $registration_id,
    $today
);

$check->execute();

$check_result = $check->get_result();

if ($check_result->num_rows > 0) {

    $existing = $check_result->fetch_assoc();

    if ($existing["verification_status"] === "verified") {

        die("Attendance has already been verified.");

    } else {

        die("Attendance has already been submitted.");

    }

}

$check->close();


/* Photo validation */

$allowed_types = [
    "image/jpeg",
    "image/png",
    "image/webp"
];

$file_type = mime_content_type(
    $_FILES["event_photo"]["tmp_name"]
);

if (!in_array($file_type, $allowed_types)) {
    die("Please upload a valid JPG, PNG or WEBP image.");
}


/* Maximum file size: 5 MB */

if ($_FILES["event_photo"]["size"] > 5 * 1024 * 1024) {
    die("Photo size must be less than 5 MB.");
}


/* Create upload directory */

$upload_dir = "uploads/attendance/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}


/* Generate safe filename */

$extension = pathinfo(
    $_FILES["event_photo"]["name"],
    PATHINFO_EXTENSION
);

$file_name =
    "attendance_" .
    $registration_id .
    "_" .
    time() .
    "." .
    strtolower($extension);

$file_path = $upload_dir . $file_name;


/* Move uploaded photo */

if (!move_uploaded_file(
    $_FILES["event_photo"]["tmp_name"],
    $file_path
)) {

    die("Failed to upload photo.");

}


/*
    AUTOMATIC VERIFICATION

    Registration       = PASS
    Event date         = PASS
    Event time         = PASS
    GPS location       = PASS
    Photo validation   = PASS

    Therefore attendance is VERIFIED.
*/

$attendance_date = date("Y-m-d");
$attendance_time = date("H:i:s");

$verification_status = "verified";

$verification_method =
    "automatic: registration + GPS + time + photo";


/* Insert attendance */

$insert = $conn->prepare(
    "INSERT INTO attendance
    (
        registration_id,
        attendance_date,
        attendance_time,
        latitude,
        longitude,
        event_photo,
        verification_status,
        verification_method
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$insert->bind_param(
    "issddsss",
    $registration_id,
    $attendance_date,
    $attendance_time,
    $student_lat,
    $student_lon,
    $file_path,
    $verification_status,
    $verification_method
);


if ($insert->execute()) {

    /*
        Redirect back to attendance page
        with VERIFIED status.
    */

    header(
        "Location: attendance.php?registration_id=" .
        $registration_id
    );

    exit();

} else {

    die("Failed to mark attendance.");

}


$insert->close();

$conn->close();

?>