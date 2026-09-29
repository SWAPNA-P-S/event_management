<?php

session_start();

require_once "../db.php";


/* =========================================================
   CORE ADMIN ACCESS
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$role = $_SESSION["role"] ?? "";

if ($role !== "core_admin" && $role !== "admin") {
    header("Location: ../student_dashboard.php");
    exit();
}


/* =========================================================
   EVENTS WAITING FOR CORE ADMIN REVIEW
========================================================= */

$events = [];

$result = $conn->query("
    SELECT
        e.event_id,
        e.event_name,
        e.event_type,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.request_status,
        e.status,
        e.publication_status,

        sb.booking_id,
        sb.booking_date,
        sb.start_time AS booking_start_time,
        sb.end_time AS booking_end_time,
        sb.booking_status,

        s.stage_name,
        s.location AS stage_location

    FROM stage_bookings sb

    INNER JOIN events e
        ON sb.event_id = e.event_id

    LEFT JOIN stages s
        ON sb.stage_id = s.stage_id

    WHERE sb.booking_status = 'assigned'

    ORDER BY
        sb.booking_date ASC,
        sb.start_time ASC,
        e.event_id ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}


/* =========================================================
   HELPERS
========================================================= */

function format_date($date)
{
    if (empty($date)) {
        return "-";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars($date);
    }

    return date("d M Y", $timestamp);
}


function format_time($time)
{
    if (empty($time)) {
        return "-";
    }

    $timestamp = strtotime($time);

    if (!$timestamp) {
        return htmlspecialchars($time);
    }

    return date("h:i A", $timestamp);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Event Review | Core Admin</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #F4F7FB;
    color: #172033;
}


/* =========================================================
   HEADER
========================================================= */

.header {
    background: #0B1F3A;
    color: white;
    padding: 20px 30px;
}

.header-inner {
    max-width: 1150px;
    margin: auto;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    margin: 0;
    font-size: 24px;
}

.header p {
    margin: 6px 0 0;
    color: #C9D7E8;
    font-size: 13px;
}

.back-link {
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    padding: 9px 13px;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 6px;
}

.back-link:hover {
    background: rgba(255,255,255,0.1);
}


/* =========================================================
   MAIN
========================================================= */

.main {
    max-width: 1150px;
    margin: auto;
    padding: 30px;
}


/* =========================================================
   PAGE TITLE
========================================================= */

.page-title {
    margin-bottom: 25px;
}

.page-title h2 {
    margin: 0 0 7px;
    color: #0B1F3A;
    font-size: 22px;
}

.page-title p {
    margin: 0;
    color: #68778C;
    font-size: 14px;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary {
    background: #EAF2FB;
    border-left: 5px solid #1E4E8C;
    padding: 15px 18px;
    border-radius: 7px;
    margin-bottom: 25px;
    color: #173F70;
    font-size: 14px;
}


/* =========================================================
   EVENT CARD
========================================================= */

.event-card {
    background: white;
    border: 1px solid #DCE4EF;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 18px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.04);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 17px;
}

.event-name {
    color: #0B1F3A;
    font-size: 19px;
    font-weight: bold;
}

.event-id {
    margin-top: 5px;
    color: #8190A4;
    font-size: 12px;
}

.status {
    display: inline-block;
    background: #FFF3CD;
    color: #856404;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
}


/* =========================================================
   DETAILS
========================================================= */

.details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 13px;
    margin-bottom: 18px;
}

.detail {
    background: #FAFCFF;
    border: 1px solid #E1E8F0;
    border-radius: 7px;
    padding: 12px;
}

.detail-label {
    color: #8190A4;
    font-size: 11px;
    margin-bottom: 5px;
}

.detail-value {
    color: #172033;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
}


/* =========================================================
   STAGE BOX
========================================================= */

.stage-box {
    background: #F7FAFE;
    border: 1px solid #DCE4EF;
    border-radius: 8px;
    padding: 15px;
    margin-top: 5px;
}

.stage-title {
    color: #0B1F3A;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 12px;
}

.stage-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}


/* =========================================================
   BUTTON
========================================================= */

.review-button {
    display: inline-block;
    margin-top: 18px;
    background: #1E4E8C;
    color: white;
    text-decoration: none;
    padding: 10px 17px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: bold;
}

.review-button:hover {
    background: #163B6C;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    background: white;
    border: 1px solid #DCE4EF;
    border-radius: 12px;
    padding: 45px 20px;
    text-align: center;
    color: #68778C;
}

.empty-icon {
    font-size: 38px;
    margin-bottom: 12px;
}

.empty h3 {
    margin: 0 0 7px;
    color: #0B1F3A;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 850px) {

    .details,
    .stage-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .event-header {
        flex-direction: column;
    }

}

