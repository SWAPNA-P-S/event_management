<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Organizer Access Check
|--------------------------------------------------------------------------
| Access allowed if:
| 1. User has global organizer role
| OR
| 2. User is a member of an approved organizing team
*/

$is_organizer = false;

if (isset($_SESSION["role"]) && $_SESSION["role"] === "organizer") {
    $is_organizer = true;
} else {

    $stmt = $conn->prepare("
        SELECT tm.team_member_id
        FROM organizer_team_members tm
        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id
        WHERE tm.user_id = ?
          AND r.status = 'approved'
        LIMIT 1
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $is_organizer = true;
        $_SESSION["is_organizer"] = true;
    }

    $stmt->close();
}

if (!$is_organizer) {
    header("Location: ../login.html");
    exit();
}


/*
|--------------------------------------------------------------------------
| Event ID Check
|--------------------------------------------------------------------------
*/

if (!isset($_GET["event_id"]) || !is_numeric($_GET["event_id"])) {
    header("Location: approval_status.php");
    exit();
}

$event_id = (int) $_GET["event_id"];

$message = "";
$message_type = "";


/*
|--------------------------------------------------------------------------
| Fetch Event
|--------------------------------------------------------------------------
| Global organizer:
|   event must belong to organizer_id
|
| Student organizer:
|   event must belong to their approved organizing team
*/

