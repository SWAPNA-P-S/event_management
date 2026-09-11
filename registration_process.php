<?php

include "auth_student.php";
include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$user_id = $_SESSION["user_id"];
$event_id = $_POST["event_id"];


/* Check whether already registered */

$check = $conn->prepare(
    "SELECT registration_id
     FROM registrations
     WHERE user_id = ? AND event_id = ?"
);

$check->bind_param("ii", $user_id, $event_id);
$check->execute();

$check_result = $check->get_result();

if ($check_result->num_rows > 0) {

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Already Registered - Event Hub</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f3fc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .message-container {
            background: white;
            width: 90%;
            max-width: 500px;
            padding: 45px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #fff3cd;
            color: #d39e00;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        h1 {
            color: #4c2a87;
            margin-bottom: 12px;
            font-size: 28px;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .btn {
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .primary-btn {
            background: #4c2a87;
            color: white;
        }

        .secondary-btn {
            background: #eee6ff;
            color: #4c2a87;
        }

    </style>

</head>

<body>

    <div class="message-container">

        <div class="icon">
            !
        </div>

        <h1>Already Registered</h1>

        <p>
            You have already registered for this event.
            You cannot register for the same event again.
        </p>

        <div class="buttons">

            <a href="events.php" class="btn primary-btn">
                Back to Events
            </a>

            <a href="student_dashboard.php" class="btn secondary-btn">
                Dashboard
            </a>

        </div>

    </div>

</body>

</html>

<?php

    exit();
}

$check->close();


/* Get event fee */

$event_stmt = $conn->prepare(
    "SELECT fee, event_name
     FROM events
     WHERE event_id = ?"
);

$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();

$event_result = $event_stmt->get_result();

if ($event_result->num_rows === 0) {
    die("Event not found.");
}

$event_data = $event_result->fetch_assoc();

$event_fee = $event_data["fee"];
$event_name = $event_data["event_name"];

$event_stmt->close();


/* Decide registration type automatically */

if ($event_fee > 0) {
    $registration_type = "paid";
} else {
    $registration_type = "free";
}


/* Insert registration */

$stmt = $conn->prepare(
    "INSERT INTO registrations
     (user_id, event_id, registration_type, status)
     VALUES (?, ?, ?, 'pending')"
);

$stmt->bind_param(
    "iis",
    $user_id,
    $event_id,
    $registration_type
);


if ($stmt->execute()) {

    $registration_id = $stmt->insert_id;


    /* Paid event */

    if ($event_fee > 0) {

        header(
            "Location: payment.php?registration_id=" . $registration_id
        );

        exit();

    }


    /* Free event */

    else {

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registration Successful - Event Hub</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f3fc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-container {
            background: white;
            width: 90%;
            max-width: 500px;
            padding: 45px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
        }

        h1 {
            color: #2e7d32;
            margin-bottom: 15px;
            font-size: 28px;
        }

        .event-name {
            color: #293f91;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 12px;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .primary-btn {
            background: #293f91;
            color: white;
        }

        .secondary-btn {
            background: #eee6ff;
            color: #293f91;
        }

    </style>

</head>

<body>

    <div class="success-container">

        <div class="success-icon">
            ✓
        </div>

        <h1>
            Successfully Registered!
        </h1>

        <div class="event-name">
            <?php echo htmlspecialchars($event_name); ?>
        </div>

        <p>
            You have successfully registered for this event.
        </p>

        <div class="buttons">

            <a href="my_registration.php" class="btn primary-btn">
                My Registrations
            </a>

            <a href="student_dashboard.php" class="btn secondary-btn">
                Back to Dashboard
            </a>

        </div>

    </div>

</body>

</html>

<?php

    }

} else {

    echo "Registration failed. Please try again.";

}


$stmt->close();
$conn->close();

?>