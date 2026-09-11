<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];

$certificate_id = $_GET["certificate_id"] ?? "";

if (empty($certificate_id)) {
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
     AND r.user_id = ?"
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Certificate - Event Hub</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, serif;
            background: #f1f5f9;
            padding: 40px 20px;
        }

        .certificate {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 60px;
            border: 10px solid #172554;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            text-align: center;
        }

        .college {
            font-size: 18px;
            color: #555;
            margin-bottom: 15px;
        }

        h1 {
            font-size: 42px;
            color: #172554;
            margin: 10px 0;
        }

        .subtitle {
            font-size: 20px;
            color: #555;
            margin-bottom: 35px;
        }

        .student-name {
            font-size: 34px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 20px 0;
        }

        .event-name {
            font-size: 25px;
            font-weight: bold;
            margin: 20px 0;
        }

        .details {
            color: #555;
            font-size: 17px;
            line-height: 1.8;
        }

        .certificate-number {
            margin-top: 35px;
            font-size: 14px;
            color: #777;
        }

        .buttons {
            text-align: center;
            margin-top: 25px;
        }

        button,
        a {
            display: inline-block;
            padding: 12px 20px;
            margin: 5px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }

        button {
            background: #263a8b;
            color: white;
        }

        a {
            background: #e2e8f0;
            color: #172554;
        }

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .certificate {
                box-shadow: none;
                max-width: 100%;
            }

            .buttons {
                display: none;
            }

        }

    </style>

</head>

<body>

<div class="certificate">

    <div class="college">
        EVENT HUB
    </div>

    <h1>
        CERTIFICATE OF PARTICIPATION
    </h1>

    <div class="subtitle">
        This certificate is proudly presented to
    </div>

    <div class="student-name">
        <?php
        echo htmlspecialchars($certificate["name"]);
        ?>
    </div>

    <div class="details">
        for successfully participating in
    </div>

    <div class="event-name">
        <?php
        echo htmlspecialchars($certificate["event_name"]);
        ?>
    </div>

    <div class="details">

        Event Date:
        <?php
        echo date(
            "d F Y",
            strtotime($certificate["event_date"])
        );
        ?>

        <br>

        Venue:
        <?php
        echo htmlspecialchars($certificate["venue"]);
        ?>

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
        <?php
        echo $certificate["issued_date"]
            ? date(
                "d F Y",
                strtotime($certificate["issued_date"])
            )
            : "Not specified";
        ?>

    </div>

</div>


<div class="buttons">

    <button onclick="window.print()">
        🖨️ Print / Save PDF
    </button>

    <a href="certificate.php">
        ← Back to Certificates
    </a>

</div>

</body>

</html>