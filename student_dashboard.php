<?php 

include "auth_student.php"; 
include "db.php"; 

$user_id = $_SESSION["user_id"];


/* =========================================================
   Check whether this student is an approved organizer
========================================================= */

$organizer_check = $conn->prepare(
    "SELECT tm.team_member_id
     FROM organizer_team_members tm
     INNER JOIN organizer_requests r
         ON tm.request_id = r.request_id
     WHERE tm.user_id = ?
       AND r.status = 'approved'
     LIMIT 1"
);

$organizer_check->bind_param("i", $user_id);
$organizer_check->execute();

$organizer_result = $organizer_check->get_result();

$is_organizer = ($organizer_result->num_rows > 0);

$organizer_check->close();


/* =========================================================
   Upcoming Published Events Count
========================================================= */

$upcoming_events = 0;

$upcoming_query = $conn->query(
    "SELECT COUNT(*) AS total
     FROM events
     WHERE event_date >= CURDATE()
     AND status = 'upcoming'
     AND publication_status = 'published'"
);

if ($upcoming_query) {

    $upcoming_row = $upcoming_query->fetch_assoc();

    $upcoming_events = (int)$upcoming_row["total"];

}


/* =========================================================
   My Registrations Count
========================================================= */

$registration_query = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM registrations
     WHERE user_id = ?"
);

$registration_query->bind_param("i", $user_id);
$registration_query->execute();

$registration_result = $registration_query->get_result();

$my_registrations = $registration_result->fetch_assoc()["total"];

$registration_query->close();


/* =========================================================
   My Certificates Count
========================================================= */

$certificate_query = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM certificates c
     JOIN registrations r
     ON c.registration_id = r.registration_id
     WHERE r.user_id = ?"
);

$certificate_query->bind_param("i", $user_id);
$certificate_query->execute();

$certificate_result = $certificate_query->get_result();

$my_certificates = $certificate_result->fetch_assoc()["total"];

$certificate_query->close();


/* =========================================================
   Upcoming Published Events
========================================================= */

$events_query = $conn->query(
    "SELECT 
        event_id,
        event_name,
        event_date,
        start_time,
        end_time,
        venue
     FROM events
     WHERE event_date >= CURDATE()
     AND status = 'upcoming'
     AND publication_status = 'published'
     ORDER BY event_date ASC, start_time ASC
     LIMIT 5"
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard</title>

    <link rel="stylesheet" href="css/dashboard.css">

    <style>

        .organizer-box {
            background: #ffffff;
            padding: 20px 25px;
            margin: 25px 0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .organizer-box h3 {
            margin-top: 0;
            color: #5b3ca8;
        }

        .organizer-box p {
            color: #666;
            margin-bottom: 15px;
        }

        .organizer-btn {
            display: inline-block;
            padding: 11px 18px;
            background: #5b3ca8;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .organizer-btn:hover {
            background: #472d8b;
        }

    </style>

</head>


<body>

<div class="dashboard">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="logo">
            🎓 Event Hub
        </div>


        <nav>

            <a href="student_dashboard.php" class="active">
                🏠 Dashboard
            </a>

            <a href="events.php">
                📅 Events
            </a>

            <a href="my_registration.php">
                📝 My Registrations
            </a>

            <a href="my_qr.php">
                🎟 My QR
            </a>

            <a href="feedback.php">
                💬 Feedback
            </a>

            <a href="updates.html">
                📢 Updates
            </a>

            <a href="certificate.php">
                🏆 Certificates
            </a>

            <a href="profile.php">
                👤 Profile
            </a>

        </nav>


        <a href="login.html" class="logout">
            🚪 Logout
        </a>

    </aside>



    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <h1>
                    Student Dashboard
                </h1>

                <p>
                    Manage your events and registrations
                </p>

            </div>


            <div class="user-info">

                🔔 &nbsp;
                👤

                <strong>
                    <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
                </strong>

                <p>

                    <a href="home.html" class="back-home">
                        ← Back to Home
                    </a>

                </p>

            </div>

        </header>



        <!-- =====================================================
             WELCOME
        ====================================================== -->

        <section class="welcome">

            <h2>

                Welcome back,
                <?php echo htmlspecialchars($_SESSION["user_name"]); ?>! 👋

            </h2>

            <p>
                Explore upcoming college events and join the ones you love.
            </p>

        </section>



        <!-- =====================================================
             ORGANIZER SECTION
        ====================================================== -->

        <?php if ($is_organizer): ?>

            <section class="organizer-box">

                <h3>
                    🎯 Organizer Access
                </h3>

                <p>

                    You are an approved member of an organizing team.
                    You can manage your team's approved events while continuing
                    to use all student features.

                </p>

                <a
                    href="organizer/organizer_dashboard.php"
                    class="organizer-btn"
                >
                    🎯 Go to Organizer Dashboard
                </a>

            </section>

        <?php else: ?>

            <section class="organizer-box">

                <h3>
                    Want to organize an event?
                </h3>

                <p>

                    Form a 3-member student team and request Organizer access.
                    Your request will be reviewed by the Admin.

                </p>

                <a
                    href="become_organizer.php"
                    class="organizer-btn"
                >
                    Become an Organizer
                </a>

            </section>

        <?php endif; ?>



        <!-- =====================================================
             STATS
        ====================================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    📅
                </div>

                <div>

                    <h3>
                        <?php echo $upcoming_events; ?>
                    </h3>

                    <p>
                        Upcoming Events
                    </p>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    📝
                </div>

                <div>

                    <h3>
                        <?php echo $my_registrations; ?>
                    </h3>

                    <p>
                        My Registrations
                    </p>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    🏆
                </div>

                <div>

                    <h3>
                        <?php echo $my_certificates; ?>
                    </h3>

                    <p>
                        Certificates
                    </p>

                </div>

            </div>


        </section>



        <!-- =====================================================
             UPCOMING EVENTS
        ====================================================== -->

        <section class="events-section">


            <div class="section-heading">

                <h2>
                    Upcoming Events
                </h2>

                <a href="events.php">
                    View All
                </a>

            </div>



            <?php if ($events_query && $events_query->num_rows > 0): ?>


                <?php while ($event = $events_query->fetch_assoc()): ?>


                    <div class="event-card">


                        <!-- EVENT DATE -->

                        <div class="event-date">

                            <strong>

                                <?php
                                echo date(
                                    "d",
                                    strtotime($event["event_date"])
                                );
                                ?>

                            </strong>


                            <span>

                                <?php
                                echo strtoupper(
                                    date(
                                        "M",
                                        strtotime($event["event_date"])
                                    )
                                );
                                ?>

                            </span>

                        </div>



                        <!-- EVENT DETAILS -->

                        <div class="event-details">


                            <h3>

                                <?php
                                echo htmlspecialchars(
                                    $event["event_name"]
                                );
                                ?>

                            </h3>


                            <p>

                                📍

                                <?php
                                echo htmlspecialchars(
                                    $event["venue"]
                                );
                                ?>

                            </p>


                            <p>

                                ⏰

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

                            </p>


                        </div>



                        <!-- VIEW DETAILS -->

                        <a
                            href="event-details.php?event_id=<?php echo (int)$event["event_id"]; ?>"
                            class="event-details-btn"
                        >
                            View Details
                        </a>


                    </div>


                <?php endwhile; ?>


            <?php else: ?>

                <p>
                    No upcoming events available.
                </p>

            <?php endif; ?>


        </section>


    </main>

</div>

</body>

</html>