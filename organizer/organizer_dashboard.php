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
         MY EVENTS
    ========================= -->

    <div class="section">

        <h2>
            📌 My Team Events
        </h2>


        <p class="empty"
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


                            <a
                                href="planning.php?event_id=<?php echo $event["event_id"]; ?>"
                                class="manage-btn">

                                Manage Event

                            </a>

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

                            <!-- Student-style event viewing -->

                            <a
                                href="../event-details.php?event_id=<?php echo $event["event_id"]; ?>"
                                class="view-btn">

                                View Details

                            </a>


                            <!-- Student-style participation -->

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
            Quick Actions
        </h2>


        <div class="quick-actions">


            <a
                href="event_request.php"
                class="action">

                + Create Event Request

            </a>


            <a
                href="approval_status.php"
                class="action">

                Approval Status

            </a>


            <a
                href="planning.php"
                class="action">

                Event Planning

            </a>


            <button
                type="button"
                class="action"
                onclick="openEventSelector('brochure')">

                📄 Brochure

            </button>


            <button
                type="button"
                class="action"
                onclick="openEventSelector('checklist')">

                ✅ Event Checklist

            </button>


            <a
                href="participants.php"
                class="action">

                View Participants

            </a>


            <a
                href="scan_qr.php"
                class="action">

                Scan Attendance QR

            </a>


            <!-- Student privilege -->

            <a
                href="../events.php"
                class="action">

                🎓 Browse All Events

            </a>


        </div>

    </div>


    <!-- =========================
         RECENT ACTIVITY
    ========================= -->

    <div class="section">

        <h2>
            Recent Activity
        </h2>


        <?php if ($total_events > 0): ?>

            <p class="empty">

                You currently have

                <strong>
                    <?php echo $total_events; ?>
                </strong>

                event(s) connected to your approved
                organizing team.

            </p>

        <?php else: ?>

            <p class="empty">

                No recent organizer activity available.

            </p>

        <?php endif; ?>

    </div>


</div>


<!-- =========================
     EVENT SELECT MODAL
========================= -->

<div
    class="modal"
    id="eventModal">

    <div class="modal-box">

        <h2 id="modalTitle">
            Select Event
        </h2>

        <p id="modalDescription">
            Select an event to continue.
        </p>


        <?php if (count($organizer_events) > 0): ?>

            <select id="selectedEvent">

                <option value="">
                    -- Select Event --
                </option>


                <?php foreach (
                    $organizer_events as $event
                ): ?>

                    <option
                        value="<?php
                        echo $event["event_id"];
                        ?>">

                        <?php
                        echo htmlspecialchars(
                            $event["event_name"]
                        );
                        ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="modal-btn cancel-btn"
                    onclick="closeEventSelector()">

                    Cancel

                </button>


                <button
                    type="button"
                    class="modal-btn continue-btn"
                    onclick="continueToModule()">

                    Continue

                </button>

            </div>

        <?php else: ?>

            <p>
                No events available for organizer
                management yet.
            </p>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="modal-btn cancel-btn"
                    onclick="closeEventSelector()">

                    Close

                </button>

            </div>

        <?php endif; ?>

    </div>

</div>


<script>

let selectedModule = "";


/* =========================
   OPEN EVENT SELECTOR
========================= */

function openEventSelector(module) {

    selectedModule = module;

    const modal =
        document.getElementById("eventModal");

    const title =
        document.getElementById("modalTitle");

    const description =
        document.getElementById("modalDescription");


    if (module === "brochure") {

        title.innerText =
            "📄 Select Event for Brochure";

        description.innerText =
            "Choose one of your team's events whose brochure you want to upload or view.";

    }


    if (module === "checklist") {

        title.innerText =
            "✅ Select Event for Checklist";

        description.innerText =
            "Choose one of your team's events whose preparation checklist you want to open.";

    }


    modal.style.display = "flex";

}


/* =========================
   CLOSE MODAL
========================= */

function closeEventSelector() {

    document.getElementById(
        "eventModal"
    ).style.display = "none";

}


/* =========================
   CONTINUE
========================= */

function continueToModule() {

    const eventId =
        document.getElementById(
            "selectedEvent"
        ).value;


    if (eventId === "") {

        alert("Please select an event.");

        return;

    }


    if (selectedModule === "brochure") {

        window.location.href =
            "brochure_upload.php?event_id="
            + eventId;

    }


    if (selectedModule === "checklist") {

        window.location.href =
            "checklist.php?event_id="
            + eventId;

    }

}


/* =========================
   CLOSE WHEN CLICKING OUTSIDE
========================= */

window.onclick = function(event) {

    const modal =
        document.getElementById("eventModal");


    if (event.target === modal) {

        closeEventSelector();

    }

};

</script>


</body>

</html>