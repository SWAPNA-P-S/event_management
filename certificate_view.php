<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];

$certificate_id = $_GET["certificate_id"] ?? "";

if (empty($certificate_id) || !is_numeric($certificate_id)) {
    die("Certificate not found.");
}

$stmt = $conn->prepare(
    "SELECT
        c.certificate_id,
        c.certificate_number,
        c.certificate_type,
        c.issued_date,
        r.registration_id,
        e.event_name,
        e.event_date,
        e.venue,
        u.name
     FROM certificates c
     JOIN registrations r
        ON c.registration_id = r.registration_id
     JOIN events e
        ON r.event_id = e.event_id
     JOIN users u
        ON r.user_id = u.id
     WHERE c.certificate_id = ?
       AND r.user_id = ?
       AND c.status = 'issued'"
);

$stmt->bind_param(
    "ii",
    $certificate_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Certificate not found.");
}

$certificate = $result->fetch_assoc();

$stmt->close();
$conn->close();

$certificate_type = !empty($certificate["certificate_type"])
    ? ucfirst($certificate["certificate_type"])
    : "Participation";

$issued_date = !empty($certificate["issued_date"])
    ? date("d F Y", strtotime($certificate["issued_date"]))
    : "Not specified";

$event_date = !empty($certificate["event_date"])
    ? date("d F Y", strtotime($certificate["event_date"]))
    : "Not specified";

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
        Certificate - Event Hub
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 35px 20px;
            font-family: Georgia, "Times New Roman", serif;
            background: #EEF3F8;
            color: #172033;
        }

        /* =========================
           ACTION BUTTONS
        ========================= */

        .actions {
            max-width: 1100px;
            margin: 0 auto 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .back-btn,
        .print-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .back-btn {
            background: #FFFFFF;
            color: #0B1F3A;
            border: 1px solid #DCE4EF;
        }

        .print-btn {
            background: #1E4E8C;
            color: #FFFFFF;
        }

        .back-btn:hover {
            background: #F4F7FB;
        }

        .print-btn:hover {
            background: #0B1F3A;
        }

        /* =========================
           CERTIFICATE
        ========================= */

        .certificate-wrapper {
            max-width: 1100px;
            margin: auto;
        }

        .certificate {
            position: relative;
            width: 100%;
            min-height: 720px;
            padding: 48px;
            background: #FFFFFF;
            border: 2px solid #0B1F3A;
            box-shadow: 0 10px 35px rgba(11, 31, 58, 0.12);
            overflow: hidden;
        }

        /* Outer decorative border */

        .certificate::before {
            content: "";
            position: absolute;
            inset: 12px;
            border: 1px solid #B9C9DD;
            pointer-events: none;
        }

        /* Corner decorations */

        .corner {
            position: absolute;
            width: 70px;
            height: 70px;
            border-color: #1E4E8C;
            border-style: solid;
            pointer-events: none;
        }

        .corner.top-left {
            top: 25px;
            left: 25px;
            border-width: 4px 0 0 4px;
        }

        .corner.top-right {
            top: 25px;
            right: 25px;
            border-width: 4px 4px 0 0;
        }

        .corner.bottom-left {
            bottom: 25px;
            left: 25px;
            border-width: 0 0 4px 4px;
        }

        .corner.bottom-right {
            bottom: 25px;
            right: 25px;
            border-width: 0 4px 4px 0;
        }

        /* =========================
           HEADER
        ========================= */

        .certificate-header {
            position: relative;
            text-align: center;
            z-index: 1;
        }

        .college-name {
            font-family: Arial, Helvetica, sans-serif;
            color: #0B1F3A;
            font-size: 27px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }

        .college-location {
            font-family: Arial, Helvetica, sans-serif;
            color: #667085;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .header-line {
            width: 150px;
            height: 2px;
            margin: 17px auto;
            background: #1E4E8C;
        }

        /* =========================
           TITLE
        ========================= */

        .certificate-label {
            text-align: center;
            color: #1E4E8C;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 3px;
            margin-top: 20px;
        }

        .certificate-title {
            text-align: center;
            color: #0B1F3A;
            font-size: 43px;
            letter-spacing: 2px;
            margin: 8px 0 12px;
            font-weight: 700;
        }

        .presented-text {
            text-align: center;
            color: #667085;
            font-size: 17px;
            font-style: italic;
        }

        /* =========================
           STUDENT
        ========================= */

        .student-name {
            text-align: center;
            color: #1E4E8C;
            font-size: 38px;
            font-weight: 700;
            margin: 22px 0 8px;
            padding: 0 20px;
        }

        .student-line {
            width: 420px;
            max-width: 80%;
            height: 1px;
            background: #B9C9DD;
            margin: 0 auto 20px;
        }

        /* =========================
           EVENT
        ========================= */

        .participation-text {
            text-align: center;
            color: #4B5563;
            font-size: 16px;
            line-height: 1.7;
        }

        .event-name {
            text-align: center;
            color: #0B1F3A;
            font-size: 27px;
            font-weight: 700;
            margin: 10px auto;
            max-width: 800px;
        }

        .event-details {
            text-align: center;
            color: #667085;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.8;
            margin-top: 8px;
        }

        /* =========================
           FOOTER
        ========================= */

        .certificate-footer {
            position: absolute;
            left: 65px;
            right: 65px;
            bottom: 48px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            align-items: end;
            gap: 25px;
            z-index: 1;
        }

        .footer-item {
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            color: #667085;
            font-size: 12px;
        }

        .footer-line {
            width: 150px;
            height: 1px;
            background: #8A99AA;
            margin: 0 auto 8px;
        }

        .footer-label {
            color: #172033;
            font-weight: 600;
        }

        .certificate-number {
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            color: #667085;
            font-size: 11px;
            line-height: 1.7;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            body {
                padding: 20px 10px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .back-btn,
            .print-btn {
                width: 100%;
            }

            .certificate {
                min-height: 800px;
                padding: 45px 25px;
            }

            .college-name {
                font-size: 21px;
            }

            .certificate-title {
                font-size: 30px;
            }

            .student-name {
                font-size: 29px;
            }

            .event-name {
                font-size: 22px;
            }

            .certificate-footer {
                left: 30px;
                right: 30px;
                bottom: 35px;
                grid-template-columns: 1fr;
                gap: 18px;
            }

        }

        /* =========================
           PRINT
        ========================= */

        @media print {

            @page {
                size: A4 landscape;
                margin: 0;
            }

            body {
                padding: 0;
                background: #FFFFFF;
            }

            .actions {
                display: none;
            }

            .certificate-wrapper {
                width: 100%;
                max-width: none;
            }

            .certificate {
                width: 100vw;
                height: 100vh;
                min-height: 0;
                border: 2px solid #0B1F3A;
                box-shadow: none;
                page-break-inside: avoid;
            }

        }

    </style>

</head>

<body>


<div class="actions">

    <a
        href="certificate.php"
        class="back-btn"
    >
        ← Back to Certificates
    </a>

    <button
        type="button"
        class="print-btn"
        onclick="window.print()"
    >
        🖨️ Print / Save PDF
    </button>

</div>


<div class="certificate-wrapper">

    <div class="certificate">

        <div class="corner top-left"></div>
        <div class="corner top-right"></div>
        <div class="corner bottom-left"></div>
        <div class="corner bottom-right"></div>


        <!-- HEADER -->

        <div class="certificate-header">

            <div class="college-name">
                EVENT HUB
            </div>

            <div class="college-location">
                College Event Management System
            </div>

            <div class="header-line"></div>

        </div>


        <!-- TITLE -->

        <div class="certificate-label">
            OFFICIAL CERTIFICATE
        </div>

        <div class="certificate-title">

            CERTIFICATE OF
            <?php echo strtoupper(htmlspecialchars($certificate_type)); ?>

        </div>

        <div class="presented-text">
            This certificate is proudly presented to
        </div>


        <!-- STUDENT -->

        <div class="student-name">

            <?php
            echo htmlspecialchars($certificate["name"]);
            ?>

        </div>

        <div class="student-line"></div>


        <!-- EVENT -->

        <div class="participation-text">
            for successfully participating in
        </div>

        <div class="event-name">

            <?php
            echo htmlspecialchars($certificate["event_name"]);
            ?>

        </div>


        <div class="event-details">

            Event Date:
            <?php echo htmlspecialchars($event_date); ?>

            &nbsp;&nbsp; | &nbsp;&nbsp;

            Venue:
            <?php
            echo htmlspecialchars($certificate["venue"]);
            ?>

        </div>


        <!-- FOOTER -->

        <div class="certificate-footer">

            <div class="footer-item">

                <div class="footer-line"></div>

                <div class="footer-label">
                    Event Coordinator
                </div>

            </div>


            <div class="certificate-number">

                Certificate No:
                <?php
                echo htmlspecialchars(
                    $certificate["certificate_number"]
                );
                ?>

                <br>

                Issued Date:
                <?php echo htmlspecialchars($issued_date); ?>

            </div>


            <div class="footer-item">

                <div class="footer-line"></div>

                <div class="footer-label">
                    Authorized Signature
                </div>

            </div>

        </div>


    </div>

</div>


</body>

</html>