@media (max-width: 600px) {

    .header-inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .main {
        padding: 20px 15px;
    }

    .details,
    .stage-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <div class="header-inner">

        <div>

            <h1>
                📋 Event Review
            </h1>

            <p>
                Core Admin / PIMC final event scrutiny
            </p>

        </div>

        <a
            href="core_admin_dashboard.php"
            class="back-link"
        >
            ← Dashboard
        </a>

    </div>

</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


<div class="page-title">

    <h2>
        Events Waiting for Review
    </h2>

    <p>
        Review events after Department / HOD approval and stage assignment.
    </p>

</div>


<!-- =========================================================
     SUMMARY
========================================================= -->

<div class="summary">

    <strong>
        <?php echo count($events); ?>
    </strong>

    event(s) are currently waiting for Core Admin review.

</div>


<?php if (empty($events)): ?>


<!-- =========================================================
     EMPTY
========================================================= -->

<div class="empty">

    <div class="empty-icon">
        ✓
    </div>

    <h3>
        No Events Waiting for Review
    </h3>

    <p>
        There are currently no newly assigned events
        waiting for Core Admin scrutiny.
    </p>

</div>


<?php else: ?>


<!-- =========================================================
     EVENTS
========================================================= -->

<?php foreach ($events as $event): ?>


<div class="event-card">


    <div class="event-header">

        <div>

            <div class="event-name">

                <?php
                echo htmlspecialchars(
                    $event["event_name"]
                );
                ?>

            </div>

            <div class="event-id">

                Event ID:
                <?php
                echo intval(
                    $event["event_id"]
                );
                ?>

                <?php if (!empty($event["event_type"])): ?>

                    &nbsp; | &nbsp;

                    <?php
                    echo htmlspecialchars(
                        $event["event_type"]
                    );
                    ?>

                <?php endif; ?>

            </div>

        </div>


        <span class="status">

            🟠 Stage Assigned – Review Required

        </span>

    </div>


    <!-- EVENT DETAILS -->

    <div class="details">


        <div class="detail">

            <div class="detail-label">
                Event Date
            </div>

            <div class="detail-value">

                <?php
                echo format_date(
                    $event["event_date"]
                );
                ?>

            </div>

        </div>


        <div class="detail">

            <div class="detail-label">
                Event Time
            </div>

            <div class="detail-value">

                <?php

                echo format_time(
                    $event["start_time"]
                );

                echo " - ";

                echo format_time(
                    $event["end_time"]
                );

                ?>

            </div>

        </div>


        <div class="detail">

            <div class="detail-label">
                Venue / Hall
            </div>

            <div class="detail-value">

                <?php
                echo htmlspecialchars(
                    $event["venue"] ?? "-"
                );
                ?>

            </div>

        </div>


        <div class="detail">

            <div class="detail-label">
                Request Status
            </div>

            <div class="detail-value">

                <?php
                echo htmlspecialchars(
                    $event["request_status"] ?? "-"
                );
                ?>

            </div>

        </div>


        <div class="detail">

            <div class="detail-label">
                Event Status
            </div>

            <div class="detail-value">

                <?php
                echo htmlspecialchars(
                    $event["status"] ?? "-"
                );
                ?>

            </div>

        </div>


        <div class="detail">

            <div class="detail-label">
                Publication
            </div>

            <div class="detail-value">

                <?php
                echo htmlspecialchars(
                    $event["publication_status"] ?? "-"
                );
                ?>

            </div>

        </div>


    </div>


    <!-- STAGE DETAILS -->

    <div class="stage-box">

        <div class="stage-title">

            🏟️ Stage Assignment

        </div>


        <div class="stage-grid">


            <div class="detail">

                <div class="detail-label">
                    Stage
                </div>

                <div class="detail-value">

                    <?php
                    echo htmlspecialchars(
                        $event["stage_name"] ?? "-"
                    );
                    ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Stage Location
                </div>

                <div class="detail-value">

                    <?php
                    echo htmlspecialchars(
                        $event["stage_location"] ?? "-"
                    );
                    ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Booking Date
                </div>

                <div class="detail-value">

                    <?php
                    echo format_date(
                        $event["booking_date"]
                    );
                    ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Booking Time
                </div>

                <div class="detail-value">

                    <?php

                    echo format_time(
                        $event["booking_start_time"]
                    );

                    echo " - ";

                    echo format_time(
                        $event["booking_end_time"]
                    );

                    ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Booking Status
                </div>

                <div class="detail-value">

                    <?php
                    echo htmlspecialchars(
                        $event["booking_status"] ?? "-"
                    );
                    ?>

                </div>

            </div>


        </div>

    </div>


    <!-- REVIEW -->

    <a
        href="core_event_review.php?event_id=<?php
            echo intval(
                $event["event_id"]
            );
        ?>"
        class="review-button"
    >

        🔍 Open Full Event Review →

    </a>


</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


</body>

</html>