<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.html");
    exit();
}

require_once "../db.php";

$admin_name = $_SESSION["user_name"] ?? "Admin";


/* =========================
   DASHBOARD COUNTS
========================= */

// Total Events
$total_events = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_events = $row["total"];
}


// Pending Event Requests
$pending_requests = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE request_status = 'pending'
");

if ($result) {
    $row = $result->fetch_assoc();
    $pending_requests = $row["total"];
}


// Approved Events
$approved_events = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE request_status = 'approved'
");

if ($result) {
    $row = $result->fetch_assoc();
    $approved_events = $row["total"];
}


// Total Users
$total_users = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_users = $row["total"];
}


/* =========================
   ORGANIZER / TEAM REQUEST COUNT
========================= */

$pending_team_requests = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM organizer_requests
    WHERE status = 'pending'
");

if ($result) {
    $row = $result->fetch_assoc();
    $pending_team_requests = $row["total"];
}


/* =========================
   REPORT COUNTS
========================= */

$pending_reports = 0;
$correction_reports = 0;
$approved_reports = 0;

$result = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM event_reports
    GROUP BY status
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if ($row["status"] === "pending") {

            $pending_reports = $row["total"];

        }

        elseif ($row["status"] === "correction_required") {

            $correction_reports = $row["total"];

        }

        elseif ($row["status"] === "approved") {

            $approved_reports = $row["total"];

        }
    }
}


/* =========================
   ORGANIZER / TEAM REQUESTS
========================= */

$organizer_requests = $conn->query("
    SELECT
        r.request_id,
        r.requested_by,
        r.team_name,
        r.reason,
        r.status,
        r.created_at,
        u.name AS leader_name,
        u.email AS leader_email
    FROM organizer_requests r
    LEFT JOIN users u
        ON r.requested_by = u.id
    ORDER BY r.request_id DESC
    LIMIT 10
");


/* =========================
   RECENT EVENT REQUESTS
========================= */

$requests = $conn->query("
    SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.event_type,
        e.request_status,
        u.name AS organizer_name
    FROM events e
    LEFT JOIN users u
        ON e.organizer_id = u.id
    ORDER BY e.event_id DESC
    LIMIT 10
");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | EventHub</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}


body {
    background: #f1f5f9;
    color: #1e293b;
}


/* =========================
   SIDEBAR
========================= */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 240px;
    height: 100vh;

    background: #0f172a;

    color: white;

    padding: 25px 18px;

    overflow-y: auto;
}


.logo {

    font-size: 22px;

    font-weight: bold;

    color: #60a5fa;

    margin-bottom: 35px;
}


.menu-title {

    font-size: 12px;

    color: #64748b;

    margin: 25px 10px 8px;

    text-transform: uppercase;
}


.sidebar a {

    display: block;

    color: #cbd5e1;

    text-decoration: none;

    padding: 13px 12px;

    margin-bottom: 6px;

    border-radius: 8px;

    transition: 0.2s;
}


.sidebar a:hover,
.sidebar a.active {

    background: #1e3a8a;

    color: white;
}


/* =========================
   MAIN
========================= */

.main {

    margin-left: 240px;

    padding: 30px;
}


/* =========================
   TOP BAR
========================= */

.topbar {

    background: white;

    padding: 22px 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.05);
}


.topbar h1 {

    font-size: 26px;

    margin-bottom: 6px;
}


.topbar p {

    color: #64748b;
}


/* =========================
   DASHBOARD CARDS
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

    padding: 23px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.05);
}


.card-title {

    color: #64748b;

    font-size: 14px;

    margin-bottom: 12px;
}


.card-number {

    font-size: 32px;

    font-weight: bold;

    color: #1e3a8a;
}


/* =========================
   REPORT SECTION
========================= */

.report-section {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 30px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.05);
}


.report-section h2 {

    font-size: 20px;

    margin-bottom: 20px;

    color: #172554;
}


.report-cards {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;
}


.report-card {

    padding: 20px;

    border-radius: 10px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;
}


.report-card h3 {

    font-size: 14px;

    color: #64748b;

    margin-bottom: 10px;
}


.report-number {

    font-size: 28px;

    font-weight: bold;

    color: #1e3a8a;
}


.report-button {

    display: inline-block;

    margin-top: 18px;

    padding: 10px 18px;

    background: #2563eb;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-size: 14px;
}


.report-button:hover {

    background: #1d4ed8;
}


/* =========================
   REQUESTS
========================= */

.requests {

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.05);

    overflow-x: auto;

    margin-bottom: 30px;
}


.requests-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.requests-header h2 {

    font-size: 20px;
}


/* =========================
   TABLE
========================= */

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}


th {

    background: #f8fafc;

    color: #475569;

    font-size: 13px;

    text-align: left;

    padding: 14px 12px;

    border-bottom:
        1px solid #e2e8f0;
}


td {

    padding: 14px 12px;

    border-bottom:
        1px solid #e2e8f0;

    font-size: 14px;
}


tr:hover {

    background: #f8fafc;
}


/* =========================
   STATUS
========================= */

