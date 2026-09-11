<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];

$query = $conn->prepare(
    "SELECT
        r.registration_id,
        r.registration_type,
        r.status AS registration_status,
        e.event_id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.fee,
        p.status AS payment_status
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     LEFT JOIN payments p
     ON r.registration_id = p.registration_id
     WHERE r.user_id = ?
     ORDER BY e.event_date ASC, e.start_time ASC"
);

$query->bind_param("i", $user_id);
$query->execute();

$registrations = $query->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations - Event Hub</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fb;
            color: #222;
        }

        /* Header */
        header {
            background: #172554;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        header a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        header a:hover {
            text-decoration: underline;
        }

        /* Main */
        .page {
            min-height: calc(100vh - 70px);
            padding: 40px 20px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        h1 {
            color: #172554;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        /* Registration Card */
        .registration-card {
            background: white;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .event-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }

        .event-top h2 {
            color: #1e3a8a;
            font-size: 22px;
        }

        .status {
            background: #dcfce7;
            color: #166534;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .event-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-box {
            background: #f8f9ff;
            padding: 14px;
            border-radius: 8px;
        }

        .info-box span {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .info-box strong {
            color: #333;
            font-size: 15px;
        }

        .payment-status {
            border-top: 1px solid #eee;
            padding-top: 18px;
            margin-top: 5px;
            color: #444;
        }

        .payment-status strong {
            color: #166534;
        }

        /* Buttons */
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .qr-btn,
        .details-btn {
            display: inline-block;
            padding: 11px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .qr-btn {
            background: #263a8b;
            color: white;
        }

        .qr-btn:hover {
            background: #1d2f73;
        }

        .details-btn {
            background: #eef2ff;
            color: #263a8b;
        }

        .details-btn:hover {
            background: #e0e7ff;
        }

        /* Back button */
        .back-dashboard {
            display: inline-block;
            margin-bottom: 25px;
            color: #263a8b;
            text-decoration: none;
            font-weight: 600;
        }

        .back-dashboard:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 600px) {

            header {
                padding: 16px 20px;
            }

            .page {
                padding: 30px 15px;
            }

            h1 {
                font-size: 27px;
            }

            .event-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .event-info {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            .qr-btn,
            .details-btn {
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <!-- Header -->
    <header>
        <div class="logo">Event Hub</div>
    </header>


    <!-- Main Content -->
    <main class="page">

        <div class="container">

            <!-- Back to Dashboard -->
            <a href="student_dashboard.php" class="back-dashboard">
                ← Back to Dashboard
            </a>

            <h1>My Registrations</h1>

            <p class="subtitle">
                View and manage the events you have registered for.
            </p>


            <?php if ($registrations->num_rows > 0): ?>

                <?php while ($registration = $registrations->fetch_assoc()): ?>

                    <div class="registration-card">

                        <div class="event-top">

                            <h2>
                                <?php echo htmlspecialchars($registration["event_name"]); ?>
                            </h2>

                            <span class="status">
                                ✓ Registered
                            </span>

                        </div>


                        <div class="event-info">

                            <div class="info-box">

                                <span>📅 Date</span>

                                <strong>
                                    <?php
                                    echo date(
                                        "d F Y",
                                        strtotime($registration["event_date"])
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="info-box">

                                <span>⏰ Time</span>

                                <strong>

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime($registration["start_time"])
                                    );
                                    ?>

                                    –

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime($registration["end_time"])
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <span>📍 Venue</span>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $registration["venue"]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="info-box">

                                <span>🎟️ Registration Fee</span>

                                <strong>

                                    <?php if ((float)$registration["fee"] == 0): ?>

                                        FREE

                                    <?php else: ?>

                                        ₹<?php
                                        echo number_format(
                                            (float)$registration["fee"],
                                            2
                                        );
                                        ?>

                                    <?php endif; ?>

                                </strong>

                            </div>

                        </div>


                        <div class="payment-status">

                            Payment Status:

                            <?php if ((float)$registration["fee"] == 0): ?>

                                <strong>✓ No Payment Required</strong>

                            <?php elseif ($registration["payment_status"] === "pending"): ?>

                                <strong style="color:#b7791f;">
                                    ⏳ Payment Verification Pending
                                </strong>

                            <?php elseif ($registration["payment_status"] === "verified"): ?>

                                <strong>
                                    ✓ Payment Verified
                                </strong>

                            <?php else: ?>

                                <strong style="color:#b91c1c;">
                                    Payment Pending
                                </strong>

                            <?php endif; ?>

                        </div>


                        <div class="actions">

                            <?php if ((float)$registration["fee"] == 0): ?>

                                <!-- Free Event -->
                                <a
                                    href="attendance.php?registration_id=<?php echo $registration["registration_id"]; ?>"
                                    class="qr-btn"
                                >
                                    View My Attendance
                                </a>

                            <?php else: ?>

                                <!-- Paid Event -->
                                <a
                                    href="my_qr.php?registration_id=<?php echo $registration["registration_id"]; ?>"
                                    class="qr-btn"
                                >
                                    View My QR
                                </a>

                            <?php endif; ?>


                            <a
                                href="event-details.php?event_id=<?php echo $registration["event_id"]; ?>"
                                class="details-btn"
                            >
                                Event Details
                            </a>

                        </div>

                    </div>

                <?php endwhile; ?>


            <?php else: ?>

                <div class="registration-card" style="text-align:center;">

                    <h2>No Registrations Yet</h2>

                    <p style="color:#666; margin-top:10px;">
                        You have not registered for any events yet.
                    </p>

                    <a
                        href="events.php"
                        class="qr-btn"
                        style="margin-top:20px;"
                    >
                        Browse Events
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>