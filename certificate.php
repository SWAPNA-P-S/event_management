<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        r.registration_id,
        e.event_name,
        e.event_date,
        e.venue,
        a.verification_status,
        c.certificate_id,
        c.certificate_number,
        c.certificate_type,
        c.issued_date,
        c.status
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     JOIN attendance a
     ON r.registration_id = a.registration_id
     LEFT JOIN certificates c
     ON r.registration_id = c.registration_id
     WHERE r.user_id = ?
     AND a.verification_status = 'verified'
     ORDER BY e.event_date DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Certificates - Event Hub</title>

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

        header {
            background: #172554;
            color: white;
            padding: 18px 40px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        .page {
            min-height: calc(100vh - 70px);
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            color: #263a8b;
            text-decoration: none;
            font-weight: 600;
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

        .certificate-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .certificate-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }

        .certificate-title h2 {
            color: #1e3a8a;
            margin-bottom: 8px;
        }

        .certificate-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-box {
            background: #f8f9ff;
            padding: 15px;
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
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: #dcfce7;
            color: #166534;
        }

        .certificate-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 12px 20px;
            background: #263a8b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }

        .certificate-btn:hover {
            background: #1e3075;
        }

        .pending-box {
            background: #fff7ed;
            color: #9a3412;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .no-certificate {
            background: white;
            padding: 35px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .no-certificate h2 {
            color: #172554;
            margin-bottom: 10px;
        }

        .no-certificate p {
            color: #666;
            line-height: 1.6;
        }

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

            .certificate-info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<header>

    <div class="logo">
        Event Hub
    </div>

</header>


<main class="page">

    <div class="container">

        <a href="student_dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

        <h1>My Certificates</h1>

        <p class="subtitle">
            View certificates for events you have successfully attended.
        </p>


        <?php

        $has_event = false;

        while ($event = $result->fetch_assoc()):

            $has_event = true;

        ?>

            <div class="certificate-card">

                <div class="certificate-title">

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $event["event_name"]
                        );
                        ?>
                    </h2>

                    <p>
                        📍
                        <?php
                        echo htmlspecialchars(
                            $event["venue"]
                        );
                        ?>
                    </p>

                </div>


                <div class="certificate-info">

                    <div class="info-box">

                        <span>📅 Event Date</span>

                        <strong>
                            <?php
                            echo date(
                                "d F Y",
                                strtotime($event["event_date"])
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <span>📜 Certificate Type</span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $event["certificate_type"]
                                ?? "Participation"
                            );
                            ?>
                        </strong>

                    </div>


                    <?php if (!empty($event["certificate_number"])): ?>

                        <div class="info-box">

                            <span>🔢 Certificate Number</span>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $event["certificate_number"]
                                );
                                ?>
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>📅 Issued Date</span>

                            <strong>
                                <?php
                                echo $event["issued_date"]
                                    ? date(
                                        "d F Y",
                                        strtotime(
                                            $event["issued_date"]
                                        )
                                    )
                                    : "Not issued";
                                ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>


                <?php if (!empty($event["certificate_id"])): ?>

                    <span class="status">
                        🟢 Certificate Issued
                    </span>

                    <br>

                    <a
                        href="certificate_view.php?certificate_id=<?php
                            echo $event["certificate_id"];
                        ?>"
                        class="certificate-btn"
                    >
                        View Certificate
                    </a>


                <?php else: ?>

                    <div class="pending-box">

                        📜 Your attendance has been verified.
                        Certificate will be issued by the organizer.

                    </div>

                <?php endif; ?>


            </div>


        <?php endwhile; ?>


        <?php if (!$has_event): ?>

            <div class="no-certificate">

                <h2>
                    📜 No Certificates Yet
                </h2>

                <p>
                    Certificates will be available after you
                    successfully attend and verify an event.
                </p>

            </div>

        <?php endif; ?>


    </div>

</main>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>