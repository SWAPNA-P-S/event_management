<?php

session_start();

require_once __DIR__ . "/../db.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.html");
    exit();

}


$user_id = $_SESSION["user_id"];


/* =========================================================
   STAGE MANAGER CHECK
========================================================= */

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "stage_manager"
) {

    header("Location: ../login.html");
    exit();

}


/* =========================================================
   GET STAGE MANAGER DETAILS
========================================================= */

$user_stmt = $conn->prepare(
    "SELECT
        id,
        name,
        email,
        phone,
        college,
        department,
        student_id
     FROM users
     WHERE id = ?"
);

$user_stmt->bind_param(
    "i",
    $user_id
);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


/* =========================================================
   TOTAL STAGES
========================================================= */

$total_stages_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM stages"
);

$total_stages = $total_stages_result->fetch_assoc()["total"];


/* =========================================================
   AVAILABLE STAGES TODAY
========================================================= */

$available_stages_result = $conn->query(
    "SELECT
        s.stage_id,
        s.stage_name,
        s.location,
        s.capacity,
        s.description,
        s.status
     FROM stages s
     WHERE s.status = 'available'
     AND NOT EXISTS (
         SELECT 1
         FROM stage_bookings sb
         WHERE sb.stage_id = s.stage_id
         AND sb.booking_date = CURDATE()
         AND sb.booking_status IN ('pending', 'assigned')
     )
     ORDER BY s.stage_name"
);


/* =========================================================
   AVAILABLE STAGE COUNT
========================================================= */

$available_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM stages s
     WHERE s.status = 'available'
     AND NOT EXISTS (
         SELECT 1
         FROM stage_bookings sb
         WHERE sb.stage_id = s.stage_id
         AND sb.booking_date = CURDATE()
         AND sb.booking_status IN ('pending', 'assigned')
     )"
);

$available_count =
    $available_count_result->fetch_assoc()["total"];


/* =========================================================
   TODAY / UPCOMING BOOKED STAGES
========================================================= */

$booked_stages_stmt = $conn->prepare(
    "SELECT
        sb.booking_id,
        sb.booking_date,
        sb.start_time,
        sb.end_time,
        sb.buffer_minutes,
        sb.booking_status,

        s.stage_id,
        s.stage_name,
        s.location,
        s.capacity,

        e.event_id,
        e.event_name,
        e.requirements,

        sh.cleaned,
        sh.equipment_ready,
        sh.sound_checked,
        sh.lights_checked,
        sh.stage_ready,
        sh.handed_over

     FROM stage_bookings sb

     INNER JOIN stages s
        ON sb.stage_id = s.stage_id

     INNER JOIN events e
        ON sb.event_id = e.event_id

     LEFT JOIN stage_handover sh
        ON sb.booking_id = sh.booking_id

     WHERE sb.booking_date >= CURDATE()

     AND sb.booking_status IN ('pending', 'assigned')

     ORDER BY
        sb.booking_date ASC,
        sb.start_time ASC"
);

$booked_stages_stmt->execute();

$booked_stages =
    $booked_stages_stmt->get_result();


/* =========================================================
   BOOKED COUNT
========================================================= */

$booked_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM stage_bookings
     WHERE booking_date >= CURDATE()
     AND booking_status IN ('pending', 'assigned')"
);

$booked_count =
    $booked_count_result->fetch_assoc()["total"];


/* =========================================================
   TODAY'S BOOKINGS COUNT
========================================================= */

$today_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM stage_bookings
     WHERE booking_date = CURDATE()
     AND booking_status IN ('pending', 'assigned')"
);

$today_count =
    $today_count_result->fetch_assoc()["total"];


/* =========================================================
   FORMAT TIME
========================================================= */

function formatTime($time)
{
    if (empty($time)) {
        return "-";
    }

    return date(
        "h:i A",
        strtotime($time)
    );
}


/* =========================================================
   HANDOVER STATUS
========================================================= */

