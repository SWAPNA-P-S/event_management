<?php

session_start();

require_once "../db.php";


// -------------------------------------------------
// CHECK LOGIN
// -------------------------------------------------

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$user_role = $_SESSION["role"] ?? "";


// -------------------------------------------------
// CHECK ORGANIZER ACCESS
// -------------------------------------------------

$is_organizer = false;


// Existing organizer role
if ($user_role === "organizer") {
    $is_organizer = true;
}


// Approved student-organizer
if ($user_role === "student") {

    $organizer_check = $conn->prepare("
        SELECT tm.team_member_id
        FROM organizer_team_members tm
        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id
        WHERE tm.user_id = ?
          AND r.status = 'approved'
        LIMIT 1
    ");

    $organizer_check->bind_param("i", $user_id);
    $organizer_check->execute();

    $organizer_result = $organizer_check->get_result();

    if ($organizer_result->num_rows > 0) {

        $is_organizer = true;
        $_SESSION["is_organizer"] = true;

    }

    $organizer_check->close();
}


if (!$is_organizer) {
    header("Location: ../login.html");
    exit();
}


// -------------------------------------------------
// CHECK EVENT ID
// -------------------------------------------------

if (!isset($_GET["event_id"]) || !is_numeric($_GET["event_id"])) {
    die("Invalid Event ID.");
}

$event_id = (int) $_GET["event_id"];


// -------------------------------------------------
// FETCH EVENT
// -------------------------------------------------

if ($user_role === "organizer") {

    $stmt = $conn->prepare("
        SELECT
            event_id,
            event_name,
            description,
            event_date,
            start_time,
            end_time,
            venue,
            event_type,
            objective,
            expected_participants,
            guest_details,
            requirements,
            request_status,
            status
        FROM events
        WHERE event_id = ?
          AND organizer_id = ?
    ");

    $stmt->bind_param("ii", $event_id, $user_id);

} else {

    $stmt = $conn->prepare("
        SELECT
            e.event_id,
            e.event_name,
            e.description,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.event_type,
            e.objective,
            e.expected_participants,
            e.guest_details,
            e.requirements,
            e.request_status,
            e.status
        FROM events e
        INNER JOIN organizer_team_members tm
            ON e.organizer_request_id = tm.request_id
        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id
        WHERE e.event_id = ?
          AND tm.user_id = ?
          AND r.status = 'approved'
    ");

    $stmt->bind_param("ii", $event_id, $user_id);
}


$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {
    die("You do not have permission to manage this event.");
}


$event = $result->fetch_assoc();

$stmt->close();


// -------------------------------------------------
// CHECKLIST ARRAY
// -------------------------------------------------

$checklist = [];


// -------------------------------------------------
// 1. EVENT REQUEST SUBMITTED
// -------------------------------------------------

$event_request_completed =
    !empty($event["event_name"]) &&
    !empty($event["event_date"]) &&
    !empty($event["start_time"]) &&
    !empty($event["end_time"]);


$checklist[] = [
    "title" => "Event Request Submitted",
    "description" => "Basic event details have been submitted.",
    "completed" => $event_request_completed
];


// -------------------------------------------------
// 2. ADMIN APPROVAL
// -------------------------------------------------

$approval_completed =
    strtolower($event["request_status"]) === "approved";


$checklist[] = [
    "title" => "Admin Approval Received",
    "description" => "The event request has been approved by the administration.",
    "completed" => $approval_completed
];


// -------------------------------------------------
// 3. EVENT OBJECTIVE
// -------------------------------------------------

$objective_completed =
    !empty(trim($event["objective"]));


$checklist[] = [
    "title" => "Event Objective Completed",
    "description" => "Purpose and objective of the event are provided.",
    "completed" => $objective_completed
];


// -------------------------------------------------
// 4. EXPECTED PARTICIPANTS
// -------------------------------------------------

$participants_completed =
    !empty($event["expected_participants"]) &&
    $event["expected_participants"] > 0;


$checklist[] = [
    "title" => "Expected Participants Entered",
    "description" => "Expected number of participants has been specified.",
    "completed" => $participants_completed
];


// -------------------------------------------------
// 5. GUEST DETAILS
// -------------------------------------------------

$guest_completed =
    !empty(trim($event["guest_details"]));


$checklist[] = [
    "title" => "Guest Details Completed",
    "description" => "Guest / resource person details have been added.",
    "completed" => $guest_completed
];


// -------------------------------------------------
// 6. EVENT REQUIREMENTS
// -------------------------------------------------

$requirements_completed =
    !empty(trim($event["requirements"]));


$checklist[] = [
    "title" => "Event Requirements Finalized",
    "description" => "Technical and other event requirements are provided.",
    "completed" => $requirements_completed
];


// -------------------------------------------------
// 7. VENUE
// -------------------------------------------------

$venue_completed = false;


$checklist[] = [
    "title" => "Venue Confirmed",
    "description" => "Venue booking will be automatically verified after venue module integration.",
    "completed" => $venue_completed,
    "later" => true
];


// -------------------------------------------------
// 8. STAGE
// -------------------------------------------------

$stage_completed = false;


$checklist[] = [
    "title" => "Stage Booked",
    "description" => "Stage booking will be automatically verified through Stage Management.",
    "completed" => $stage_completed,
    "later" => true
];


// -------------------------------------------------
// 9. TIME SLOT
// -------------------------------------------------

$time_slot_completed = false;


$checklist[] = [
    "title" => "Time Slot Confirmed",
    "description" => "Stage time-slot confirmation will be connected later.",
    "completed" => $time_slot_completed,
    "later" => true
];


// -------------------------------------------------
// 10. BROCHURE UPLOADED
// -------------------------------------------------

$brochure_uploaded = false;
$brochure_approved = false;


$brochure_stmt = $conn->prepare("
    SELECT status
    FROM event_brochures
    WHERE event_id = ?
    ORDER BY upload_date DESC
    LIMIT 1
");

$brochure_stmt->bind_param("i", $event_id);
$brochure_stmt->execute();

$brochure_result = $brochure_stmt->get_result();


if ($brochure_result->num_rows > 0) {

    $brochure = $brochure_result->fetch_assoc();

    $brochure_uploaded = true;

    if (
        strtolower($brochure["status"]) === "approved"
    ) {
        $brochure_approved = true;
    }
}


$brochure_stmt->close();


// Brochure Uploaded

$checklist[] = [
    "title" => "Brochure Uploaded",
    "description" => "Event brochure has been uploaded for administrative scrutiny.",
    "completed" => $brochure_uploaded
];


// Brochure Approved

$checklist[] = [
    "title" => "Brochure Approved",
    "description" => "Brochure has been approved after administrative scrutiny.",
    "completed" => $brochure_approved
];


// -------------------------------------------------
// 12. INVITATION
// -------------------------------------------------

$checklist[] = [
    "title" => "Invitation Prepared",
    "description" => "Invitation preparation will be added to the event workflow.",
    "completed" => false,
    "manual" => true
];


// -------------------------------------------------
// 13. FINAL PLAN
// -------------------------------------------------

$checklist[] = [
    "title" => "Final Event Plan Completed",
    "description" => "Final event planning confirmation will be added to the workflow.",
    "completed" => false,
    "manual" => true
];


// -------------------------------------------------
// 14. EVENT PUBLISHED
// -------------------------------------------------

$published =
    strtolower($event["status"]) === "published";


$checklist[] = [
    "title" => "Event Published",
    "description" => "The event is officially published and visible to users.",
    "completed" => $published
];


// -------------------------------------------------
// CALCULATE PROGRESS
// -------------------------------------------------

$total = count($checklist);

$completed = 0;


foreach ($checklist as $item) {

    if ($item["completed"]) {
        $completed++;
    }
}


$progress = $total > 0
    ? round(($completed / $total) * 100)
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Event Checklist | Organizer</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f4f7fb;
    color: #172033;
}


/* HEADER */

.header {
    background: #0b1f3a;
    color: white;

    padding: 20px 35px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 24px;
}

.back-btn {
    text-decoration: none;

    color: white;

    background: #1769e0;

    padding: 10px 18px;

    border-radius: 7px;

    font-size: 14px;
}

.back-btn:hover {
    background: #0d55bd;
}


/* CONTAINER */

.container {
    max-width: 1000px;

    margin: 35px auto;

    padding: 0 20px;
}


/* EVENT CARD */

.event-card {
    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.08);

    margin-bottom: 25px;
}

.event-card h2 {
    color: #0b1f3a;

    margin-bottom: 10px;
}

.event-card p {
    color: #697386;
}


/* EVENT INFO */

.event-info {
    display: flex;

    flex-wrap: wrap;

    gap: 15px;

    margin-top: 20px;
}

.info-box {
    background: #f1f5fb;

    padding: 12px 15px;

    border-radius: 8px;

    min-width: 180px;
}

.info-box strong {
    display: block;

    color: #0b1f3a;

    margin-bottom: 5px;
}


/* PROGRESS */

.progress-card {
    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.08);

    margin-bottom: 25px;
}

.progress-top {
    display: flex;

    justify-content: space-between;

    margin-bottom: 10px;
}

.progress-bar {
    width: 100%;

    height: 14px;

    background: #e1e6ef;

    border-radius: 20px;

    overflow: hidden;
}

.progress-fill {
    height: 100%;

    width: <?php echo $progress; ?>%;

    background: #1769e0;

    border-radius: 20px;

    transition: width 0.4s ease;
}

.progress-text {
    margin-top: 10px;

    color: #697386;

    font-size: 14px;
}


/* CHECKLIST */

.checklist-card {
    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.checklist-card h2 {
    color: #0b1f3a;

    margin-bottom: 20px;
}


/* CHECK ITEM */

.check-item {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 17px 5px;

    border-bottom: 1px solid #e6eaf0;
}

.check-item:last-child {
    border-bottom: none;
}

.left {
    display: flex;

    align-items: center;

    gap: 13px;
}


/* CHECK CIRCLE */

.checkbox {
    width: 21px;

    height: 21px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 13px;

    font-weight: bold;

    flex-shrink: 0;
}

.checkbox.completed {
    background: #dff5e5;

    color: #218838;
}

.checkbox.pending {
    background: #fff2cc;

    color: #9a7200;
}

.checkbox.later {
    background: #e8edf5;

    color: #526174;
}


/* TEXT */

.item-title {
    font-size: 16px;

    font-weight: 600;
}

.item-description {
    font-size: 13px;

    color: #697386;

    margin-top: 4px;
}


/* STATUS */

.status {
    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    white-space: nowrap;
}

.completed-status {
    background: #dff5e5;

    color: #218838;
}

.pending-status {
    background: #fff2cc;

    color: #9a7200;
}

.later-status {
    background: #e8edf5;

    color: #526174;
}

.manual-status {
    background: #e8edf5;

    color: #526174;
}


/* MOBILE */

@media(max-width: 600px) {

    .header {
        padding: 18px;
    }

    .header h1 {
        font-size: 19px;
    }

    .container {
        margin-top: 20px;
    }

    .event-info {
        flex-direction: column;
    }

    .info-box {
        width: 100%;
    }

    .check-item {
        align-items: flex-start;
    }

    .status {
        font-size: 11px;
    }

}

</style>

</head>


<body>


<!-- HEADER -->

<div class="header">

    <h1>
        Organizer Checklist
    </h1>


    <!-- BACK TO ORGANIZER DASHBOARD -->

    <a
        href="organizer_dashboard.php"
        class="back-btn">

        ← Back

    </a>

</div>


<div class="container">


<!-- EVENT DETAILS -->

<div class="event-card">

    <h2>

        <?php
        echo htmlspecialchars(
            $event["event_name"]
        );
        ?>

    </h2>


    <p>
        Track the preparation progress of your event.
    </p>


    <div class="event-info">


        <div class="info-box">

            <strong>
                Date
            </strong>

            <?php
            echo htmlspecialchars(
                $event["event_date"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Time
            </strong>

            <?php

            echo date(
                "h:i A",
                strtotime($event["start_time"])
            );

            ?>

            -

            <?php

            echo date(
                "h:i A",
                strtotime($event["end_time"])
            );

            ?>

        </div>


        <div class="info-box">

            <strong>
                Venue
            </strong>

            <?php

            echo !empty($event["venue"])
                ? htmlspecialchars($event["venue"])
                : "Not confirmed";

            ?>

        </div>


        <div class="info-box">

            <strong>
                Event Type
            </strong>

            <?php

            echo htmlspecialchars(
                $event["event_type"]
            );

            ?>

        </div>


    </div>

</div>


<!-- PROGRESS -->

<div class="progress-card">


    <div class="progress-top">

        <strong>
            Preparation Progress
        </strong>

        <strong>
            <?php echo $progress; ?>%
        </strong>

    </div>


    <div class="progress-bar">

        <div class="progress-fill"></div>

    </div>


    <div class="progress-text">

        <?php echo $completed; ?>

        of

        <?php echo $total; ?>

        checklist items completed.

    </div>


</div>


<!-- CHECKLIST -->

<div class="checklist-card">


    <h2>
        Event Preparation Checklist
    </h2>


<?php foreach ($checklist as $item): ?>


    <?php

    if (!empty($item["later"])) {

        $icon = "•";

        $box_class = "later";

        $status_class = "later-status";

        $status_text = "Coming Later";

    }

    elseif (!empty($item["manual"])) {

        $icon = "•";

        $box_class = "later";

        $status_class = "manual-status";

        $status_text = "Manual";

    }

    elseif ($item["completed"]) {

        $icon = "✓";

        $box_class = "completed";

        $status_class = "completed-status";

        $status_text = "Completed";

    }

    else {

        $icon = "!";

        $box_class = "pending";

        $status_class = "pending-status";

        $status_text = "Pending";

    }

    ?>


    <div class="check-item">


        <div class="left">


            <div class="checkbox <?php echo $box_class; ?>">

                <?php echo $icon; ?>

            </div>


            <div>


                <div class="item-title">

                    <?php

                    echo htmlspecialchars(
                        $item["title"]
                    );

                    ?>

                </div>


                <div class="item-description">

                    <?php

                    echo htmlspecialchars(
                        $item["description"]
                    );

                    ?>

                </div>


            </div>


        </div>


        <span
            class="status <?php echo $status_class; ?>">

            <?php
            echo $status_text;
            ?>

        </span>


    </div>


<?php endforeach; ?>


</div>


</div>


</body>

</html>