<?php

session_start();
require_once "../db.php";


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| ORGANIZER ACCESS CHECK
|--------------------------------------------------------------------------
| Access allowed if:
| 1. User has global organizer role
| OR
| 2. User is a member of an approved organizing team
|--------------------------------------------------------------------------
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
| EVENT ID CHECK
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
| FETCH EVENT
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["role"]) && $_SESSION["role"] === "organizer") {

    $stmt = $conn->prepare("
        SELECT *
        FROM events
        WHERE event_id = ?
          AND organizer_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $event_id, $user_id);

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
| FETCH STAGE BOOKING
|--------------------------------------------------------------------------
| This allows the organizer to see the currently selected
| venue, stage, date, time and stage status.
|--------------------------------------------------------------------------
*/

$stage_booking = null;

$stage_stmt = $conn->prepare("
    SELECT
        sb.*,
        s.stage_name,
        s.location AS stage_location
    FROM stage_bookings sb
    LEFT JOIN stages s
        ON sb.stage_id = s.stage_id
    WHERE sb.event_id = ?
    ORDER BY sb.booking_id DESC
    LIMIT 1
");

$stage_stmt->bind_param("i", $event_id);
$stage_stmt->execute();

$stage_result = $stage_stmt->get_result();

if ($stage_result->num_rows > 0) {

    $stage_booking = $stage_result->fetch_assoc();
}

$stage_stmt->close();


/*
|--------------------------------------------------------------------------
| UPDATE PLANNING DETAILS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $objective = trim($_POST["objective"] ?? "");

    $expected_participants = (int) (
        $_POST["expected_participants"] ?? 0
    );

    $guest_details = trim(
        $_POST["guest_details"] ?? ""
    );

    $requirements = trim(
        $_POST["requirements"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($objective === "") {

        $message = "Please enter the event objective.";
        $message_type = "error";

    } elseif ($expected_participants <= 0) {

        $message = "Please enter a valid number of expected participants.";
        $message_type = "error";

    } else {


        /*
        |--------------------------------------------------------------------------
        | GLOBAL ORGANIZER
        |--------------------------------------------------------------------------
        */

        if (
            isset($_SESSION["role"])
            && $_SESSION["role"] === "organizer"
        ) {

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


        /*
        |--------------------------------------------------------------------------
        | STUDENT ORGANIZER / APPROVED TEAM MEMBER
        |--------------------------------------------------------------------------
        */

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
            | REFRESH EVENT DATA
            |--------------------------------------------------------------------------
            */

            if (
                isset($_SESSION["role"])
                && $_SESSION["role"] === "organizer"
            ) {

                $stmt2 = $conn->prepare("
                    SELECT *
                    FROM events
                    WHERE event_id = ?
                      AND organizer_id = ?
                    LIMIT 1
                ");

                $stmt2->bind_param(
                    "ii",
                    $event_id,
                    $user_id
                );

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

                $stmt2->bind_param(
                    "ii",
                    $event_id,
                    $user_id
                );
            }


            $stmt2->execute();

            $event = $stmt2
                ->get_result()
                ->fetch_assoc();

            $stmt2->close();


        } else {

            $message = "Something went wrong. Please try again.";
            $message_type = "error";

            $stmt->close();
        }
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

/* =========================
   GLOBAL
========================= */

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


/* =========================
   NAVBAR
========================= */

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

.back-btn:hover {

    background: #12345c;
}


/* =========================
   CONTAINER
========================= */

.container {

    width: 90%;

    max-width: 1050px;

    margin: 35px auto;
}


/* =========================
   PAGE TITLE
========================= */

.page-title {

    margin-bottom: 25px;
}

.page-title h1 {

    font-size: 30px;

    color: #071a33;
}

.page-title p {

    color: #667085;

    margin-top: 7px;

    line-height: 1.5;
}


/* =========================
   MESSAGE
========================= */

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


/* =========================
   CARD
========================= */

.card {

    background: white;

    border-radius: 14px;

    padding: 28px;

    margin-bottom: 25px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
}

.card h2 {

    color: #071a33;

    margin-bottom: 20px;
}


/* =========================
   EVENT INFORMATION
========================= */

.event-info {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 15px;
}

.info-box {

    background: #f7f9fc;

    padding: 16px;

    border-radius: 9px;

    border: 1px solid #edf0f5;
}

.info-box strong {

    display: block;

    color: #344054;

    margin-bottom: 6px;

    font-size: 14px;
}

.info-box span {

    color: #172033;

    font-size: 15px;
}


/* =========================
   PLANNING INTRO
========================= */

.planning-intro {

    background: #eff6ff;

    border-left: 4px solid #0b5ed7;

    padding: 16px;

    border-radius: 8px;

    margin-bottom: 25px;

    color: #344054;

    line-height: 1.6;
}


/* =========================
   FORM
========================= */

.form-group {

    margin-bottom: 22px;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;

    color: #344054;
}

.required {

    color: #dc2626;
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

    box-shadow: 0 0 0 2px rgba(11,94,215,0.08);
}

textarea {

    min-height: 115px;

    resize: vertical;
}

.help-text {

    display: block;

    margin-top: 7px;

    font-size: 13px;

    color: #667085;

    line-height: 1.5;
}


/* =========================
   SAVE BUTTON
========================= */

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


/* =========================
   STAGE SUMMARY
========================= */

.stage-summary {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}

.stage-box {

    background: #f8fafc;

    padding: 16px;

    border-radius: 9px;

    border: 1px solid #e2e8f0;
}

.stage-box strong {

    display: block;

    margin-bottom: 6px;

    color: #344054;

    font-size: 14px;
}

.stage-status {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}

.status-pending {

    background: #fef3c7;

    color: #92400e;
}

.status-assigned {

    background: #ffedd5;

    color: #c2410c;
}

.status-booked {

    background: #fee2e2;

    color: #991b1b;
}

.status-rejected {

    background: #f1f5f9;

    color: #475569;
}

.status-available {

    background: #dcfce7;

    color: #166534;
}


/* =========================
   STAGE NOTE
========================= */

.stage-note {

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    padding: 15px;

    border-radius: 8px;

    color: #475569;

    line-height: 1.5;

    margin-bottom: 18px;
}


/* =========================
   NEXT BUTTONS
========================= */

.next-section {

    display: flex;

    justify-content: flex-end;

    gap: 12px;

    flex-wrap: wrap;

    margin-top: 25px;
}

.next-btn {

    display: inline-block;

    text-decoration: none;

    background: #071a33;

    color: white;

    padding: 12px 20px;

    border-radius: 8px;

    font-weight: bold;
}

.next-btn:hover {

    background: #12345c;
}

.secondary-btn {

    background: #e2e8f0;

    color: #334155;
}

.secondary-btn:hover {

    background: #cbd5e1;
}


/* =========================
   PLANNING STEPS
========================= */

.planning-steps {

    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 12px;

    margin-top: 10px;
}

.step {

    padding: 14px;

    background: #f8fafc;

    border-radius: 9px;

    text-align: center;

    border: 1px solid #e2e8f0;
}

.step-number {

    width: 30px;

    height: 30px;

    line-height: 30px;

    margin: 0 auto 8px;

    border-radius: 50%;

    background: #0b5ed7;

    color: white;

    font-weight: bold;
}

.step p {

    font-size: 13px;

    color: #475569;

    line-height: 1.4;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 800px) {

    .event-info,
    .stage-summary {

        grid-template-columns: 1fr;
    }

    .planning-steps {

        grid-template-columns: repeat(2, 1fr);
    }

    .navbar {

        padding: 15px 20px;
    }

    .container {

        width: 94%;
    }
}


@media (max-width: 500px) {

    .planning-steps {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- =========================
     NAVBAR
========================= -->

<div class="navbar">

    <div class="logo">
        EventHub | Organizer
    </div>

    <a
        href="approval_status.php"
        class="back-btn"
    >
        ← Approval Status
    </a>

</div>



<div class="container">


    <!-- =========================
         PAGE TITLE
    ========================= -->

    <div class="page-title">

        <h1>
            Event Planning
        </h1>

        <p>
            Complete the planning details of your approved event
            before proceeding to venue, stage and event preparation.
        </p>

    </div>



    <!-- =========================
         MESSAGE
    ========================= -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo htmlspecialchars($message_type); ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>



    <!-- =========================
         EVENT INFORMATION
    ========================= -->

    <div class="card">

        <h2>
            📋 Event Information
        </h2>


        <div class="event-info">


            <div class="info-box">

                <strong>
                    Event Name
                </strong>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $event["event_name"]
                    );
                    ?>
                </span>

            </div>


            <div class="info-box">

                <strong>
                    Event Type
                </strong>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $event["event_type"] ?: "Not specified"
                    );
                    ?>
                </span>

            </div>


            <div class="info-box">

                <strong>
                    Event Date
                </strong>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $event["event_date"]
                    );
                    ?>
                </span>

            </div>


            <div class="info-box">

                <strong>
                    Event Time
                </strong>

                <span>
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
                </span>

            </div>


            <div class="info-box">

                <strong>
                    Venue
                </strong>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $event["venue"] ?: "Not selected"
                    );
                    ?>
                </span>

            </div>


            <div class="info-box">

                <strong>
                    Registration Fee
                </strong>

                <span>
                    ₹<?php
                    echo number_format(
                        (float)$event["fee"],
                        2
                    );
                    ?>
                </span>

            </div>


        </div>

    </div>



    <!-- =========================
         PLANNING PROCESS
    ========================= -->

    <div class="card">

        <h2>
            📝 Event Planning Process
        </h2>


        <div class="planning-intro">

            Event planning contains the important information required
            to organize the programme properly. Complete the objective,
            expected participants, guest/resource person details and
            technical/infrastructure requirements before continuing
            to the next planning stages.

        </div>


        <div class="planning-steps">


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <p>
                    Event Objective
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <p>
                    Participants & Guests
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <p>
                    Requirements
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    4
                </div>

                <p>
                    Venue & Stage
                </p>

            </div>


        </div>

    </div>



    <!-- =========================
         PLANNING DETAILS
    ========================= -->

    <div class="card">

        <h2>
            🗂️ Planning Details
        </h2>


        <form method="POST">


            <!-- OBJECTIVE -->

            <div class="form-group">

                <label for="objective">

                    Event Objective

                    <span class="required">
                        *
                    </span>

                </label>


                <textarea
                    id="objective"
                    name="objective"
                    placeholder="Enter the main objective and purpose of the event..."
                    required
                ><?php
                echo htmlspecialchars(
                    $event["objective"] ?? ""
                );
                ?></textarea>


                <span class="help-text">

                    Example: To provide students with an opportunity
                    to improve technical knowledge, teamwork and
                    communication skills.

                </span>

            </div>



            <!-- EXPECTED PARTICIPANTS -->

            <div class="form-group">

                <label for="expected_participants">

                    Expected Participants

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="number"
                    id="expected_participants"
                    name="expected_participants"
                    min="1"
                    placeholder="Example: 200"
                    value="<?php
                    echo htmlspecialchars(
                        $event["expected_participants"] ?? ""
                    );
                    ?>"
                    required
                >


                <span class="help-text">

                    Enter the approximate number of participants
                    expected for the event.

                </span>

            </div>



            <!-- GUEST / RESOURCE PERSON -->

            <div class="form-group">

                <label for="guest_details">

                    Guest / Resource Person Details

                </label>


                <textarea
                    id="guest_details"
                    name="guest_details"
                    placeholder="Enter guest/resource person name, designation, organization and other details..."
                ><?php
                echo htmlspecialchars(
                    $event["guest_details"] ?? ""
                );
                ?></textarea>


                <span class="help-text">

                    Include name, designation, organization and
                    other relevant details if applicable.

                </span>

            </div>



            <!-- REQUIREMENTS -->

            <div class="form-group">

                <label for="requirements">

                    Technical & Infrastructure Requirements

                </label>


                <textarea
                    id="requirements"
                    name="requirements"
                    placeholder="Example: Microphone, sound system, projector, stage lights, chairs, tables, internet connection..."
                ><?php
                echo htmlspecialchars(
                    $event["requirements"] ?? ""
                );
                ?></textarea>


                <span class="help-text">

                    Mention the equipment, technical support,
                    furniture, electricity or other infrastructure
                    required for conducting the event.

                </span>

            </div>



            <button
                type="submit"
                class="save-btn"
            >
                💾 Save Planning Details
            </button>


        </form>

    </div>



    <!-- =========================
         STAGE BOOKING SUMMARY
    ========================= -->

    <div class="card">

        <h2>
            🎭 Venue & Stage Status
        </h2>


        <?php if ($stage_booking): ?>


            <div class="stage-summary">


                <div class="stage-box">

                    <strong>
                        Selected Venue
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $stage_booking["stage_location"]
                        ?? $event["venue"]
                        ?? "-"
                    );
                    ?>

                </div>


                <div class="stage-box">

                    <strong>
                        Selected Stage
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $stage_booking["stage_name"]
                        ?? "-"
                    );
                    ?>

                </div>


                <div class="stage-box">

                    <strong>
                        Date
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $stage_booking["booking_date"]
                        ?? "-"
                    );
                    ?>

                </div>


                <div class="stage-box">

                    <strong>
                        Time
                    </strong>

                    <?php

                    if (
                        !empty($stage_booking["start_time"])
                        && !empty($stage_booking["end_time"])
                    ) {

                        echo date(
                            "h:i A",
                            strtotime(
                                $stage_booking["start_time"]
                            )
                        );

                        echo " - ";

                        echo date(
                            "h:i A",
                            strtotime(
                                $stage_booking["end_time"]
                            )
                        );

                    } else {

                        echo "-";
                    }

                    ?>

                </div>


            </div>



            <div class="stage-box">

                <strong>
                    Stage Booking Status
                </strong>


                <?php

                $booking_status =
                    $stage_booking["booking_status"]
                    ?? "pending";


                if ($booking_status === "booked") {

                    echo '
                    <span class="stage-status status-booked">
                        🔴 Booked
                    </span>
                    ';

                } elseif ($booking_status === "assigned") {

                    echo '
                    <span class="stage-status status-assigned">
                        🟠 Assigned
                    </span>
                    ';

                } elseif ($booking_status === "rejected") {

                    echo '
                    <span class="stage-status status-rejected">
                        ❌ Rejected
                    </span>
                    ';

                } else {

                    echo '
                    <span class="stage-status status-pending">
                        🟡 Pending Department Approval
                    </span>
                    ';
                }

                ?>

            </div>


            <div class="stage-note">

                ℹ️ The stage is assigned by the Department Admin/HOD
                after event request approval. Final confirmation
                will be done by Core Admin/PIMC.

            </div>


        <?php else: ?>


            <div class="stage-note">

                ℹ️ No stage has been selected yet.
                Please continue to Venue & Stage Management
                to select an available stage and time slot.

            </div>


        <?php endif; ?>



        <div class="next-section">

            <a
                href="venue.php?event_id=<?php echo $event_id; ?>"
                class="next-btn"
            >
                🎭 Continue to Venue & Stage →
            </a>

        </div>

    </div>



    <!-- =========================
         NEXT WORKFLOW
    ========================= -->

    <div class="card">

        <h2>
            📌 Next Planning Stages
        </h2>


        <div class="planning-steps">


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <p>
                    Venue & Stage
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <p>
                    Event Checklist
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <p>
                    Brochure Preparation
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    4
                </div>

                <p>
                    Core Admin / PIMC Review
                </p>

            </div>


        </div>


        <div class="next-section">

            <a
                href="checklist.php?event_id=<?php echo $event_id; ?>"
                class="next-btn secondary-btn"
            >
                Open Checklist
            </a>


            <a
                href="brochure_upload.php?event_id=<?php echo $event_id; ?>"
                class="next-btn"
            >
                Brochure →
            </a>

        </div>

    </div>


</div>

</body>

</html>