<?php
session_start();
include "../db.php";

/* =========================================================
   ADMIN / HOD LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    echo "<script>
            alert('Access denied.');
            window.location.href='../student_dashboard.php';
          </script>";
    exit();
}

/* =========================================================
   GET ADMIN DETAILS
========================================================= */

$admin_id = (int) $_SESSION["user_id"];
$admin_name = $_SESSION["user_name"] ?? "Department Admin";
$admin_department = "";

$admin_stmt = $conn->prepare(
    "SELECT name, department
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();

$admin_result = $admin_stmt->get_result();

if ($admin_result->num_rows === 1) {
    $admin_data = $admin_result->fetch_assoc();

    $admin_name = $admin_data["name"];
    $admin_department = $admin_data["department"];
}

$admin_stmt->close();

/* =========================================================
   DASHBOARD COUNTS
========================================================= */

$pending_event_requests = 0;
$pending_organizer_requests = 0;
$pending_organizer_request_id = 0;
$pending_event_request_id = 0;
$pending_payments = 0;
$pending_brochures = 0;

/* =========================================================
   PENDING EVENT REQUESTS
========================================================= */

$event_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM events
     WHERE request_status = 'pending'"
);

$event_stmt->execute();

$event_result = $event_stmt->get_result();

if ($event_row = $event_result->fetch_assoc()) {
    $pending_event_requests = (int) $event_row["total"];
}

$event_stmt->close();

/* =========================================================
   GET ONE PENDING EVENT REQUEST ID
   FIX FOR INVALID EVENT REQUEST
========================================================= */

$event_id_stmt = $conn->prepare(
    "SELECT event_id
     FROM events
     WHERE request_status = 'pending'
     ORDER BY event_id DESC
     LIMIT 1"
);

$event_id_stmt->execute();

$event_id_result = $event_id_stmt->get_result();

if ($event_id_row = $event_id_result->fetch_assoc()) {
    $pending_event_request_id = (int) $event_id_row["event_id"];
}

$event_id_stmt->close();

/* =========================================================
   PENDING ORGANIZER REQUESTS
========================================================= */

$organizer_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM organizer_requests
     WHERE status = 'pending'"
);

$organizer_stmt->execute();

$organizer_result = $organizer_stmt->get_result();

if ($organizer_row = $organizer_result->fetch_assoc()) {
    $pending_organizer_requests = (int) $organizer_row["total"];
}

$organizer_stmt->close();

/* =========================================================
   GET ONE PENDING ORGANIZER REQUEST ID
========================================================= */

$organizer_id_stmt = $conn->prepare(
    "SELECT request_id
     FROM organizer_requests
     WHERE status = 'pending'
     ORDER BY request_id DESC
     LIMIT 1"
);

$organizer_id_stmt->execute();

$organizer_id_result = $organizer_id_stmt->get_result();

if ($organizer_id_row = $organizer_id_result->fetch_assoc()) {
    $pending_organizer_request_id =
        (int) $organizer_id_row["request_id"];
}

$organizer_id_stmt->close();

/* =========================================================
   PENDING PAYMENTS
========================================================= */

$payment_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM payments
     WHERE status = 'pending'"
);

$payment_stmt->execute();

$payment_result = $payment_stmt->get_result();

if ($payment_row = $payment_result->fetch_assoc()) {
    $pending_payments = (int) $payment_row["total"];
}

$payment_stmt->close();

/* =========================================================
   PENDING BROCHURES
========================================================= */

$brochure_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM event_brochures
     WHERE status = 'pending'"
);

$brochure_stmt->execute();

$brochure_result = $brochure_stmt->get_result();

if ($brochure_row = $brochure_result->fetch_assoc()) {
    $pending_brochures = (int) $brochure_row["total"];
}

$brochure_stmt->close();

/* =========================================================
   RECENT PENDING EVENT REQUESTS
========================================================= */

$recent_events = [];

$recent_event_stmt = $conn->prepare(
    "SELECT
        event_id,
        event_name,
        event_date,
        start_time,
        venue,
        request_status
     FROM events
     WHERE request_status = 'pending'
     ORDER BY event_id DESC
     LIMIT 5"
);

$recent_event_stmt->execute();

$recent_event_result =
    $recent_event_stmt->get_result();

