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

$core_admin_name = $_SESSION["user_name"] ?? "Core Admin";


/* =========================================================
   PENDING COUNTS
========================================================= */

$event_review_count = 0;
$brochure_review_count = 0;
$report_review_count = 0;
$cancellation_count = 0;
$postponement_count = 0;
$booked_count = 0;
$published_count = 0;


/* Event Review */

$result = $conn->query("
    SELECT COUNT(DISTINCT event_id) AS total
    FROM stage_bookings
    WHERE booking_status = 'assigned'
");

if ($result) {
    $row = $result->fetch_assoc();
    $event_review_count = (int)($row["total"] ?? 0);
}


/* Brochure Review */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM event_brochures
    WHERE hod_status = 'approved'
      AND status IN ('pending', 'resubmitted')
");

if ($result) {
    $row = $result->fetch_assoc();
    $brochure_review_count = (int)($row["total"] ?? 0);
}


/* Report Review */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM event_reports
    WHERE status = 'admin_approved'
");

if ($result) {
    $row = $result->fetch_assoc();
    $report_review_count = (int)($row["total"] ?? 0);
}


/* Cancellation Review */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM event_cancellation_requests
    WHERE status = 'pending'
");

if ($result) {
    $row = $result->fetch_assoc();
    $cancellation_count = (int)($row["total"] ?? 0);
}


/* Postponement */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE postponement_status = 'verified'
");

if ($result) {
    $row = $result->fetch_assoc();
    $postponement_count = (int)($row["total"] ?? 0);
}


/* Booked Events */

$result = $conn->query("
    SELECT COUNT(DISTINCT event_id) AS total
    FROM stage_bookings
    WHERE booking_status = 'booked'
");

if ($result) {
    $row = $result->fetch_assoc();
    $booked_count = (int)($row["total"] ?? 0);
}


/* Published Events */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE request_status = 'approved'
      AND status = 'approved'
      AND publication_status = 'published'
");

if ($result) {
    $row = $result->fetch_assoc();
    $published_count = (int)($row["total"] ?? 0);
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

<title>Core Admin Dashboard</title>


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
    padding: 22px 30px;
}

.header-inner {
    max-width: 1200px;
    margin: auto;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.header h1 {
    margin: 0;
    font-size: 25px;
}

.header p {
    margin: 6px 0 0;
    color: #C9D7E8;
    font-size: 13px;
}

.admin-name {
    font-size: 14px;
    font-weight: bold;
}


/* =========================================================
   LAYOUT
========================================================= */

.layout {
    display: flex;
    min-height: calc(100vh - 85px);
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 230px;
    background: white;
    border-right: 1px solid #DCE4EF;
    padding: 22px 15px;
}

.sidebar-title {
    font-size: 18px;
    font-weight: bold;
    color: #0B1F3A;
    margin-bottom: 22px;
}

.sidebar-section {
    font-size: 10px;
    text-transform: uppercase;
    color: #8190A4;
    margin: 20px 10px 8px;
}

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: space-between;

    text-decoration: none;
    color: #172033;

    padding: 11px 13px;
    border-radius: 7px;

    margin-bottom: 5px;

    font-size: 14px;
    font-weight: 600;
}

.sidebar a:hover {
    background: #EAF2FB;
    color: #1E4E8C;
}

.sidebar a.active {
    background: #1E4E8C;
    color: white;
}

.count {
    font-size: 11px;
    background: #EEF3F8;
    color: #1E4E8C;
    padding: 3px 7px;
    border-radius: 12px;
}

.sidebar a.active .count {
    background: rgba(255,255,255,0.2);
    color: white;
}


/* =========================================================
   MAIN
========================================================= */

.main {
    flex: 1;
    padding: 30px;
}

.container {
    max-width: 1200px;
    margin: auto;
}


/* =========================================================
   WELCOME
========================================================= */

.welcome {
    margin-bottom: 25px;
}

.welcome h2 {
    margin: 0 0 6px;
    color: #0B1F3A;
    font-size: 23px;
}

.welcome p {
    margin: 0;
    color: #68778C;
    font-size: 14px;
}


/* =========================================================
   STATS
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    border: 1px solid #DCE4EF;
    border-radius: 12px;
    padding: 20px;
}

.stat-card h3 {
    margin: 0 0 10px;
    font-size: 13px;
    color: #68778C;
}

.stat-number {
    font-size: 30px;
    font-weight: bold;
    color: #1E4E8C;
}

.stat-card.red {
    border-left: 4px solid #DC3545;
}

.stat-card.red .stat-number {
    color: #B91C1C;
}

.stat-card.orange {
    border-left: 4px solid #F59E0B;
}

.stat-card.orange .stat-number {
    color: #B45309;
}


/* =========================================================
   REVIEW CARDS
========================================================= */

.section-title {
    color: #0B1F3A;
    font-size: 18px;
    margin: 0 0 15px;
}

.review-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.review-card {
    display: block;

    background: white;
    border: 1px solid #DCE4EF;
    border-radius: 12px;
    padding: 22px;

    text-decoration: none;
    color: inherit;

    cursor: pointer;

    transition: 0.2s;
}

.review-card:hover {
    border-color: #9DB8D5;
    box-shadow: 0 5px 18px rgba(11,31,58,0.08);
    transform: translateY(-1px);
}

.review-icon {
    width: 45px;
    height: 45px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: #EAF2FB;

    font-size: 22px;

    margin-bottom: 15px;
}

.review-card h3 {
    margin: 0 0 7px;
    color: #0B1F3A;
    font-size: 17px;
}

.review-card p {
    margin: 0 0 15px;
    color: #68778C;
    font-size: 13px;
    line-height: 1.5;
}

.review-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.review-count {
    font-size: 13px;
    font-weight: bold;
    color: #1E4E8C;
}

.open-text {
    color: #1E4E8C;
    font-size: 13px;
    font-weight: bold;
}

.review-card.cancel {
    border-left: 4px solid #DC3545;
}

.review-card.cancel .review-icon {
    background: #FDECEC;
}

.review-card.cancel .review-count {
    color: #B91C1C;
}


/* =========================================================
   NOTICES
========================================================= */

.notice-section {
    background: white;
    border: 1px solid #DCE4EF;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.notice-section h3 {
    margin: 0 0 10px;
    color: #0B1F3A;
    font-size: 17px;
}

.notice-text {
    color: #68778C;
    font-size: 13px;
    line-height: 1.6;
}

.notice-link {
    display: inline-block;
    margin-top: 13px;
    background: #FFF3CD;
    color: #856404;
    text-decoration: none;
    padding: 9px 13px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
}


/* =========================================================
   MONITORING
========================================================= */

.monitoring {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 25px;
}

.monitor-card {
    background: white;
    border: 1px solid #DCE4EF;
    border-radius: 12px;
    padding: 20px;
}

.monitor-card h3 {
    margin: 0 0 8px;
    color: #0B1F3A;
    font-size: 15px;
}

.monitor-card p {
    margin: 0 0 13px;
    color: #68778C;
    font-size: 12px;
    line-height: 1.5;
}

.monitor-number {
    font-size: 27px;
    font-weight: bold;
    color: #1E4E8C;
    margin-bottom: 5px;
}

.monitor-link {
    display: inline-block;
    color: #1E4E8C;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    text-align: center;
    color: #8A98AA;
    font-size: 12px;
    padding: 10px 0 25px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .review-grid {
        grid-template-columns: 1fr;
    }

    .monitoring {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 750px) {

    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #DCE4EF;
    }

    .main {
        padding: 20px;
    }

    .header-inner {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 500px) {

    .stats,
    .monitoring {
        grid-template-columns: 1fr;
    }

    .main {
        padding: 15px;
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
                Core Admin Dashboard
            </h1>

            <p>
                Event management and final administrative review
            </p>

        </div>

        <div class="admin-name">

            <?php
            echo htmlspecialchars($core_admin_name);
            ?>

        </div>

    </div>

</div>


<div class="layout">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">

    <div class="sidebar-title">
        Core Admin
    </div>


    <a
        href="core_admin_dashboard.php"
        class="active"
    >

        <span>🏠 Dashboard</span>

    </a>


    <div class="sidebar-section">
        Review
    </div>


    <a href="core_event_review.php">

        <span>📋 Event Review</span>

        <?php if ($event_review_count > 0): ?>

            <span class="count">
                <?php echo $event_review_count; ?>
            </span>

        <?php endif; ?>

    </a>


    <a href="brochure_review.php">

        <span>📄 Brochure Review</span>

        <?php if ($brochure_review_count > 0): ?>

            <span class="count">
                <?php echo $brochure_review_count; ?>
            </span>

        <?php endif; ?>

    </a>


    <a href="report_review.php">

        <span>📝 Report Review</span>

        <?php if ($report_review_count > 0): ?>

            <span class="count">
                <?php echo $report_review_count; ?>
            </span>

        <?php endif; ?>

    </a>


    <a href="cancellation_review.php">

        <span>❌ Cancellation Review</span>

        <?php if ($cancellation_count > 0): ?>

            <span class="count">
                <?php echo $cancellation_count; ?>
            </span>

        <?php endif; ?>

    </a>


    <div class="sidebar-section">
        Monitoring
    </div>


    <a href="stage_availability.php">
        <span>🏟️ Stage Availability</span>
    </a>


    <a href="booked_events.php">
        <span>📅 Booked Events</span>
    </a>


    <div class="sidebar-section">
        Account
    </div>


    <a href="../logout.php">
        <span>🚪 Logout</span>
    </a>

</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">

<div class="container">


<!-- WELCOME -->

<div class="welcome">

    <h2>
        Welcome, <?php echo htmlspecialchars($core_admin_name); ?>
    </h2>

    <p>
        Review pending items and monitor approved events from one place.
    </p>

</div>


<!-- =========================================================
     STATS
========================================================= -->

<div class="stats">


    <div class="stat-card">

        <h3>
            Event Reviews
        </h3>

        <div class="stat-number">
            <?php echo $event_review_count; ?>
        </div>

    </div>


    <div class="stat-card">

        <h3>
            Brochure Reviews
        </h3>

        <div class="stat-number">
            <?php echo $brochure_review_count; ?>
        </div>

    </div>


    <div class="stat-card">

        <h3>
            Final Report Reviews
        </h3>

        <div class="stat-number">
            <?php echo $report_review_count; ?>
        </div>

    </div>


    <div class="stat-card red">

        <h3>
            Cancellation Requests
        </h3>

        <div class="stat-number">
            <?php echo $cancellation_count; ?>
        </div>

    </div>


</div>


<!-- =========================================================
     REVIEW
========================================================= -->

<h2 class="section-title">
    Review
</h2>


<div class="review-grid">


    <!-- EVENT -->

    <a
        href="core_event_review.php"
        class="review-card"
    >

        <div class="review-icon">
            📋
        </div>

        <h3>
            Event Review
        </h3>

        <p>
            Review events after Department / HOD approval
            and stage assignment.
        </p>

        <div class="review-bottom">

            <span class="review-count">

                <?php echo $event_review_count; ?>
                pending

            </span>

            <span class="open-text">
                Open →
            </span>

        </div>

    </a>


    <!-- BROCHURE -->

    <a
        href="brochure_review.php"
        class="review-card"
    >

        <div class="review-icon">
            📄
        </div>

        <h3>
            Brochure Review
        </h3>

        <p>
            Review brochures that have already been approved
            by Department / HOD.
        </p>

        <div class="review-bottom">

            <span class="review-count">

                <?php echo $brochure_review_count; ?>
                pending

            </span>

            <span class="open-text">
                Open →
            </span>

        </div>

    </a>


    <!-- REPORT -->

    <a
        href="report_review.php"
        class="review-card"
    >

        <div class="review-icon">
            📝
        </div>

        <h3>
            Final Report Review
        </h3>

        <p>
            Give final approval to event reports after
            Department / HOD review.
        </p>

        <div class="review-bottom">

            <span class="review-count">

                <?php echo $report_review_count; ?>
                pending

            </span>

            <span class="open-text">
                Open →
            </span>

        </div>

    </a>


    <!-- CANCELLATION -->

    <a
        href="cancellation_review.php"
        class="review-card cancel"
    >

        <div class="review-icon">
            ❌
        </div>

        <h3>
            Cancellation Review
        </h3>

        <p>
            Review organizer requests to cancel an approved
            event.
        </p>

        <div class="review-bottom">

            <span class="review-count">

                <?php echo $cancellation_count; ?>
                pending

            </span>

            <span class="open-text">
                Open →
            </span>

        </div>

    </a>


</div>


<!-- =========================================================
     POSTPONEMENT
========================================================= -->

<?php if ($postponement_count > 0): ?>

<div class="notice-section">

    <h3>
        ⏸️ Postponement Review
    </h3>

    <div class="notice-text">

        <?php echo $postponement_count; ?>
        postponed event(s) are waiting for Core Admin review.

    </div>

    <a
        href="core_event_review.php"
        class="notice-link"
    >
        Review Postponed Events →
    </a>

</div>

<?php endif; ?>


<!-- =========================================================
     MONITORING
========================================================= -->

<h2 class="section-title">
    Monitoring
</h2>


<div class="monitoring">


    <div class="monitor-card">

        <div class="monitor-number">
            <?php echo $booked_count; ?>
        </div>

        <h3>
            📅 Booked Events
        </h3>

        <p>
            Events with officially booked stage schedules.
        </p>

        <a
            href="booked_events.php"
            class="monitor-link"
        >
            View Booked Events →
        </a>

    </div>


    <div class="monitor-card">

        <div class="monitor-number">
            <?php echo $published_count; ?>
        </div>

        <h3>
            🌐 Published Events
        </h3>

        <p>
            Events currently published for students.
        </p>

    </div>


    <div class="monitor-card">

        <div class="monitor-number">
            🏟️
        </div>

        <h3>
            Stage Availability
        </h3>

        <p>
            Check current stage and schedule availability.
        </p>

        <a
            href="stage_availability.php"
            class="monitor-link"
        >
            Open Availability →
        </a>

    </div>


</div>


<div class="footer">

    SSV College Event Management System

</div>


</div>

</div>

</div>


</body>

</html>