.status {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.pending {

    background: #fef3c7;

    color: #92400e;
}


.approved {

    background: #dcfce7;

    color: #166534;
}


.correction {

    background: #fee2e2;

    color: #991b1b;
}


/* =========================
   VIEW BUTTON
========================= */

.view-btn {

    display: inline-block;

    background: #2563eb;

    color: white;

    text-decoration: none;

    padding: 7px 12px;

    border-radius: 6px;

    font-size: 12px;
}


.view-btn:hover {

    background: #1d4ed8;
}


/* =========================
   EMPTY
========================= */

.empty {

    padding: 35px;

    text-align: center;

    color: #64748b;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    .cards {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .report-cards {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 650px) {

    .sidebar {

        width: 190px;
    }


    .main {

        margin-left: 190px;

        padding: 20px;
    }


    .cards {

        grid-template-columns: 1fr;
    }


    .report-cards {

        grid-template-columns: 1fr;
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

        EventHub Admin

    </div>


    <a
        href="admin_dashboard.php"
        class="active"
    >

        🏠 Dashboard

    </a>


    <a
        href="admin_dashboard.php#organizer-requests"
    >

        👥 Team Requests

    </a>


    <a
        href="admin_dashboard.php#event-requests"
    >

        📋 Event Requests

    </a>


    <a
        href="brochure_review.php"
    >

        📄 Brochure Review

    </a>


    <a
        href="reports.php"
    >

        📊 Reports Review

    </a>


    <div class="menu-title">

        Account

    </div>


    <a
        href="../login.html"
    >

        🚪 Logout

    </a>


</div>



<!-- =========================
     MAIN
========================= -->

<div class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <h1>

            Welcome,
            <?php echo htmlspecialchars($admin_name); ?>
            👋

        </h1>


        <p>

            Manage event requests, team approvals,
            brochure reviews and reports.

        </p>

    </div>



    <!-- =========================
         DASHBOARD CARDS
    ========================= -->

    <div class="cards">


        <div class="card">

            <div class="card-title">

                Total Events

            </div>


            <div class="card-number">

                <?php echo $total_events; ?>

            </div>

        </div>



        <div class="card">

            <div class="card-title">

                Pending Requests

            </div>


            <div class="card-number">

                <?php echo $pending_requests; ?>

            </div>

        </div>



        <div class="card">

            <div class="card-title">

                Approved Events

            </div>


            <div class="card-number">

                <?php echo $approved_events; ?>

            </div>

        </div>



        <div class="card">

            <div class="card-title">

                Total Users

            </div>


            <div class="card-number">

                <?php echo $total_users; ?>

            </div>

        </div>


    </div>



    <!-- =========================
         ORGANIZER / TEAM REQUESTS
    ========================= -->

    <div
        class="requests"
        id="organizer-requests"
    >


        <div class="requests-header">

            <h2>

                👥 Organizer / Team Requests

            </h2>

        </div>


        <?php if (
            $organizer_requests &&
            $organizer_requests->num_rows > 0
        ): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            Team Name
                        </th>

                        <th>
                            Leader
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Reason
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php while (
                    $team =
                    $organizer_requests->fetch_assoc()
                ): ?>


                    <tr>


                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $team["team_name"]
                                );

                                ?>

                            </strong>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $team["leader_name"]
                                ?? "Unknown"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $team["leader_email"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $team["reason"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td>


                            <?php

                            $team_status =
                                $team["status"];


                            if (
                                $team_status ===
                                "approved"
                            ) {

                                echo '
                                <span class="status approved">
                                    Approved
                                </span>';

                            }

                            elseif (
                                $team_status ===
                                "correction_required"
                            ) {

                                echo '
                                <span class="status correction">
                                    Correction Required
                                </span>';

                            }

                            else {

                                echo '
                                <span class="status pending">
                                    Pending
                                </span>';

                            }

                            ?>


                        </td>


                        <td>


                            <a
                                href="organizer_request_view.php?request_id=<?php echo $team["request_id"]; ?>"
                                class="view-btn"
                            >

                                View

                            </a>


                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">

                No organizer/team requests found.

            </div>


        <?php endif; ?>


    </div>



    <!-- =========================
         REPORT SUMMARY
    ========================= -->

    <div class="report-section">


        <h2>

            📊 Event Reports

        </h2>


        <div class="report-cards">


            <div class="report-card">

                <h3>

                    Pending Reports

                </h3>


                <div class="report-number">

                    <?php echo $pending_reports; ?>

                </div>

            </div>



            <div class="report-card">

                <h3>

                    Correction Required

                </h3>


                <div class="report-number">

                    <?php echo $correction_reports; ?>

                </div>

            </div>



            <div class="report-card">

                <h3>

                    Approved Reports

                </h3>


                <div class="report-number">

                    <?php echo $approved_reports; ?>

                </div>

            </div>


        </div>


        <a
            href="reports.php"
            class="report-button"
        >

            View Reports

        </a>


    </div>



    <!-- =========================
         EVENT REQUESTS
    ========================= -->

    <div
        class="requests"
        id="event-requests"
    >


        <div class="requests-header">

            <h2>

                📋 Recent Event Requests

            </h2>

        </div>


        <?php if (
            $requests &&
            $requests->num_rows > 0
        ): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            Event
                        </th>

                        <th>
                            Organizer
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Time
                        </th>

                        <th>
                            Venue
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php while (
                    $event =
                    $requests->fetch_assoc()
                ): ?>


                    <tr>


                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $event["event_name"]
                                );

                                ?>

                            </strong>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $event["organizer_name"]
                                ?? "Unknown"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $event["event_date"]
                            );

                            ?>

                        </td>


                        <td>

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

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $event["venue"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $event["event_type"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td>


                            <?php

                            $status =
                                $event["request_status"];


                            if (
                                $status ===
                                "approved"
                            ) {

                                echo '
                                <span class="status approved">
                                    Approved
                                </span>';

                            }

                            elseif (
                                $status ===
                                "correction_required"
                            ) {

                                echo '
                                <span class="status correction">
                                    Correction Required
                                </span>';

                            }

                            else {

                                echo '
                                <span class="status pending">
                                    Pending
                                </span>';

                            }

                            ?>


                        </td>


                        <td>


                            <a
                                href="event_request_view.php?event_id=<?php echo $event["event_id"]; ?>"
                                class="view-btn"
                            >

                                View

                            </a>


                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">

                No event requests found.

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>