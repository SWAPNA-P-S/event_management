<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "core_admin") {
    header("Location: ../login.html");
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatDateValue($date)
{
    if (empty($date)) {
        return "-";
    }

    $time = strtotime($date);

    return $time ? date("d M Y", $time) : h($date);
}

function formatTimeValue($time)
{
    if (empty($time)) {
        return "-";
    }

    $timestamp = strtotime($time);

    return $timestamp ? date("h:i A", $timestamp) : h($time);
}


/* =========================
   BOOKED EVENTS
========================= */

$events = [];

$sql = "
    SELECT
        sb.booking_id,
        sb.event_id,
        sb.stage_id,
        sb.booking_date,
        sb.start_time,
        sb.end_time,
        sb.buffer_minutes,
        sb.booking_status,
        sb.confirmed_at,

        e.event_name,
        e.event_type,
        e.event_date,
        e.start_time AS event_start_time,
        e.end_time AS event_end_time,
        e.venue,
        e.status AS event_status,
        e.request_status,
        e.publication_status,

        s.stage_name,
        s.location AS stage_location,
        s.capacity AS stage_capacity

    FROM stage_bookings sb

    INNER JOIN events e
        ON e.event_id = sb.event_id

    LEFT JOIN stages s
        ON s.stage_id = sb.stage_id

    WHERE sb.booking_status IN ('assigned', 'booked')

    ORDER BY
        sb.booking_date ASC,
        sb.start_time ASC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

$total_booked = count($events);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Booked Events | Core Admin</title>

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


/* HEADER */

.header {
    background: #0B1F3A;
    color: white;
    padding: 18px 30px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left h1 {
    margin: 0;
    font-size: 22px;
}

.header-left p {
    margin: 5px 0 0;
    color: #DCE4EF;
    font-size: 13px;
}

.back-btn {
    text-decoration: none;
    color: white;

    border: 1px solid rgba(255,255,255,0.3);

    padding: 9px 15px;
    border-radius: 8px;

    font-size: 13px;
}


/* CONTAINER */

.container {
    width: 94%;
    max-width: 1250px;
    margin: 30px auto;
}


/* SUMMARY */

.summary {
    background: white;
    border: 1px solid #DCE4EF;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 22px;
}

.summary h2 {
    margin: 0 0 6px;
    font-size: 19px;
}

.summary p {
    margin: 0;

    color: #667085;
    font-size: 14px;
}

.count {
    display: inline-block;

    margin-top: 14px;

    background: #EAF2FB;
    color: #1E4E8C;

    padding: 8px 13px;

    border-radius: 20px;

    font-size: 13px;
    font-weight: bold;
}


/* EMPTY */

.empty {
    background: white;

    border: 1px solid #DCE4EF;

    border-radius: 12px;

    padding: 55px 20px;

    text-align: center;
}

.empty-icon {
    font-size: 42px;
    margin-bottom: 12px;
}

.empty h3 {
    margin: 0 0 7px;
}

.empty p {
    margin: 0;

    color: #667085;

    font-size: 14px;
}


/* GRID */

.grid {
    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(380px, 1fr));

    gap: 20px;
}


/* CARD */

.card {
    background: white;

    border: 1px solid #DCE4EF;

    border-radius: 13px;

    overflow: hidden;
}


/* CARD HEADER */

.card-top {
    background: #F8FAFD;

    border-bottom: 1px solid #DCE4EF;

    padding: 18px;
}

.card-top h3 {
    margin: 0 0 7px;

    color: #0B1F3A;

    font-size: 18px;
}

.event-id {
    font-size: 12px;
    color: #667085;
}

.status {
    display: inline-block;

    margin-top: 10px;

    padding: 6px 10px;

    border-radius: 15px;

    background: #E8F7EE;

    color: #176B3A;

    font-size: 11px;

    font-weight: bold;
}


/* DETAILS */

.details {
    padding: 18px;
}

.detail-row {
    display: flex;

    gap: 12px;

    margin-bottom: 12px;

    font-size: 13px;
}

.detail-label {
    width: 125px;

    flex-shrink: 0;

    color: #667085;

    font-weight: 600;
}

.detail-value {
    color: #172033;
}


/* SECTION */

.section-title {
    margin-top: 20px;
    margin-bottom: 12px;

    padding-bottom: 8px;

    border-bottom: 1px solid #E8EDF4;

    color: #0B1F3A;

    font-size: 14px;

    font-weight: bold;
}


/* RESPONSIVE */

@media (max-width: 650px) {

    .header {
        padding: 16px;
        gap: 10px;
    }

    .header-left h1 {
        font-size: 18px;
    }

    .container {
        width: 92%;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .detail-row {
        flex-direction: column;
        gap: 3px;
    }

    .detail-label {
        width: auto;
    }
}

</style>

</head>


<body>


<header class="header">

    <div class="header-left">

        <h1>Booked Events</h1>

        <p>
            Core Admin / Event Monitoring
        </p>

    </div>


    <a
        href="core_admin_dashboard.php"
        class="back-btn">

        ← Back to Dashboard

    </a>

</header>



<div class="container">


    <div class="summary">

        <h2>
            Booked Events & Stage Assignments
        </h2>

        <p>
            View events that currently have an assigned or booked stage.
        </p>


        <span class="count">

            <?php echo $total_booked; ?>

            Booked Events

        </span>

    </div>



    <?php if (empty($events)): ?>


        <div class="empty">

            <div class="empty-icon">
                📅
            </div>


            <h3>
                No Booked Events
            </h3>


            <p>
                There are currently no events with an assigned or booked stage.
            </p>

        </div>


    <?php else: ?>


        <div class="grid">


            <?php foreach ($events as $event): ?>


                <div class="card">


                    <div class="card-top">


                        <h3>

                            <?php
                            echo h(
                                $event["event_name"]
                            );
                            ?>

                        </h3>


                        <div class="event-id">

                            Event ID:
                            <?php
                            echo h(
                                $event["event_id"]
                            );
                            ?>

                        </div>


                        <span class="status">

                            <?php
                            echo h(
                                ucfirst(
                                    $event["booking_status"]
                                )
                            );
                            ?>

                        </span>


                    </div>



                    <div class="details">


                        <div class="section-title">
                            Event Details
                        </div>


                        <div class="detail-row">

                            <div class="detail-label">
                                Event Type
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["event_type"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Event Date
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatDateValue(
                                    $event["event_date"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Event Time
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatTimeValue(
                                    $event["event_start_time"]
                                );
                                ?>

                                -

                                <?php
                                echo formatTimeValue(
                                    $event["event_end_time"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Venue
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["venue"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Event Status
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["event_status"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Request Status
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["request_status"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Publication
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["publication_status"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="section-title">
                            Stage Booking
                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Stage
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["stage_name"]
                                    ?: "Not specified"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Stage Location
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["stage_location"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Booking Date
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatDateValue(
                                    $event["booking_date"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Stage Time
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatTimeValue(
                                    $event["start_time"]
                                );
                                ?>

                                -

                                <?php
                                echo formatTimeValue(
                                    $event["end_time"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Booking Status
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $event["booking_status"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Confirmed
                            </div>

                            <div class="detail-value">

                                <?php
                                echo $event["confirmed_at"]
                                    ? formatDateValue(
                                        $event["confirmed_at"]
                                    )
                                    : "Not confirmed";
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Capacity
                            </div>

                            <div class="detail-value">

                                <?php
                                echo $event["stage_capacity"]
                                    ? h(
                                        $event["stage_capacity"]
                                    )
                                    : "-";
                                ?>

                            </div>

                        </div>


                    </div>

                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>


</body>

</html>