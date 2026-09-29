<?php

session_start();
require_once "../db.php";


/* =========================================================
   HELPER FUNCTION
========================================================= */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];


/* =========================================================
   APPROVED ORGANIZER TEAM CHECK
========================================================= */

$approved_team_ids = [];

$stmt = $conn->prepare("
    SELECT DISTINCT otm.request_id
    FROM organizer_team_members otm
    INNER JOIN organizer_requests orq
        ON orq.request_id = otm.request_id
    WHERE otm.user_id = ?
      AND orq.status = 'approved'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $approved_team_ids[] = (int)$row["request_id"];
}

$stmt->close();


/*
   User must belong to an approved organizing team.
*/

if (empty($approved_team_ids)) {
    header("Location: ../student_dashboard.php");
    exit();
}


/* =========================================================
   GET COMPLETED EVENTS
========================================================= */

$placeholders = implode(
    ",",
    array_fill(0, count($approved_team_ids), "?")
);

$types = str_repeat("i", count($approved_team_ids));


$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.organizer_request_id,

        er.report_id,
        er.status AS report_status,
        er.correction_reason

    FROM events e

    LEFT JOIN event_reports er
        ON e.event_id = er.event_id
        AND er.organizer_id = ?

    WHERE e.organizer_request_id IN ($placeholders)

      AND (
            e.event_date < CURDATE()

            OR

            (
                e.event_date = CURDATE()
                AND e.end_time <= CURTIME()
            )
          )

    ORDER BY e.event_date DESC
";


$stmt = $conn->prepare($sql);


$event_types = "i" . $types;


$event_params = array_merge(
    [$user_id],
    $approved_team_ids
);


$stmt->bind_param(
    $event_types,
    ...$event_params
);


$stmt->execute();

$events = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Event Reports</title>


<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f4f7fb;

    color: #172033;
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    max-width: 1150px;

    margin: 35px auto;

    padding: 20px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back {

    display: inline-block;

    margin-bottom: 18px;

    color: #1e4e8c;

    text-decoration: none;

    font-weight: 700;

    font-size: 16px;
}

.back:hover {

    text-decoration: underline;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    background: #0b1f3a;

    color: white;

    padding: 30px 35px;

    border-radius: 18px;

    margin-bottom: 28px;

    box-shadow: 0 8px 25px rgba(11,31,58,0.15);
}

.page-header h1 {

    margin: 0 0 8px;

    font-size: 30px;
}

.page-header p {

    margin: 0;

    color: #d9e5f5;

    font-size: 15px;
}


/* =========================================================
   MAIN CARD
========================================================= */

.card {

    background: white;

    padding: 30px;

    border-radius: 16px;

    border: 1px solid #dce4ef;

    box-shadow: 0 6px 22px rgba(11,31,58,0.07);
}

.card h2 {

    margin: 0;

    color: #0b1f3a;

    padding-bottom: 15px;

    border-bottom: 2px solid #eaf2fb;

    font-size: 24px;
}


/* =========================================================
   EVENT BOX
========================================================= */

.event-box {

    border: 1px solid #dce4ef;

    border-radius: 14px;

    padding: 22px;

    margin-top: 20px;

    background: #ffffff;

    transition: 0.2s ease;
}

.event-box:hover {

    border-color: #b9cee6;

    box-shadow: 0 6px 18px rgba(11,31,58,0.08);
}


/* =========================================================
   EVENT TITLE
========================================================= */

.event-box h3 {

    margin: 0 0 12px;

    color: #0b1f3a;

    font-size: 20px;
}


/* =========================================================
   EVENT DETAILS
========================================================= */

.event-details {

    color: #5d6b7e;

    line-height: 1.8;

    font-size: 14px;

    margin-bottom: 18px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 15px;
}

.status-pending {

    background: #fef3c7;

    color: #92400e;
}

.status-approved {

    background: #dcfce7;

    color: #166534;
}

.status-correction {

    background: #fee2e2;

    color: #991b1b;
}


/* =========================================================
   BUTTON AREA
========================================================= */