function getHandoverStatus(
    $stage_ready,
    $handed_over
) {

    if ((int)$handed_over === 1) {

        return [
            "text" => "Handed Over",
            "class" => "handover-complete"
        ];

    }

    if ((int)$stage_ready === 1) {

        return [
            "text" => "Stage Ready",
            "class" => "handover-ready"
        ];

    }

    return [
        "text" => "Preparation Pending",
        "class" => "handover-pending"
    ];
}


/* =========================================================
   PAGE
========================================================= */

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Stage Manager Dashboard - Event Hub
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family: Arial, sans-serif;

            background: #f5f0ff;

            color: #333;

        }


        /* =================================================
           SIDEBAR
        ================================================= */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            width: 240px;

            height: 100vh;

            background: #4f3295;

            color: white;

            padding: 25px 15px;

        }


        .sidebar-title {

            text-align: center;

            font-size: 22px;

            font-weight: bold;

            margin-bottom: 35px;

        }


        .sidebar-subtitle {

            text-align: center;

            font-size: 13px;

            color: #ddd;

            margin-top: -25px;

            margin-bottom: 30px;

        }


        .nav-link {

            display: block;

            padding: 13px 15px;

            margin-bottom: 8px;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-size: 15px;

        }


        .nav-link:hover,

        .nav-link.active {

            background: #6b46a1;

        }


        /* =================================================
           MAIN
        ================================================= */

        .main {

            margin-left: 240px;

            padding: 30px;

        }


        .topbar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

        }


        .welcome h1 {

            color: #4f3295;

            font-size: 28px;

            margin-bottom: 6px;

        }


        .welcome p {

            color: #666;

        }


        .manager-badge {

            background: #e9ddff;

            color: #4f3295;

            padding: 10px 16px;

            border-radius: 20px;

            font-size: 14px;

            font-weight: bold;

        }


        /* =================================================
           STAT CARDS
        ================================================= */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 30px;

        }


        .stat-card {

            background: white;

            padding: 22px;

            border-radius: 14px;

            box-shadow:
                0 5px 18px
                rgba(91, 59, 140, 0.10);

        }


        .stat-title {

            color: #777;

            font-size: 14px;

            margin-bottom: 10px;

        }


        .stat-number {

            font-size: 30px;

            font-weight: bold;

            color: #4f3295;

        }


        /* =================================================
           SECTION
        ================================================= */

        .section {

            margin-bottom: 30px;

        }


        .section-title {

            color: #4f3295;

            font-size: 21px;

            margin-bottom: 15px;

        }


        /* =================================================
           STAGE CARDS
        ================================================= */

        .stage-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

        }


        .stage-card {

            background: white;

            border-radius: 14px;

            padding: 20px;

            box-shadow:
                0 5px 18px
                rgba(91, 59, 140, 0.10);

        }


        .stage-card h3 {

            color: #4f3295;

            margin-bottom: 10px;

        }


        .stage-card p {

            color: #666;

            font-size: 14px;

            margin-bottom: 7px;

        }


        .available-badge {

            display: inline-block;

            margin-top: 10px;

            padding: 6px 12px;

            background: #e1f7e8;

            color: #218838;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        /* =================================================
           BOOKING CARDS
        ================================================= */

        .booking-list {

            display: flex;

            flex-direction: column;

            gap: 18px;

        }


        .booking-card {

            background: white;

            border-radius: 14px;

            padding: 22px;

            box-shadow:
                0 5px 18px
                rgba(91, 59, 140, 0.10);

        }


        .booking-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 20px;

            margin-bottom: 18px;

        }


        .booking-header h3 {

            color: #4f3295;

            font-size: 20px;

            margin-bottom: 6px;

        }


        .event-name {

            color: #555;

            font-size: 14px;

        }


        .booking-status {

            padding: 6px 12px;

            border-radius: 20px;

            background: #eee3ff;

            color: #5b3b8c;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;

        }


        .booking-details {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

            margin-bottom: 18px;

        }


        .detail-box {

            background: #f8f5ff;

            padding: 12px;

            border-radius: 8px;

        }


        .detail-label {

            color: #777;

            font-size: 12px;

            margin-bottom: 5px;

        }


        .detail-value {

            color: #333;

            font-weight: bold;

            font-size: 14px;

        }


        /* =================================================
           REQUIREMENTS
        ================================================= */

        .requirements {

            background: #faf8ff;

            padding: 15px;

            border-radius: 10px;

            margin-bottom: 15px;

        }


        .requirements-title {

            color: #4f3295;

            font-weight: bold;

            margin-bottom: 8px;

        }


        .requirements-text {

            color: #555;

            font-size: 14px;

            line-height: 1.6;

        }


        /* =================================================
           HANDOVER
        ================================================= */

        .handover {

            display: inline-block;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .handover-complete {

            background: #e1f7e8;

            color: #218838;

        }


        .handover-ready {

            background: #e5f1ff;

            color: #1769aa;

        }


        .handover-pending {

            background: #fff1d6;

            color: #9a6700;

        }


        /* =================================================
           EMPTY STATE
        ================================================= */

        .empty {

            background: white;

            padding: 30px;

            text-align: center;

            border-radius: 14px;

            color: #777;

            box-shadow:
                0 5px 18px
                rgba(91, 59, 140, 0.08);

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1100px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .stage-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .booking-details {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 750px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;

            }


            .main {

                margin-left: 0;

                padding: 20px;

            }


            .topbar {

                flex-direction: column;

                align-items: flex-start;

                gap: 12px;

            }


            .stats,

            .stage-grid,

            .booking-details {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="sidebar-title">
            Event Hub
        </div>

        <div class="sidebar-subtitle">
            Stage Manager
        </div>


        <a
            href="dashboard.php"
            class="nav-link active"
        >
            🏠 Dashboard
        </a>


        <a
            href="equipment.php"
            class="nav-link"
        >
            🎤 Equipment
        </a>


        <a
            href="handover.php"
            class="nav-link"
        >
            🤝 Stage Handover
        </a>


        <a
            href="profile.php"
            class="nav-link"
        >
            👤 Profile
        </a>


        <a
            href="../login.html"
            class="nav-link"
        >
            🚪 Logout
        </a>

    </aside>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="main">


        <!-- TOP BAR -->

        <div class="topbar">

            <div class="welcome">

                <h1>
                    Welcome,
                    <?php
                    echo htmlspecialchars($user["name"]);
                    ?> 👋
                </h1>

                <p>
                    Stage Manager Dashboard
                </p>

            </div>


            <div class="manager-badge">
                Stage Manager
            </div>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-title">
                    Total Stages
                </div>

                <div class="stat-number">
                    <?php echo $total_stages; ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Available Today
                </div>

                <div class="stat-number">
                    <?php echo $available_count; ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Booked / Assigned
                </div>

                <div class="stat-number">
                    <?php echo $booked_count; ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Today's Bookings
                </div>

                <div class="stat-number">
                    <?php echo $today_count; ?>
                </div>

            </div>


        </div>


        <!-- =================================================
             AVAILABLE STAGES
        ================================================== -->

        <section class="section">

            <h2 class="section-title">
                🟢 Available / Free Stages Today
            </h2>


            <?php if ($available_stages_result->num_rows > 0): ?>


                <div class="stage-grid">


                    <?php while (
                        $stage =
                        $available_stages_result->fetch_assoc()
                    ): ?>


                        <div class="stage-card">

                            <h3>
                                <?php
                                echo htmlspecialchars(
                                    $stage["stage_name"]
                                );
                                ?>
                            </h3>


                            <p>

                                <strong>
                                    Location:
                                </strong>

                                <?php

                                echo htmlspecialchars(
                                    $stage["location"] ?? "-"
                                );

                                ?>

                            </p>


                            <p>

                                <strong>
                                    Capacity:
                                </strong>

                                <?php

                                echo htmlspecialchars(
                                    $stage["capacity"] ?? "-"
                                );

                                ?>

                            </p>


                            <?php if (
                                !empty($stage["description"])
                            ): ?>

                                <p>

                                    <?php

                                    echo htmlspecialchars(
                                        $stage["description"]
                                    );

                                    ?>

                                </p>

                            <?php endif; ?>


                            <span class="available-badge">
                                Available Today
                            </span>

                        </div>


                    <?php endwhile; ?>


                </div>


            <?php else: ?>


                <div class="empty">

                    No free stages are available today.

                </div>


            <?php endif; ?>


        </section>


        <!-- =================================================
             BOOKED STAGES
        ================================================== -->

        <section class="section">

            <h2 class="section-title">
                🔴 Booked / Upcoming Stages
            </h2>


            <?php if ($booked_stages->num_rows > 0): ?>


                <div class="booking-list">


                    <?php while (
                        $booking =
                        $booked_stages->fetch_assoc()
                    ): ?>


                        <div class="booking-card">


                            <div class="booking-header">


                                <div>

                                    <h3>

                                        <?php

                                        echo htmlspecialchars(
                                            $booking["stage_name"]
                                        );

                                        ?>

                                    </h3>


                                    <div class="event-name">

                                        Event:

                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $booking["event_name"]
                                            );

                                            ?>

                                        </strong>

                                    </div>

                                </div>


                                <span class="booking-status">

                                    <?php

                                    echo htmlspecialchars(
                                        ucfirst(
                                            $booking["booking_status"]
                                        )
                                    );

                                    ?>

                                </span>


                            </div>


                            <!-- BOOKING DETAILS -->

                            <div class="booking-details">


                                <div class="detail-box">

                                    <div class="detail-label">
                                        Date
                                    </div>

                                    <div class="detail-value">

                                        <?php

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $booking["booking_date"]
                                            )
                                        );

                                        ?>

                                    </div>

                                </div>


                                <div class="detail-box">

                                    <div class="detail-label">
                                        Start Time
                                    </div>

                                    <div class="detail-value">

                                        <?php

                                        echo formatTime(
                                            $booking["start_time"]
                                        );

                                        ?>

                                    </div>

                                </div>


                                <div class="detail-box">

                                    <div class="detail-label">
                                        End Time
                                    </div>

                                    <div class="detail-value">

                                        <?php

                                        echo formatTime(
                                            $booking["end_time"]
                                        );

                                        ?>

                                    </div>

                                </div>


                                <div class="detail-box">

                                    <div class="detail-label">
                                        Buffer / Cleanup
                                    </div>

                                    <div class="detail-value">

                                        <?php

                                        echo (int)
                                            $booking["buffer_minutes"];

                                        ?>
                                        minutes

                                    </div>

                                </div>


                            </div>


                            <!-- LOCATION -->

                            <div class="detail-box">

                                <div class="detail-label">
                                    Stage Location
                                </div>

                                <div class="detail-value">

                                    <?php

                                    echo htmlspecialchars(
                                        $booking["location"] ?? "-"
                                    );

                                    ?>

                                </div>

                            </div>


                            <br>


                            <!-- REQUIREMENTS -->

                            <div class="requirements">

                                <div class="requirements-title">

                                    🎤 Event Requirements

                                </div>


                                <div class="requirements-text">

                                    <?php

                                    if (
                                        !empty(
                                            $booking["requirements"]
                                        )
                                    ) {

                                        echo nl2br(
                                            htmlspecialchars(
                                                $booking["requirements"]
                                            )
                                        );

                                    } else {

                                        echo "No specific requirements provided.";

                                    }

                                    ?>

                                </div>

                            </div>


                            <!-- HANDOVER STATUS -->

                            <?php

                            $handover =
                                getHandoverStatus(
                                    $booking["stage_ready"],
                                    $booking["handed_over"]
                                );

                            ?>


                            <div>

                                <strong>
                                    Stage Handover:
                                </strong>


                                <span
                                    class="handover
                                    <?php
                                    echo $handover["class"];
                                    ?>"
                                >

                                    <?php

                                    echo $handover["text"];

                                    ?>

                                </span>

                            </div>


                        </div>


                    <?php endwhile; ?>


                </div>


            <?php else: ?>


                <div class="empty">

                    No booked or upcoming stages found.

                </div>


            <?php endif; ?>


        </section>


    </main>


</body>

</html>