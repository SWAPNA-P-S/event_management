<?php

include "auth_student.php";
include "db.php";

date_default_timezone_set("Asia/Kolkata");

$user_id = $_SESSION["user_id"];

$registration_id = $_GET["registration_id"] ?? "";

if (empty($registration_id)) {
    die("Registration not found.");
}


/* Get event + attendance information */

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
        e.latitude,
        e.longitude,
        a.attendance_id,
        a.attendance_date,
        a.attendance_time,
        a.verification_status,
        a.verification_method
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     LEFT JOIN attendance a
     ON r.registration_id = a.registration_id
     AND a.attendance_date = CURDATE()
     WHERE r.registration_id = ?
     AND r.user_id = ?
     AND e.fee = 0"
);

$stmt->bind_param("ii", $registration_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid attendance request.");
}

$event = $result->fetch_assoc();

$stmt->close();


/* Current date and time */

$current_date = date("Y-m-d");
$current_time = date("H:i:s");


/* Check whether attendance time is active */

$time_available = false;

if (
    $current_date === $event["event_date"] &&
    $current_time >= $event["start_time"] &&
    $current_time <= $event["end_time"]
) {
    $time_available = true;
}


/* Attendance status */

$attendance_status = $event["verification_status"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Attendance - Event Hub</title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {
            font-family: Arial, sans-serif;
            background: #f5f6fb;
            color: #222;
        }


        header {
            background: #172554;
            color: white;
            padding: 18px 40px;
        }


        .logo {
            font-size: 24px;
            font-weight: bold;
        }


        .page {
            min-height: calc(100vh - 70px);
            padding: 40px 20px;
        }


        .container {
            max-width: 850px;
            margin: auto;
        }


        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            color: #263a8b;
            text-decoration: none;
            font-weight: 600;
        }


        h1 {
            color: #172554;
            font-size: 32px;
            margin-bottom: 8px;
        }


        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }


        .attendance-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }


        .event-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }


        .event-title h2 {
            color: #1e3a8a;
            margin-bottom: 8px;
        }


        .event-title p {
            color: #666;
        }


        .event-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }


        .info-box {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
        }


        .info-box span {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 5px;
        }


        .info-box strong {
            color: #333;
        }


        .verification-box {
            background: #eef2ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }


        .verification-box h3 {
            color: #263a8b;
            margin-bottom: 10px;
        }


        .verification-box p {
            color: #555;
            line-height: 1.6;
        }


        .location-status,
        .time-status,
        .ai-status {
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
        }


        .form-group {
            margin-bottom: 22px;
        }


        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }


        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }


        .location-btn {
            border: none;
            background: #eef2ff;
            color: #263a8b;
            padding: 11px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }


        .location-btn:hover {
            background: #e0e7ff;
        }


        .submit-btn {
            width: 100%;
            border: none;
            background: #263a8b;
            color: white;
            padding: 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }


        .submit-btn:disabled {
            background: #aaa;
            cursor: not-allowed;
        }


        .status-box {
            padding: 22px;
            border-radius: 10px;
            text-align: center;
            margin-top: 20px;
        }


        .verified {
            background: #dcfce7;
            color: #166534;
        }


        .failed {
            background: #fee2e2;
            color: #991b1b;
        }


        .status-box h2 {
            margin-bottom: 8px;
        }


        @media (max-width: 600px) {

            header {
                padding: 16px 20px;
            }

            .page {
                padding: 30px 15px;
            }

            h1 {
                font-size: 27px;
            }

            .event-info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<header>

    <div class="logo">
        Event Hub
    </div>

</header>


<main class="page">

    <div class="container">


        <a href="my_registration.php" class="back-link">
            ← Back to My Registrations
        </a>


        <h1>My Attendance</h1>

        <p class="subtitle">
            Complete automatic attendance verification for this event.
        </p>


        <div class="attendance-card">


            <!-- Event Information -->

            <div class="event-title">

                <h2>
                    <?php echo htmlspecialchars($event["event_name"]); ?>
                </h2>

                <p>
                    📍 <?php echo htmlspecialchars($event["venue"]); ?>
                </p>

            </div>


            <div class="event-info">

                <div class="info-box">

                    <span>📅 Date</span>

                    <strong>
                        <?php
                        echo date(
                            "d F Y",
                            strtotime($event["event_date"])
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-box">

                    <span>⏰ Time</span>

                    <strong>

                        <?php
                        echo date(
                            "h:i A",
                            strtotime($event["start_time"])
                        );
                        ?>

                        –

                        <?php
                        echo date(
                            "h:i A",
                            strtotime($event["end_time"])
                        );
                        ?>

                    </strong>

                </div>

            </div>


            <?php if ($attendance_status === "verified"): ?>


                <!-- VERIFIED -->

                <div class="status-box verified">

                    <h2>
                        🟢 Attendance Verified
                    </h2>

                    <p>
                        Your attendance has been automatically verified.
                    </p>

                    <p style="margin-top:10px;">
                        Verification method:
                        <?php
                        echo htmlspecialchars(
                            $event["verification_method"]
                        );
                        ?>
                    </p>

                </div>


            <?php elseif ($attendance_status === "rejected"): ?>


                <!-- REJECTED -->

                <div class="status-box failed">

                    <h2>
                        🔴 Attendance Verification Failed
                    </h2>

                    <p>
                        Your attendance could not be verified.
                    </p>

                </div>


            <?php elseif (!$time_available): ?>


                <!-- OUTSIDE EVENT TIME -->

                <div class="status-box failed">

                    <h2>
                        ⏰ Attendance Not Available
                    </h2>

                    <p>
                        Attendance can be submitted only during the event time.
                    </p>

                </div>


            <?php else: ?>


                <!-- ATTENDANCE FORM -->

                <div class="verification-box">

                    <h3>
                        🤖 Automatic Verification
                    </h3>

                    <p>
                        Your attendance will be automatically checked using
                        your event registration, current location, event time
                        and uploaded photo.
                    </p>


                    <div class="time-status" id="timeStatus">
                        ⏰ Event time verified
                    </div>


                    <div class="location-status" id="locationStatus">
                        📍 Location not detected
                    </div>


                    <div class="ai-status" id="aiStatus">
                        🤖 Photo AI verification pending
                    </div>

                </div>


                <form
                    action="attendance_process.php"
                    method="POST"
                    enctype="multipart/form-data"
                    id="attendanceForm"
                >


                    <input
                        type="hidden"
                        name="registration_id"
                        value="<?php echo $event["registration_id"]; ?>"
                    >


                    <input
                        type="hidden"
                        name="latitude"
                        id="latitude"
                    >


                    <input
                        type="hidden"
                        name="longitude"
                        id="longitude"
                    >


                    <!-- Photo -->

                    <div class="form-group">

                        <label for="event_photo">
                            📸 Event Photo
                        </label>

                        <input
                            type="file"
                            name="event_photo"
                            id="event_photo"
                            accept="image/*"
                            capture="environment"
                            required
                        >

                    </div>


                    <!-- Location -->

                    <div class="form-group">

                        <label>
                            📍 Current Location
                        </label>

                        <button
                            type="button"
                            class="location-btn"
                            onclick="getLocation()"
                        >
                            Detect My Location
                        </button>

                    </div>


                    <!-- Submit -->

                    <button
                        type="submit"
                        class="submit-btn"
                        id="submitBtn"
                        disabled
                    >
                        Verify & Mark Attendance
                    </button>


                </form>


            <?php endif; ?>


        </div>

    </div>

</main>


<script>


let locationDetected = false;


function getLocation() {

    const locationStatus =
        document.getElementById("locationStatus");


    if (!navigator.geolocation) {

        locationStatus.innerHTML =
            "❌ Geolocation is not supported by this browser.";

        return;
    }


    locationStatus.innerHTML =
        "📍 Detecting your location...";


    navigator.geolocation.getCurrentPosition(

        function(position) {

            document.getElementById("latitude").value =
                position.coords.latitude;

            document.getElementById("longitude").value =
                position.coords.longitude;


            locationDetected = true;


            locationStatus.innerHTML =
                "🟢 Location detected successfully";


            checkForm();

        },

        function(error) {

            locationStatus.innerHTML =
                "❌ Please allow location access.";

        }

    );

}


function checkForm() {

    const photo =
        document.getElementById("event_photo");

    const submitBtn =
        document.getElementById("submitBtn");


    if (
        photo.files.length > 0 &&
        locationDetected
    ) {

        submitBtn.disabled = false;

        document.getElementById("aiStatus").innerHTML =
            "🤖 Photo ready for automatic AI verification";

    } else {

        submitBtn.disabled = true;

    }

}


document
    .getElementById("event_photo")
    .addEventListener("change", checkForm);


</script>


</body>

</html>