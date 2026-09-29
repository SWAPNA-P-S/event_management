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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Certificates - Event Hub</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #F4F7FB;
            color: #172033;
        }

        /* HEADER */

        header {
            background: #0B1F3A;
            color: white;
            padding: 18px 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header-inner {
            max-width: 1100px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .header-label {
            font-size: 13px;
            color: #DCE8F7;
        }

        /* PAGE */

        .page {
            min-height: calc(100vh - 70px);
            padding: 40px 20px 60px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        /* BACK */

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 28px;
            color: #1E4E8C;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .back-link:hover {
            color: #0B1F3A;
        }

        /* PAGE TITLE */

        .page-heading {
            margin-bottom: 30px;
        }

        .page-heading h1 {
            color: #0B1F3A;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #667085;
            font-size: 15px;
            line-height: 1.6;
        }

        /* CERTIFICATE CARD */

        .certificate-card {
            background: #FFFFFF;
            border: 1px solid #DCE4EF;
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 22px;
            box-shadow: 0 5px 18px rgba(11, 31, 58, 0.06);
        }

        .certificate-title {
            padding-bottom: 18px;
            margin-bottom: 20px;
            border-bottom: 1px solid #E5EAF1;
        }

        .certificate-title h2 {
            color: #0B1F3A;
            font-size: 21px;
            margin-bottom: 8px;
        }

        .venue {
            color: #667085;
            font-size: 14px;
        }

        /* INFO */

        .certificate-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .info-box {
            background: #F4F7FB;
            border: 1px solid #E1E8F0;
            padding: 15px 17px;
            border-radius: 9px;
        }

        .info-box span {
            display: block;
            color: #667085;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .info-box strong {
            color: #172033;
            font-size: 14px;
            font-weight: 600;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: #E8F7EE;
            color: #16703B;
            border: 1px solid #CBEBD7;
        }

        /* BUTTON */

        .certificate-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 14px;
            padding: 11px 20px;
            background: #1E4E8C;
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .certificate-btn:hover {
            background: #0B1F3A;
        }

        /* PENDING */

        .pending-box {
            background: #FFF8ED;
            color: #9A5B12;
            border: 1px solid #F2D7A6;
            padding: 14px 16px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.5;
        }

        /* NO CERTIFICATE */

        .no-certificate {
            background: #FFFFFF;
            border: 1px solid #DCE4EF;
            padding: 45px 30px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 5px 18px rgba(11, 31, 58, 0.06);
        }

        .no-certificate-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #EAF2FB;
            color: #1E4E8C;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .no-certificate h2 {
            color: #0B1F3A;
            font-size: 21px;
            margin-bottom: 10px;
        }

        .no-certificate p {
            color: #667085;
            line-height: 1.6;
            font-size: 14px;
        }

        /* MOBILE */

        @media (max-width: 650px) {

            header {
                padding: 16px 20px;
            }

            .header-inner {
                align-items: flex-start;
                gap: 10px;
            }

            .header-label {
                display: none;
            }

            .page {
                padding: 30px 15px 45px;
            }

            .page-heading h1 {
                font-size: 27px;
            }

            .certificate-card {
                padding: 22px;
            }

            .certificate-info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<header>

    <div class="header-inner">

        <div class="logo">
            Event Hub
        </div>

        <div class="header-label">
            Student Portal
        </div>

    </div>

</header>


<main class="page">

    <div class="container">

        <a href="student_dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>


        <div class="page-heading">

            <h1>My Certificates</h1>

            <p class="subtitle">
                View certificates for events you have successfully attended
                and whose attendance has been verified.
            </p>

        </div>


        <?php

        $has_event = false;

        while ($event = $result->fetch_assoc()):

            $has_event = true;

        ?>

            <div class="certificate-card">

                <div class="certificate-title">

                    <h2>
                        <?php
                        echo htmlspecialchars($event["event_name"]);
                        ?>
                    </h2>

                    <p class="venue">
                        📍
                        <?php
                        echo htmlspecialchars($event["venue"]);
                        ?>
                    </p>

                </div>


                <div class="certificate-info">

                    <div class="info-box">

                        <span>📅 Event Date</span>

                        <strong>

                            <?php

                            if (!empty($event["event_date"])) {

                                echo date(
                                    "d F Y",
                                    strtotime($event["event_date"])
                                );

                            } else {

                                echo "Not available";

                            }

                            ?>

                        </strong>

                    </div>


                    <div class="info-box">

                        <span>📜 Certificate Type</span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                !empty($event["certificate_type"])
                                    ? ucfirst($event["certificate_type"])
                                    : "Participation"
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

                                if (!empty($event["issued_date"])) {

                                    echo date(
                                        "d F Y",
                                        strtotime($event["issued_date"])
                                    );

                                } else {

                                    echo "Not issued";

                                }

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
                            echo (int)$event["certificate_id"];
                        ?>"
                        class="certificate-btn"
                    >
                        View Certificate
                    </a>


                <?php else: ?>

                    <div class="pending-box">

                        📜 Your attendance has been verified.
                        Your certificate will be issued by the organizer.

                    </div>

                <?php endif; ?>


            </div>


        <?php endwhile; ?>


        <?php if (!$has_event): ?>

            <div class="no-certificate">

                <div class="no-certificate-icon">
                    📜
                </div>

                <h2>
                    No Certificates Yet
                </h2>

                <p>
                    Certificates will be available after you successfully
                    attend an event and your attendance is verified.
                </p>

            </div>

        <?php endif; ?>


    </div>

</main>


<?php

$stmt->close();
$conn->close();

?>

</body>

</html>