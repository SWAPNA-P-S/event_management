<?php

session_start();

require_once "../db.php";

/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.html");

    exit();

}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];


/* =========================================================
   CHECK ORGANIZER ACCESS
========================================================= */

$organizer_access = false;

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

    $organizer_access = true;

}

$stmt->close();


/* Existing organizer users */

if ($_SESSION["role"] === "organizer") {

    $organizer_access = true;

}


/* No organizer access */

if (!$organizer_access) {

    header("Location: ../student_dashboard.php");

    exit();

}


/*
 * IMPORTANT:
 * User remains a student in the database.
 * Organizer access is an additional privilege.
 */

$_SESSION["is_organizer"] = true;


/* =========================================================
   GET APPROVED TEAMS OF CURRENT USER
========================================================= */

$my_request_ids = [];
$my_team_names = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        r.request_id,
        r.team_name
    FROM organizer_team_members tm
    INNER JOIN organizer_requests r
        ON tm.request_id = r.request_id
    WHERE tm.user_id = ?
      AND r.status = 'approved'
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $my_request_ids[] = (int)$row["request_id"];

    $my_team_names[] = $row["team_name"];

}

$stmt->close();


/* =========================================================
   MY EVENTS
   Events belonging to the user's approved team
========================================================= */

$my_events = [];

if (count($my_request_ids) > 0) {

    $placeholders = implode(
        ",",
        array_fill(0, count($my_request_ids), "?")
    );

    $types = str_repeat(
        "i",
        count($my_request_ids)
    );

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
            fee,
            status,
            request_status,
            publication_status,
            organizer_request_id
        FROM events
        WHERE organizer_request_id IN ($placeholders)
        ORDER BY event_date ASC, start_time ASC
    ");

    $stmt->bind_param(
        $types,
        ...$my_request_ids
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $my_events[] = $row;

    }

    $stmt->close();

}


/* =========================================================
   OTHER APPROVED EVENTS
   Events belonging to other organizer teams
========================================================= */

$other_events = [];