.button-area {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


/* =========================================================
   GENERATE REPORT BUTTON
========================================================= */

.generate-btn {

    display: inline-block;

    background: #0b1f3a;

    color: white;

    padding: 12px 20px;

    border-radius: 9px;

    text-decoration: none;

    font-weight: 700;

    font-size: 15px;

    transition: 0.2s ease;
}

.generate-btn:hover {

    background: #1e4e8c;

    transform: translateY(-1px);
}


/* =========================================================
   VIEW REPORT BUTTON
========================================================= */

.view-btn {

    display: inline-block;

    background: #eaf2fb;

    color: #0b1f3a;

    padding: 12px 20px;

    border-radius: 9px;

    text-decoration: none;

    font-weight: 700;

    font-size: 15px;

    border: 1px solid #c9ddf1;
}

.view-btn:hover {

    background: #dceafa;
}


/* =========================================================
   CORRECTION MESSAGE
========================================================= */

.correction-box {

    margin-top: 15px;

    padding: 14px;

    background: #fff7ed;

    border-left: 4px solid #f97316;

    border-radius: 7px;

    color: #9a3412;

    line-height: 1.6;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 45px 20px;

    color: #64748b;

    background: #f8fafc;

    border: 1px dashed #cbd5e1;

    border-radius: 12px;

    margin-top: 20px;
}

.empty-icon {

    font-size: 40px;

    margin-bottom: 10px;
}

.empty h3 {

    margin: 5px 0 8px;

    color: #334155;
}

.empty p {

    margin: 0;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .container {

        padding: 12px;

        margin: 15px auto;
    }

    .page-header {

        padding: 22px;
    }

    .page-header h1 {

        font-size: 24px;
    }

    .card {

        padding: 20px;
    }

    .button-area {

        flex-direction: column;
    }

    .generate-btn,
    .view-btn {

        width: 100%;

        text-align: center;
    }

}

</style>

</head>


<body>


<div class="container">


<!-- =====================================================
     BACK TO DASHBOARD
====================================================== -->

<a
    href="organizer_dashboard.php"
    class="back"
>
    ← Back to Organizer Dashboard
</a>


<!-- =====================================================
     PAGE HEADER
====================================================== -->

<div class="page-header">

    <h1>
        Event Reports
    </h1>

    <p>
        Generate final reports for your completed events.
    </p>

</div>


<!-- =====================================================
     COMPLETED EVENTS
====================================================== -->

<div class="card">

    <h2>
        Completed Events
    </h2>


    <?php if ($events->num_rows > 0): ?>


        <?php while ($event = $events->fetch_assoc()): ?>


            <div class="event-box">


                <!-- EVENT NAME -->

                <h3>

                    <?= h($event["event_name"]) ?>

                </h3>


                <!-- EVENT DETAILS -->

                <div class="event-details">

                    📅

                    <?= h(
                        date(
                            "d M Y",
                            strtotime($event["event_date"])
                        )
                    ) ?>


                    <br>


                    ⏰

                    <?= h(
                        date(
                            "h:i A",
                            strtotime($event["start_time"])
                        )
                    ) ?>

                    -

                    <?= h(
                        date(
                            "h:i A",
                            strtotime($event["end_time"])
                        )
                    ) ?>


                    <br>


                    📍

                    <?= h(
                        $event["venue"]
                        ?: "Venue not specified"
                    ) ?>

                </div>


                <!-- =================================================
                     REPORT STATUS
                ================================================== -->

                <?php if ($event["report_status"] === "pending"): ?>

                    <div>

                        <span class="status status-pending">

                            Report Pending Review

                        </span>

                    </div>


                <?php elseif ($event["report_status"] === "approved"): ?>

                    <div>

                        <span class="status status-approved">

                            Report Approved

                        </span>

                    </div>


                <?php elseif ($event["report_status"] === "correction_required"): ?>

                    <div>

                        <span class="status status-correction">

                            Correction Required

                        </span>

                    </div>


                <?php endif; ?>


                <!-- =================================================
                     BUTTONS
                ================================================== -->

                <div class="button-area">


                    <?php if (
                        $event["report_status"] === null
                        ||
                        $event["report_status"] === "correction_required"
                    ): ?>


                        <!--
                            THIS IS THE MAIN REPORT BUTTON.

                            It opens report-generator.php
                            with the selected event ID.
                        -->

                        <a
                            href="report-generator.php?event_id=<?= (int)$event["event_id"] ?>"
                            class="generate-btn"
                        >

                            📄 Generate Full Report

                        </a>


                    <?php else: ?>


                        <!-- Already submitted/approved -->

                        <a
                            href="report-generator.php?event_id=<?= (int)$event["event_id"] ?>"
                            class="view-btn"
                        >

                            👁 View Full Report

                        </a>


                    <?php endif; ?>


                </div>


                <!-- =================================================
                     CORRECTION REASON
                ================================================== -->

                <?php if (
                    $event["report_status"] === "correction_required"
                    &&
                    !empty($event["correction_reason"])
                ): ?>


                    <div class="correction-box">

                        <strong>
                            Admin Correction Request
                        </strong>

                        <br><br>

                        <?= nl2br(
                            h($event["correction_reason"])
                        ) ?>

                    </div>


                <?php endif; ?>


            </div>


        <?php endwhile; ?>


    <?php else: ?>


        <div class="empty">

            <div class="empty-icon">
                📋
            </div>

            <h3>
                No Completed Events
            </h3>

            <p>
                Your completed events will appear here
                when they are ready for final report generation.
            </p>

        </div>


    <?php endif; ?>


</div>


</div>


</body>

</html>


<?php

$stmt->close();

$conn->close();

?>