while ($row = $recent_event_result->fetch_assoc()) {
    $recent_events[] = $row;
}

$recent_event_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Dashboard | Event Management System</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Arial,
        Helvetica,
        sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

/* =========================================================
   SIDEBAR
========================================================= */

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
    text-align: center;
    margin-bottom: 30px;
}

.logo h2 {
    margin: 0;
    font-size: 22px;
}

.logo p {
    margin: 7px 0 0;
    font-size: 13px;
    color: #bfdbfe;
}

.nav-title {
    font-size: 11px;
    text-transform: uppercase;
    color: #93c5fd;
    margin: 20px 12px 8px;
    letter-spacing: 1px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #e0e7ff;
    padding: 12px 13px;
    margin-bottom: 6px;
    border-radius: 8px;
    font-size: 14px;
    transition: .2s;
}

.nav-link:hover {
    background: #1e3a8a;
    color: white;
}

.nav-link.active {
    background: #2563eb;
    color: white;
}

.badge {
    margin-left: auto;
    background: #ef4444;
    color: white;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 250px;
    min-height: 100vh;
}

/* =========================================================
   TOP BAR
========================================================= */

.topbar {
    background: white;
    padding: 18px 30px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.topbar h1 {
    margin: 0;
    color: #172554;
    font-size: 24px;
}

.topbar p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 14px;
}

.admin-info {
    text-align: right;
}

.admin-name {
    font-weight: bold;
    color: #172554;
}

.admin-department {
    color: #64748b;
    font-size: 13px;
    margin-top: 3px;
}

/* =========================================================
   CONTENT
========================================================= */

.content {
    padding: 30px;
}

/* =========================================================
   WELCOME
========================================================= */

.welcome {
    background:
        linear-gradient(
            135deg,
            #1e3a8a,
            #2563eb
        );
    color: white;
    padding: 28px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow:
        0 8px 25px rgba(30, 58, 138, .15);
}

.welcome h2 {
    margin: 0 0 8px;
    font-size: 25px;
}

.welcome p {
    margin: 0;
    color: #dbeafe;
}

/* =========================================================
   STAT CARDS
========================================================= */

.stats {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 22px;
    box-shadow:
        0 4px 15px rgba(0,0,0,.06);
    border: 1px solid #e5e7eb;
}

.stat-icon {
    font-size: 27px;
    margin-bottom: 12px;
}

.stat-number {
    font-size: 30px;
    font-weight: bold;
    color: #172554;
}

.stat-title {
    margin-top: 5px;
    color: #64748b;
    font-size: 14px;
}

/* =========================================================
   SECTION
========================================================= */

.section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 25px;
    box-shadow:
        0 4px 15px rgba(0,0,0,.06);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
}

.section-header h2 {
    margin: 0;
    color: #172554;
    font-size: 20px;
}

/* =========================================================
   QUICK ACTIONS
========================================================= */

.actions {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 15px;
}

.action-card {
    display: block;
    text-decoration: none;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    transition: .2s;
}

.action-card:hover {
    transform: translateY(-2px);
    border-color: #2563eb;
    background: #eff6ff;
}

.action-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

.action-title {
    font-weight: bold;
    color: #172554;
    margin-bottom: 5px;
}

.action-description {
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
}

/* =========================================================
   EVENT LIST
========================================================= */

.event-item {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 12px;
    background: #f8fafc;
}

.event-item:last-child {
    margin-bottom: 0;
}

.event-title {
    color: #172554;
    font-weight: bold;
    font-size: 17px;
    margin-bottom: 8px;
}

.event-details {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    color: #64748b;
    font-size: 13px;
}