if (count($my_request_ids) > 0) {

    $placeholders = implode(
        ",",
        array_fill(0, count($my_request_ids), "?")
    );

    $types = str_repeat(
        "i",
        count($my_request_ids)
    );

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
            e.fee,
            e.status,
            e.request_status,
            e.organizer_request_id,
            r.team_name
        FROM events e
        LEFT JOIN organizer_requests r
            ON e.organizer_request_id = r.request_id
        WHERE e.request_status = 'approved'
          AND e.status IN ('approved', 'upcoming')
          AND (
                e.organizer_request_id IS NULL
                OR e.organizer_request_id NOT IN ($placeholders)
              )
        ORDER BY e.event_date ASC, e.start_time ASC
    ");

    $stmt->bind_param(
        $types,
        ...$my_request_ids
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $other_events[] = $row;

    }

    $stmt->close();

} else {

    $result = $conn->query("
        SELECT
            e.event_id,
            e.event_name,
            e.description,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.event_type,
            e.fee,
            e.status,
            e.request_status,
            e.organizer_request_id,
            r.team_name
        FROM events e
        LEFT JOIN organizer_requests r
            ON e.organizer_request_id = r.request_id
        WHERE e.request_status = 'approved'
          AND e.status IN ('approved', 'upcoming')
        ORDER BY e.event_date ASC, e.start_time ASC
    ");

    while ($row = $result->fetch_assoc()) {

        $other_events[] = $row;

    }

}


/* =========================================================
   DASHBOARD COUNTS
========================================================= */

$total_events = count($my_events);

$pending_requests = 0;

$approved_events = 0;

$total_participants = 0;


/* Count my approved and pending events */

foreach ($my_events as $event) {

    if ($event["request_status"] === "pending") {

        $pending_requests++;

    }

    if ($event["request_status"] === "approved") {

        $approved_events++;

    }

}


/* =========================================================
   TOTAL PARTICIPANTS FOR MY EVENTS
========================================================= */

if (count($my_request_ids) > 0) {

    $placeholders = implode(
        ",",
        array_fill(0, count($my_request_ids), "?")
    );

    $types = str_repeat(
        "i",
        count($my_request_ids)
    );

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM registrations r
        INNER JOIN events e
            ON r.event_id = e.event_id
        WHERE e.organizer_request_id IN ($placeholders)
    ");

    $stmt->bind_param(
        $types,
        ...$my_request_ids
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $total_participants =
        $result->fetch_assoc()["total"];

    $stmt->close();

}


/* =========================================================
   EVENTS FOR BROCHURE / CHECKLIST
   ONLY MY EVENTS
========================================================= */

$organizer_events = $my_events;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Organizer Dashboard</title>


<style>

/* =========================
   GENERAL
========================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f4f7fb;
    color: #1f2937;
}


/* =========================
   SIDEBAR
========================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 250px;
    height: 100vh;

    background: #172554;
    color: white;

    padding: 25px 18px;

    overflow-y: auto;
}

.logo {
    font-size: 24px;
    font-weight: bold;

    text-align: center;

    margin-bottom: 35px;
}

.role {
    text-align: center;

    font-size: 13px;

    color: #bfdbfe;

    margin-top: -25px;
    margin-bottom: 30px;
}

.sidebar a {
    display: block;

    text-decoration: none;

    color: #dbeafe;

    padding: 13px 15px;

    margin-bottom: 8px;

    border-radius: 8px;

    font-size: 15px;

    cursor: pointer;
}

.sidebar a:hover,
.sidebar a.active {
    background: #2563eb;
    color: white;
}

.student-area {
    background: #0f766e !important;
}

.student-area:hover {
    background: #115e59 !important;
}

.logout {
    margin-top: 30px;

    background: #dc2626 !important;
}

.logout:hover {
    background: #b91c1c !important;
}


/* =========================
   MAIN
========================= */

.main {
    margin-left: 250px;

    padding: 30px;
}

.topbar {
    background: white;

    padding: 20px 25px;

    border-radius: 12px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.06);
}

.topbar h1 {
    font-size: 25px;

    color: #172554;
}

.welcome {
    color: #64748b;

    font-size: 14px;

    margin-top: 5px;
}

.organizer-name {
    font-weight: bold;

    color: #2563eb;
}


/* =========================
   APPROVED MESSAGE
========================= */

.approved-message {
    background: #dcfce7;

    border: 1px solid #86efac;

    color: #166534;

    padding: 16px 20px;

    border-radius: 10px;

    margin-bottom: 25px;

    font-size: 15px;

    line-height: 1.6;
}

.approved-message strong {
    font-size: 17px;
}


/* =========================
   CARDS
========================= */

.cards {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 30px;
}

.card {
    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.06);
}

.card h3 {
    font-size: 14px;

    color: #64748b;

    margin-bottom: 12px;
}

.number {
    font-size: 30px;

    font-weight: bold;

    color: #172554;
}


/* =========================
   SECTIONS
========================= */

.section {
    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.06);
}

.section h2 {
    color: #172554;

    margin-bottom: 18px;
}


/* =========================
   QUICK ACTIONS
========================= */

.quick-actions {
    display: flex;

    gap: 15px;

    flex-wrap: wrap;
}

.action {
    background: #2563eb;

    color: white;

    text-decoration: none;

    padding: 13px 20px;

    border-radius: 8px;

    font-size: 14px;

    border: none;

    cursor: pointer;
}

.action:hover {
    background: #1d4ed8;
}


/* =========================
   EVENT LIST
========================= */

.event-list {
    display: grid;

    gap: 15px;
}

.event-box {
    border: 1px solid #e2e8f0;

    border-radius: 10px;

    padding: 18px;

    background: #f8fafc;
}

.event-box h3 {
    color: #172554;

    margin-bottom: 10px;

    font-size: 18px;
}

.event-info {
    color: #475569;

    font-size: 14px;

    line-height: 1.8;
}

.event-team {
    display: inline-block;

    margin-top: 10px;

    padding: 5px 10px;

    background: #dbeafe;

    color: #1d4ed8;

    border-radius: 6px;

    font-size: 12px;

    font-weight: bold;
}