if (isset($_SESSION["role"]) && $_SESSION["role"] === "organizer") {

    $organizer_id = $user_id;

    $stmt = $conn->prepare("
        SELECT *
        FROM events
        WHERE event_id = ?
          AND organizer_id = ?
    ");

    $stmt->bind_param("ii", $event_id, $organizer_id);

} else {

    $stmt = $conn->prepare("
        SELECT e.*
        FROM events e
        INNER JOIN organizer_team_members tm
            ON e.organizer_request_id = tm.request_id
        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id
        WHERE e.event_id = ?
          AND tm.user_id = ?
          AND r.status = 'approved'
        LIMIT 1
    ");

    $stmt->bind_param("ii", $event_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Event not found or you do not have permission to access this event.");
}

$event = $result->fetch_assoc();
$stmt->close();


/*
|--------------------------------------------------------------------------
| Update Planning Details
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $objective = trim($_POST["objective"] ?? "");
    $expected_participants = (int) ($_POST["expected_participants"] ?? 0);
    $guest_details = trim($_POST["guest_details"] ?? "");
    $requirements = trim($_POST["requirements"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Update only if the current user has access to this event
    |--------------------------------------------------------------------------
    */

    if (isset($_SESSION["role"]) && $_SESSION["role"] === "organizer") {

        $stmt = $conn->prepare("
            UPDATE events
            SET objective = ?,
                expected_participants = ?,
                guest_details = ?,
                requirements = ?
            WHERE event_id = ?
              AND organizer_id = ?
        ");

        $stmt->bind_param(
            "sissii",
            $objective,
            $expected_participants,
            $guest_details,
            $requirements,
            $event_id,
            $user_id
        );

    } else {

        $stmt = $conn->prepare("
            UPDATE events e
            INNER JOIN organizer_team_members tm
                ON e.organizer_request_id = tm.request_id
            INNER JOIN organizer_requests r
                ON tm.request_id = r.request_id
            SET e.objective = ?,
                e.expected_participants = ?,
                e.guest_details = ?,
                e.requirements = ?
            WHERE e.event_id = ?
              AND tm.user_id = ?
              AND r.status = 'approved'
        ");

        $stmt->bind_param(
            "sissii",
            $objective,
            $expected_participants,
            $guest_details,
            $requirements,
            $event_id,
            $user_id
        );
    }


    if ($stmt->execute()) {

        $message = "Planning details saved successfully!";
        $message_type = "success";

        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Refresh Event Data
        |--------------------------------------------------------------------------
        */

        if (isset($_SESSION["role"]) && $_SESSION["role"] === "organizer") {

            $stmt2 = $conn->prepare("
                SELECT *
                FROM events
                WHERE event_id = ?
                  AND organizer_id = ?
            ");

            $stmt2->bind_param("ii", $event_id, $user_id);

        } else {

            $stmt2 = $conn->prepare("
                SELECT e.*
                FROM events e
                INNER JOIN organizer_team_members tm
                    ON e.organizer_request_id = tm.request_id
                INNER JOIN organizer_requests r
                    ON tm.request_id = r.request_id
                WHERE e.event_id = ?
                  AND tm.user_id = ?
                  AND r.status = 'approved'
                LIMIT 1
            ");

            $stmt2->bind_param("ii", $event_id, $user_id);
        }

        $stmt2->execute();
        $event = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

    } else {

        $message = "Something went wrong. Please try again.";
        $message_type = "error";

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Event Planning | EventHub</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f7fb;
            color: #172033;
        }

        .navbar {
            background: #071a33;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
        }

        .back-btn {
            text-decoration: none;
            color: white;
            border: 1px solid #4da3ff;
            padding: 9px 16px;
            border-radius: 8px;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 35px auto;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 30px;
            color: #071a33;
        }

        .page-title p {
            color: #667085;
            margin-top: 6px;
        }

        .event-card {
            background: white;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
        }

        .event-card h2 {
            color: #0b5ed7;
            margin-bottom: 18px;
        }

        .event-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-box {
            background: #f7f9fc;
            padding: 15px;
            border-radius: 9px;
        }

        .info-box strong {
            display: block;
            color: #344054;
            margin-bottom: 5px;
        }

        .planning-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
        }

        .planning-card h2 {
            color: #071a33;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #344054;
        }

        input,
        textarea {
            width: 100%;
            padding: 13px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        input:focus,
        textarea:focus {
            border-color: #0b5ed7;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .save-btn {
            background: #0b5ed7;
            color: white;
            border: none;
            padding: 13px 25px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .save-btn:hover {
            background: #084298;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .success {
            background: #e7f7ed;
            color: #137333;
        }

        .error {
            background: #fdecec;
            color: #b42318;
        }

        .next-section {
            margin-top: 25px;
            text-align: right;
        }

        .next-btn {
            display: inline-block;
            text-decoration: none;
            background: #071a33;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
        }

        @media (max-width: 700px) {
            .event-info {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 15px 20px;
            }

            .container {
                width: 94%;
            }
        }
    </style>
</head>

<body>

<div class="navbar">

    <div class="logo">
        EventHub | Organizer
    </div>

    <a href="approval_status.php" class="back-btn">
        ← Approval Status
    </a>

</div>


<div class="container">

    <div class="page-title">

        <h1>Event Planning</h1>

        <p>
            Plan and manage the details of your approved event.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo $message_type; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- Event Information -->

    <div class="event-card">

        <h2>
            <?php echo htmlspecialchars($event["event_name"]); ?>
        </h2>

        <div class="event-info">

            <div class="info-box">

                <strong>Event Date</strong>

                <?php echo htmlspecialchars($event["event_date"]); ?>

            </div>


            <div class="info-box">

                <strong>Time</strong>

                <?php echo htmlspecialchars($event["start_time"]); ?>

                -

                <?php echo htmlspecialchars($event["end_time"]); ?>

            </div>


            <div class="info-box">

                <strong>Event Type</strong>

                <?php echo htmlspecialchars($event["event_type"] ?: "Not specified"); ?>

            </div>


            <div class="info-box">

                <strong>Venue</strong>

                <?php echo htmlspecialchars($event["venue"]); ?>

            </div>

        </div>

    </div>


    <!-- Planning Form -->

    <div class="planning-card">

        <h2>
            Planning Details
        </h2>


        <form method="POST">

            <div class="form-group">

                <label for="objective">
                    Event Objective
                </label>

                <textarea
                    id="objective"
                    name="objective"
                    placeholder="Enter the main objective of the event..."
                    required
                ><?php echo htmlspecialchars($event["objective"] ?? ""); ?></textarea>

            </div>


            <div class="form-group">

                <label for="expected_participants">
                    Expected Participants
                </label>

                <input
                    type="number"
                    id="expected_participants"
                    name="expected_participants"
                    min="1"
                    placeholder="Example: 200"
                    value="<?php echo htmlspecialchars($event["expected_participants"] ?? ""); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="guest_details">
                    Guest Details
                </label>

                <input
                    type="text"
                    id="guest_details"
                    name="guest_details"
                    placeholder="Guest name, designation, organization..."
                    value="<?php echo htmlspecialchars($event["guest_details"] ?? ""); ?>"
                >

            </div>


            <div class="form-group">

                <label for="requirements">
                    Event Requirements
                </label>

                <textarea
                    id="requirements"
                    name="requirements"
                    placeholder="Example: Microphone, projector, chairs, lights, sound system..."
                ><?php echo htmlspecialchars($event["requirements"] ?? ""); ?></textarea>

            </div>


            <button type="submit" class="save-btn">
                Save Planning Details
            </button>

        </form>


        <div class="next-section">

            <a
                href="venue.php?event_id=<?php echo $event_id; ?>"
                class="next-btn"
            >
                Continue to Venue →
            </a>

        </div>

    </div>

</div>

</body>
</html>