.event-status {
    display: inline-block;
    margin-top: 12px;
    background: #fef3c7;
    color: #92400e;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.view-all {
    display: inline-block;
    text-decoration: none;
    color: #2563eb;
    font-weight: bold;
    font-size: 13px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {
    text-align: center;
    padding: 35px 20px;
    color: #64748b;
}

.empty-icon {
    font-size: 35px;
    margin-bottom: 8px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .actions {
        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 800px) {

    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-info {
        text-align: left;
    }
}

@media (max-width: 600px) {

    .content {
        padding: 18px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .actions {
        grid-template-columns: 1fr;
    }

    .welcome h2 {
        font-size: 21px;
    }
}

</style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

<div class="logo">

    <h2>🏛️ Event Hub</h2>

    <p>Department Administration</p>

</div>

<div class="nav-title">
    Main
</div>

<a
    href="admin_dashboard.php"
    class="nav-link active"
>
    🏠
    <span>Dashboard</span>
</a>

<div class="nav-title">
    Event Management
</div>

<?php if ($pending_event_request_id > 0): ?>

<a
    href="event_request_view.php?event_id=<?php echo $pending_event_request_id; ?>"
    class="nav-link"
>
    📋
    <span>Event Requests</span>

    <?php if ($pending_event_requests > 0): ?>

        <span class="badge">
            <?php
            echo $pending_event_requests;
            ?>
        </span>

    <?php endif; ?>

</a>

<?php else: ?>

<a
    href="event_request_view.php"
    class="nav-link"
>
    📋
    <span>Event Requests</span>

    <?php if ($pending_event_requests > 0): ?>

        <span class="badge">
            <?php
            echo $pending_event_requests;
            ?>
        </span>

    <?php endif; ?>

</a>

<?php endif; ?>


<?php if ($pending_organizer_request_id > 0): ?>

<a
    href="organizer_request_view.php?request_id=<?php echo $pending_organizer_request_id; ?>"
    class="nav-link"
>
    👥
    <span>Organizer Requests</span>

    <?php if ($pending_organizer_requests > 0): ?>

        <span class="badge">
            <?php
            echo $pending_organizer_requests;
            ?>
        </span>

    <?php endif; ?>

</a>

<?php else: ?>

<a
    href="organizer_request_view.php"
    class="nav-link"
>
    👥
    <span>Organizer Requests</span>
</a>

<?php endif; ?>


<a
    href="brochure_review.php"
    class="nav-link"
>
    📢
    <span>Brochure Review</span>

    <?php if ($pending_brochures > 0): ?>

        <span class="badge">
            <?php
            echo $pending_brochures;
            ?>
        </span>

    <?php endif; ?>

</a>


<a
    href="report_review.php"
    class="nav-link"
>
    📑
    <span>Report Review</span>
</a>


<div class="nav-title">
    Finance
</div>

<a
    href="payment_verification.php"
    class="nav-link"
>
    💳
    <span>Payment Verification</span>

    <?php if ($pending_payments > 0): ?>

        <span class="badge">
            <?php
            echo $pending_payments;
            ?>
        </span>

    <?php endif; ?>

</a>


<div class="nav-title">
    Account
</div>

<a
    href="../profile.php"
    class="nav-link"
>
    👤
    <span>Profile</span>
</a>

<a
    href="../logout.php"
    class="nav-link"
>
    🚪
    <span>Logout</span>
</a>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">

<!-- =====================================================
     TOP BAR
===================================================== -->

<header class="topbar">

<div>

    <h1>
        Admin Dashboard
    </h1>

    <p>
        Department-level event administration
    </p>

</div>


<div class="admin-info">

    <div class="admin-name">

        <?php
        echo htmlspecialchars($admin_name);
        ?>

    </div>

    <div class="admin-department">

        <?php

        echo htmlspecialchars(
            $admin_department !== ""
                ? $admin_department
                : "Department Admin / HOD"
        );

        ?>

    </div>

</div>

</header>


<!-- =====================================================
     CONTENT
===================================================== -->

<div class="content">


<!-- =====================================================
     WELCOME
===================================================== -->

<section class="welcome">

    <h2>

        Welcome,
        <?php
        echo htmlspecialchars($admin_name);
        ?>
        👋

    </h2>

    <p>

        Manage department-level event requests,
        organizer approvals, payments, brochures
        and final reports from this dashboard.

    </p>

</section>


<!-- =====================================================
     STATISTICS
===================================================== -->

<section class="stats">


<div class="stat-card">

    <div class="stat-icon">
        📋
    </div>

    <div class="stat-number">

        <?php
        echo $pending_event_requests;
        ?>

    </div>

    <div class="stat-title">
        Pending Event Requests
    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">
        👥
    </div>

    <div class="stat-number">

        <?php
        echo $pending_organizer_requests;
        ?>

    </div>

    <div class="stat-title">
        Pending Organizer Requests
    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">
        💳
    </div>

    <div class="stat-number">

        <?php
        echo $pending_payments;
        ?>

    </div>

    <div class="stat-title">
        Pending Payments
    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">
        📢
    </div>

    <div class="stat-number">

        <?php
        echo $pending_brochures;
        ?>

    </div>

    <div class="stat-title">
        Pending Brochure Reviews
    </div>

</div>


</section>


<!-- =====================================================
     QUICK ACTIONS
===================================================== -->

<section class="section">

<div class="section-header">

    <h2>
        ⚡ Quick Actions
    </h2>

</div>


<div class="actions">


<?php if ($pending_event_request_id > 0): ?>

<a
    href="event_request_view.php?event_id=<?php echo $pending_event_request_id; ?>"
    class="action-card"
>

<?php else: ?>

<a
    href="event_request_view.php"
    class="action-card"
>

<?php endif; ?>

    <div class="action-icon">
        📋
    </div>

    <div class="action-title">
        Event Requests
    </div>

    <div class="action-description">

        Review and process submitted
        event requests.

    </div>

</a>


<?php if ($pending_organizer_request_id > 0): ?>

<a
    href="organizer_request_view.php?request_id=<?php echo $pending_organizer_request_id; ?>"
    class="action-card"
>

<?php else: ?>

<a
    href="organizer_request_view.php"
    class="action-card"
>

<?php endif; ?>

    <div class="action-icon">
        👥
    </div>

    <div class="action-title">
        Organizer Requests
    </div>

    <div class="action-description">

        Review organizing team
        requests.

    </div>

</a>


<a
    href="payment_verification.php"
    class="action-card"
>

    <div class="action-icon">
        💳
    </div>

    <div class="action-title">
        Payment Verification
    </div>

    <div class="action-description">

        Cross-check transaction details
        and payment screenshots.

    </div>

</a>


<a
    href="brochure_review.php"
    class="action-card"
>

    <div class="action-icon">
        📢
    </div>

    <div class="action-title">
        Brochure Review
    </div>

    <div class="action-description">

        Review submitted event
        publicity materials.

    </div>

</a>


<a
    href="report_review.php"
    class="action-card"
>

    <div class="action-icon">
        📑
    </div>

    <div class="action-title">
        Report Review
    </div>

    <div class="action-description">

        Review submitted final
        event reports.

    </div>

</a>


<a
    href="../events.php"
    class="action-card"
>

    <div class="action-icon">
        🎪
    </div>

    <div class="action-title">
        View Events
    </div>

    <div class="action-description">

        View approved and published
        college events.

    </div>

</a>


</div>

</section>


<!-- =====================================================
     PENDING EVENT REQUESTS
===================================================== -->

<section class="section">


<div class="section-header">

    <h2>
        📌 Recent Pending Event Requests
    </h2>

    <?php if ($pending_event_request_id > 0): ?>

    <a
        href="event_request_view.php?event_id=<?php echo $pending_event_request_id; ?>"
        class="view-all"
    >
        View All →
    </a>

    <?php else: ?>

    <a
        href="event_request_view.php"
        class="view-all"
    >
        View All →
    </a>

    <?php endif; ?>

</div>


<?php if (count($recent_events) > 0): ?>


<?php foreach ($recent_events as $event): ?>


<div class="event-item">

    <div class="event-title">

        <?php

        echo htmlspecialchars(
            $event["event_name"]
        );

        ?>

    </div>


    <div class="event-details">

        <span>

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


        <span>

            ⏰

            <?php

            echo date(
                "h:i A",
                strtotime(
                    $event["start_time"]
                )
            );

            ?>

        </span>


        <span>

            📍

            <?php

            echo htmlspecialchars(
                $event["venue"]
            );

            ?>

        </span>

    </div>


    <span class="event-status">

        ⏳ Pending Review

    </span>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    <div class="empty-icon">
        ✅
    </div>

    <h3>
        No Pending Event Requests
    </h3>

    <p>

        There are currently no event requests
        waiting for department review.

    </p>

</div>


<?php endif; ?>


</section>


</div>

</main>

</body>

</html>

<?php

$conn->close();

?>