.event-buttons {
    margin-top: 15px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.view-btn,
.register-btn,
.manage-btn {
    display: inline-block;

    padding: 9px 14px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 13px;

    font-weight: bold;
}

.view-btn {
    background: #e2e8f0;

    color: #334155;
}

.view-btn:hover {
    background: #cbd5e1;
}

.register-btn {
    background: #16a34a;

    color: white;
}

.register-btn:hover {
    background: #15803d;
}

.manage-btn {
    background: #2563eb;

    color: white;
}

.manage-btn:hover {
    background: #1d4ed8;
}

.empty {
    color: #64748b;

    font-size: 14px;

    line-height: 1.6;
}


/* =========================
   OTHER EVENT NOTICE
========================= */

.view-only-note {
    background: #eff6ff;

    border: 1px solid #bfdbfe;

    color: #1e40af;

    padding: 12px 15px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 14px;
}


/* =========================
   RECENT ACTIVITY
========================= */

.empty strong {
    color: #172554;
}


/* =========================
   EVENT SELECT MODAL
========================= */

.modal {
    display: none;

    position: fixed;

    z-index: 1000;

    left: 0;
    top: 0;

    width: 100%;
    height: 100%;

    background: rgba(15, 23, 42, 0.65);

    align-items: center;

    justify-content: center;
}

.modal-box {
    background: white;

    width: 420px;

    max-width: 90%;

    padding: 30px;

    border-radius: 15px;

    box-shadow:
        0 10px 35px rgba(0,0,0,0.2);
}

.modal-box h2 {
    color: #172554;

    margin-bottom: 10px;
}

.modal-box p {
    color: #64748b;

    font-size: 14px;

    margin-bottom: 20px;
}

.modal-box select {
    width: 100%;

    padding: 12px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    font-size: 14px;

    margin-bottom: 18px;

    outline: none;
}

.modal-buttons {
    display: flex;

    gap: 10px;

    justify-content: flex-end;
}

.modal-btn {
    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    cursor: pointer;

    font-size: 14px;
}

.cancel-btn {
    background: #e2e8f0;

    color: #334155;
}

.cancel-btn:hover {
    background: #cbd5e1;
}

.continue-btn {
    background: #2563eb;

    color: white;
}

.continue-btn:hover {
    background: #1d4ed8;
}


/* =========================
   STAGE NOTIFICATIONS
========================= */

.notification-box {
    background: #f0fdf4;

    border: 1px solid #86efac;

    border-left: 5px solid #16a34a;

    padding: 16px 18px;

    border-radius: 10px;

    margin-bottom: 12px;
}

.notification-box h3 {
    color: #166534;

    font-size: 16px;

    margin-bottom: 6px;
}

.notification-box p {
    color: #475569;

    font-size: 14px;

    line-height: 1.6;
}

.notification-time {
    color: #64748b;

    font-size: 12px;

    margin-top: 8px;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    .cards {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .modal-box {
        width: 90%;
    }

}

</style>

</head>
<body>

<!-- =========================
     SIDEBAR
========================= -->

<div class="sidebar">

    <div class="logo">
        Event Hub
    </div>

    <div class="role">
        ORGANIZER PANEL
    </div>


    <a
        href="organizer_dashboard.php"
        class="active">

        Dashboard

    </a>


    <!-- Student privileges -->

    <a
        href="../student_dashboard.php"
        class="student-area">

        🎓 Student Area

    </a>


    <a href="event_request.php">

        Event Request

    </a>


    <a href="approval_status.php">

        Approval Status

    </a>


    <a href="planning.php">

        Event Planning

    </a>


    <a
        href="javascript:void(0)"
        onclick="openEventSelector('brochure')">

        📄 Brochure

    </a>


    <a
        href="javascript:void(0)"
        onclick="openEventSelector('checklist')">

        ✅ Checklist

    </a>


    <a href="participants.php">

        Participants

    </a>


    <a href="scan_qr.php">

        Scan QR

    </a>


    <a href="expenses.php">

        Expenses

    </a>


    <a href="report.php">

        Reports

    </a>


    <a
        href="../login.html"
        class="logout">

        Logout

    </a>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>
                Organizer Dashboard
            </h1>

            <p class="welcome">

                Welcome back,

                <span class="organizer-name">

                    <?php
                    echo htmlspecialchars($user_name);
                    ?>

                </span>

            </p>

        </div>

    </div>


    <!-- =========================
         APPROVED ORGANIZER MESSAGE
    ========================= -->

    <div class="approved-message">

        <strong>
            🎉 You are now an Organizer!
        </strong>

        <br>

        Your organizer team request has been approved.

        You can manage your team's approved events
        while continuing to use all student privileges.

    </div>
    <!-- =========================
     DASHBOARD COUNTS
========================= -->

<div class="cards">


    <div class="card">

        <h3>
            My Team Events
        </h3>

        <div class="number">

            <?php
            echo $total_events;
            ?>

        </div>

    </div>


    <div class="card">

        <h3>
            Pending Requests
        </h3>

        <div class="number">

            <?php
            echo $pending_requests;
            ?>

        </div>

    </div>


    <div class="card">

        <h3>
            Approved Events
        </h3>

        <div class="number">

            <?php
            echo $approved_events;
            ?>

        </div>

    </div>


    <div class="card">

        <h3>
            My Event Participants
        </h3>

        <div class="number">

            <?php
            echo $total_participants;
            ?>

        </div>

    </div>


</div>


<!-- =========================
     STAGE READY NOTIFICATIONS
========================= -->

<div class="section">

    <h2>
        🔔 Stage Notifications
    </h2>


    <?php

    /*
     * Stage Manager creates a notification
     * in the updates table when the stage
     * becomes ready.
     */

    $stage_notifications = [];


    if (count($my_request_ids) > 0) {

        $placeholders = implode(
            ",",
            array_fill(
                0,
                count($my_request_ids),
                "?"
            )
        );

        $types = str_repeat(
            "i",
            count($my_request_ids)
        );


        $stmt = $conn->prepare("
            SELECT
                u.update_id,
                u.event_id,
                u.title,
                u.message,
                u.update_type,
                u.created_at,
                e.event_name,
                e.event_date
            FROM updates u
            INNER JOIN events e
                ON u.event_id = e.event_id
            WHERE u.update_type = 'stage_ready'
              AND e.organizer_request_id IN ($placeholders)
            ORDER BY u.created_at DESC
        ");


        $stmt->bind_param(
            $types,
            ...$my_request_ids
        );


        $stmt->execute();

        $result = $stmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $stage_notifications[] = $row;

        }


        $stmt->close();

    }


    ?>


    <?php if (count($stage_notifications) > 0): ?>


        <?php foreach ($stage_notifications as $notification): ?>

            <div class="notification-box">

                <h3>

                    <?php
                    echo htmlspecialchars(
                        $notification["title"]
                    );
                    ?>

                </h3>


                <p>

                    <?php
                    echo htmlspecialchars(
                        $notification["message"]
                    );
                    ?>

                </p>


                <p class="notification-time">

                    Event:

                    <?php
                    echo htmlspecialchars(
                        $notification["event_name"]
                    );
                    ?>

                    <br>

                    Event Date:

                    <?php
                    echo date(
                        "d M Y",
                        strtotime(
                            $notification["event_date"]
                        )
                    );
                    ?>

                    <br>

                    Notification:

                    <?php
                    echo date(
                        "d M Y, h:i A",
                        strtotime(
                            $notification["created_at"]
                        )
                    );
                    ?>

                </p>

            </div>

        <?php endforeach; ?>


    <?php else: ?>


        <p class="empty">

            No stage notifications yet.

            When the Stage Manager completes the required
            preparation and marks the stage as ready,
            the notification will appear here.

        </p>


    <?php endif; ?>


</div>
<!-- =========================
     MY EVENTS
========================= -->

<div class="section">

    <h2>
        📌 My Team Events
    </h2>


    <p
        class="empty"
        style="margin-bottom:18px;">

        These are the events managed by your approved
        organizing team.

    </p>


    <?php if (count($my_events) > 0): ?>

        <div class="event-list">


            <?php foreach ($my_events as $event): ?>

                <div class="event-box">


                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $event["event_name"]
                        );
                        ?>

                    </h3>


                    <div class="event-info">

                        📅

                        <?php
                        echo date(
                            "d M Y",
                            strtotime(
                                $event["event_date"]
                            )
                        );
                        ?>

                        <br>


                        ⏰

                        <?php
                        echo date(
                            "h:i A",
                            strtotime(
                                $event["start_time"]
                            )
                        );
                        ?>

                        -

                        <?php
                        echo date(
                            "h:i A",
                            strtotime(
                                $event["end_time"]
                            )
                        );
                        ?>

                        <br>


                        📍

                        <?php
                        echo htmlspecialchars(
                            $event["venue"]
                        );
                        ?>

                    </div>


                    <div class="event-buttons">


                        <a
                            href="../event-details.php?event_id=<?php echo $event["event_id"]; ?>"
                            class="view-btn">

                            View Event

                        </a>


                        <?php if (
                            $event["publication_status"] === "published"
                            &&
                            $event["status"] !== "cancelled"
                            &&
                            $event["status"] !== "cancellation_requested"
                        ): ?>


                            <a
                               href="cancellation_request.php?event_id=<?php echo $event["event_id"]; ?>"
                                class="manage-btn"
                                onclick="return confirm('Are you sure you want to request cancellation for this event?');">

                                Request Cancellation

                            </a>


                        <?php endif; ?>


                        <?php if (
                            $event["status"] === "cancellation_requested"
                        ): ?>


                            <span
                                class="manage-btn"
                                style="background:#f59e0b; cursor:default;">

                                Cancellation Requested

                            </span>


                        <?php endif; ?>


                    </div>


                </div>

            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <p class="empty">

            No events are currently linked to your
            approved organizing team.

        </p>


    <?php endif; ?>


</div>
<!-- =========================
     OTHER APPROVED EVENTS
========================= -->

<div class="section">

    <h2>
        🌐 Other Approved Events
    </h2>


    <div class="view-only-note">

        ℹ️ These events belong to other organizing teams.

        You can view the event details and participate
        just like a normal student.

    </div>


    <?php if (count($other_events) > 0): ?>

        <div class="event-list">


            <?php foreach ($other_events as $event): ?>

                <div class="event-box">


                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $event["event_name"]
                        );
                        ?>

                    </h3>


                    <div class="event-info">

                        📅

                        <?php
                        echo date(
                            "d M Y",
                            strtotime(
                                $event["event_date"]
                            )
                        );
                        ?>

                        <br>


                        ⏰

                        <?php
                        echo date(
                            "h:i A",
                            strtotime(
                                $event["start_time"]
                            )
                        );
                        ?>

                        -

                        <?php
                        echo date(
                            "h:i A",
                            strtotime(
                                $event["end_time"]
                            )
                        );
                        ?>

                        <br>


                        📍

                        <?php
                        echo htmlspecialchars(
                            $event["venue"]
                        );
                        ?>


                        <?php if (!empty($event["team_name"])): ?>

                            <br>

                            <span class="event-team">

                                Organized by:

                                <?php
                                echo htmlspecialchars(
                                    $event["team_name"]
                                );
                                ?>

                            </span>

                        <?php endif; ?>


                    </div>


                    <div class="event-buttons">


                        <a
                            href="../event-details.php?event_id=<?php echo $event["event_id"]; ?>"
                            class="view-btn">

                            View Details

                        </a>


                        <a
                            href="../registration.php?event_id=<?php echo $event["event_id"]; ?>"
                            class="register-btn">

                            Register / Participate

                        </a>


                    </div>


                </div>

            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <p class="empty">

            No other approved events available right now.

        </p>


    <?php endif; ?>


</div>
<!-- =========================
     QUICK ACTIONS
========================= -->

<div class="section">

    <h2>
        ⚡ Quick Actions
    </h2>


    <div class="quick-actions">


        <a
            href="event_request.php"
            class="action">

            ➕ Create Event Request

        </a>


        <a
            href="approval_status.php"
            class="action">

            📋 Approval Status

        </a>


        <a
            href="planning.php"
            class="action">

            🗓️ Event Planning

        </a>


        <a
            href="../events.php"
            class="action">

            🎓 Browse All Events

        </a>


        <a
            href="../student_dashboard.php"
            class="action">

            🎓 Student Dashboard

        </a>


    </div>

</div>
<!-- =========================
     RECENT ACTIVITY
========================= -->

<div class="section">

    <h2>
        🕒 Recent Activity
    </h2>


    <div class="activity-list">


        <div class="activity-item">

            <span class="activity-icon">
                📌
            </span>

            <div>

                <strong>
                    My Team Events
                </strong>

                <p>
                    <?php echo count($my_events); ?>
                    event(s) currently linked to your
                    organizing team.
                </p>

            </div>

        </div>


        <div class="activity-item">

            <span class="activity-icon">
                🌐
            </span>

            <div>

                <strong>
                    Other Approved Events
                </strong>

                <p>
                    <?php echo count($other_events); ?>
                    approved event(s) available for
                    viewing and participation.
                </p>

            </div>

        </div>


        <div class="activity-item">

            <span class="activity-icon">
                👥
            </span>

            <div>

                <strong>
                    Participants
                </strong>

                <p>
                    <?php echo $total_participants; ?>
                    participant(s) registered for your
                    team events.
                </p>

            </div>

        </div>


        <div class="activity-item">

            <span class="activity-icon">
                🔔
            </span>

            <div>

                <strong>
                    Stage Notifications
                </strong>

                <p>

                    <?php echo count($stage_notifications); ?>

                    stage-ready notification(s) received.

                </p>

            </div>

        </div>


    </div>

</div>
<!-- =========================
     EVENT SELECT MODAL
========================= -->

<div
    id="eventSelectModal"
    class="modal-overlay"
    style="display:none;"
>

    <div class="modal-box">

        <button
            type="button"
            class="modal-close"
            onclick="closeEventModal();">

            ×

        </button>


        <h2>
            Select Event
        </h2>


        <p class="modal-description">

            Select the event you want to manage.

        </p>


        <div class="event-select-list">


            <?php if (count($organizer_events) > 0): ?>


                <?php foreach ($organizer_events as $event): ?>

                    <button
                        type="button"
                        class="event-select-item"
                        onclick="selectEvent(
                            <?php echo (int)$event["event_id"]; ?>
                        );"
                    >

                        <span class="event-select-name">

                            <?php
                            echo htmlspecialchars(
                                $event["event_name"]
                            );
                            ?>

                        </span>


                        <span class="event-select-date">

                            📅

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $event["event_date"]
                                )
                            );
                            ?>

                        </span>

                    </button>

                <?php endforeach; ?>


            <?php else: ?>


                <p class="empty">

                    No team events available.

                </p>


            <?php endif; ?>


        </div>

    </div>

</div>
<script>

    /* =========================
       EVENT SELECT MODAL
    ========================= */

    function openEventModal() {

        const modal = document.getElementById(
            "eventSelectModal"
        );

        if (modal) {

            modal.style.display = "flex";

        }

    }


    function closeEventModal() {

        const modal = document.getElementById(
            "eventSelectModal"
        );

        if (modal) {

            modal.style.display = "none";

        }

    }


    /* =========================
       SELECT EVENT
    ========================= */

    function selectEvent(eventId) {

        if (!eventId) {

            return;

        }


        const currentAction =
            window.selectedEventAction;


        if (!currentAction) {

            closeEventModal();

            return;

        }


        if (currentAction === "brochure") {

            window.location.href =
                "brochure_upload.php?event_id="
                + eventId;

        }


        else if (currentAction === "checklist") {

            window.location.href =
                "checklist.php?event_id="
                + eventId;

        }


        else if (currentAction === "participants") {

            window.location.href =
                "participants.php?event_id="
                + eventId;

        }


        else if (currentAction === "attendance") {

            window.location.href =
                "scan_qr.php?event_id="
                + eventId;

        }


        else if (currentAction === "expenses") {

            window.location.href =
                "expenses.php?event_id="
                + eventId;

        }


        else if (currentAction === "reports") {

            window.location.href =
                "reports.php?event_id="
                + eventId;

        }


        closeEventModal();

    }


    /* =========================
       EVENT ACTION
    ========================= */

    function selectEventAction(action) {

        window.selectedEventAction = action;

        openEventModal();

    }


    /* =========================
       CLOSE MODAL WHEN CLICKING
       OUTSIDE THE MODAL BOX
    ========================= */

    const eventModal =
        document.getElementById(
            "eventSelectModal"
        );


    if (eventModal) {

        eventModal.addEventListener(
            "click",
            function(event) {

                if (
                    event.target === eventModal
                ) {

                    closeEventModal();

                }

            }
        );

    }


    /* =========================
       ESC KEY
    ========================= */

    document.addEventListener(
        "keydown",
        function(event) {

            if (event.key === "Escape") {

                closeEventModal();

            }

        }
    );

</script>


</main>

</body>

</html>