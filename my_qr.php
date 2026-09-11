<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| REGISTRATION ID GIVEN
| Show QR for one particular paid registration
|--------------------------------------------------------------------------
*/

if (isset($_GET["registration_id"])) {

    $registration_id = $_GET["registration_id"];


    $stmt = $conn->prepare(
        "SELECT
            r.registration_id,
            r.registration_type,
            r.status AS registration_status,
            e.event_name,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.event_type,
            e.fee,
            p.status AS payment_status
         FROM registrations r
         JOIN events e
         ON r.event_id = e.event_id
         LEFT JOIN payments p
         ON r.registration_id = p.registration_id
         WHERE r.registration_id = ?
         AND r.user_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $registration_id,
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();


    if ($result->num_rows === 0) {

        die("Registration not found.");

    }


    $registration = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | QR ONLY FOR PAID EVENTS
    |--------------------------------------------------------------------------
    */

    if ((float)$registration["fee"] <= 0) {

        die("QR code is only available for paid events.");

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT NOT VERIFIED
    |--------------------------------------------------------------------------
    */

    if ($registration["payment_status"] !== "verified") {

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payment Verification - Event Hub</title>

    <link
        rel="stylesheet"
        href="css/my_qr.css"
    >

</head>


<body>


<header>

    <div class="logo">
        Event Hub
    </div>

    <a
        href="student_dashboard.php"
        class="dashboard-link"
    >
        Dashboard
    </a>

</header>


<main class="page">

    <div class="container">


        <a
            href="my_qr.php"
            class="back-link"
        >
            ← Back to My QR
        </a>


        <div class="qr-card">


            <div class="event-badge">
                PAYMENT PENDING
            </div>


            <h2>
                QR Code Not Available Yet
            </h2>


            <p class="subtitle">
                Your payment is currently waiting for verification.
            </p>


            <div class="student-details">


                <div class="detail">

                    <span>Event</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $registration["event_name"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="detail">

                    <span>Registration ID</span>

                    <strong>
                        EVT-<?php
                        echo $registration["registration_id"];
                        ?>
                    </strong>

                </div>


                <div class="detail">

                    <span>Payment Status</span>

                    <strong style="color:#b7791f;">

                        ⏳ Verification Pending

                    </strong>

                </div>


            </div>


            <div class="note">

                Please wait until your payment is verified
                by the administrator.

            </div>


            <a
                href="my_registration.php"
                class="qr-btn"
            >
                ← Back to My Registrations
            </a>


        </div>

    </div>

</main>


</body>

</html>

<?php

        exit();

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT VERIFIED
    | SHOW ACTUAL QR
    |--------------------------------------------------------------------------
    */

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My QR - Event Hub</title>


    <link
        rel="stylesheet"
        href="css/my_qr.css"
    >


    <!-- QR CODE LIBRARY -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
    ></script>

</head>


<body>


<header>

    <div class="logo">
        Event Hub
    </div>


    <a
        href="student_dashboard.php"
        class="dashboard-link"
    >
        Dashboard
    </a>

</header>


<main class="page">

    <div class="container">


        <a
            href="my_qr.php"
            class="back-link"
        >
            ← Back to My QR
        </a>


        <h1>
            My QR Code
        </h1>


        <p class="subtitle">
            Use this QR code for event attendance.
        </p>


        <div class="qr-card">


            <div class="event-badge">

                <?php

                echo strtoupper(
                    htmlspecialchars(
                        $registration["event_type"]
                    )
                );

                ?>

                EVENT

            </div>


            <h2>

                <?php
                echo htmlspecialchars(
                    $registration["event_name"]
                );
                ?>

            </h2>


            <p class="event-date">

                📅

                <?php
                echo date(
                    "d F Y",
                    strtotime(
                        $registration["event_date"]
                    )
                );
                ?>

            </p>


            <!-- ACTUAL QR -->

            <div class="qr-container">

                <div id="qrcode"></div>

            </div>


            <p class="scan-text">

                Show this QR code at the event entrance.

            </p>


            <div class="student-details">


                <div class="detail">

                    <span>Student Name</span>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $_SESSION["user_name"]
                        );
                        ?>

                    </strong>

                </div>


                <div class="detail">

                    <span>Registration ID</span>

                    <strong>

                        EVT-<?php
                        echo $registration["registration_id"];
                        ?>

                    </strong>

                </div>


                <div class="detail">

                    <span>Venue</span>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $registration["venue"]
                        );
                        ?>

                    </strong>

                </div>


                <div class="detail">

                    <span>Status</span>

                    <strong class="confirmed">

                        ✓ Payment Verified

                    </strong>

                </div>


            </div>


            <div class="note">

                Please keep this QR code ready during the event.

            </div>


        </div>

    </div>

</main>


<!-- GENERATE QR -->

<script>

    const qrElement =
        document.getElementById("qrcode");


    if (qrElement) {

        new QRCode(qrElement, {

            text:
                "EVT-<?php echo $registration["registration_id"]; ?>",

            width: 200,

            height: 200

        });

    }

</script>


</body>

</html>

<?php

    exit();

}


/*
|--------------------------------------------------------------------------
| NO REGISTRATION ID
| SHOW ALL PAID REGISTRATIONS
|--------------------------------------------------------------------------
*/


$stmt = $conn->prepare(
    "SELECT
        r.registration_id,
        r.status AS registration_status,
        e.event_name,
        e.event_date,
        e.venue,
        e.fee,
        p.status AS payment_status
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     LEFT JOIN payments p
     ON r.registration_id = p.registration_id
     WHERE r.user_id = ?
     AND e.fee > 0
     ORDER BY e.event_date ASC"
);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$registrations = $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My QR - Event Hub</title>


    <link
        rel="stylesheet"
        href="css/my_qr.css"
    >

</head>


<body>


<header>

    <div class="logo">
        Event Hub
    </div>


    <a
        href="student_dashboard.php"
        class="dashboard-link"
    >
        Dashboard
    </a>

</header>


<main class="page">

    <div class="container">


        <a
            href="student_dashboard.php"
            class="back-link"
        >
            ← Back to Dashboard
        </a>


        <h1>
            My QR Codes
        </h1>


        <p class="subtitle">

            Access your QR codes for paid event attendance.

        </p>


        <?php if ($registrations->num_rows > 0): ?>


            <?php

            while (
                $registration =
                $registrations->fetch_assoc()
            ):

            ?>


                <div class="qr-card">


                    <div class="event-badge">
                        PAID EVENT
                    </div>


                    <h2>

                        <?php
                        echo htmlspecialchars(
                            $registration["event_name"]
                        );
                        ?>

                    </h2>


                    <p class="event-date">

                        📅

                        <?php
                        echo date(
                            "d F Y",
                            strtotime(
                                $registration["event_date"]
                            )
                        );
                        ?>

                    </p>


                    <div class="student-details">


                        <div class="detail">

                            <span>
                                Registration ID
                            </span>

                            <strong>

                                EVT-<?php
                                echo $registration["registration_id"];
                                ?>

                            </strong>

                        </div>


                        <div class="detail">

                            <span>
                                Venue
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $registration["venue"]
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="detail">

                            <span>
                                Payment Status
                            </span>


                            <?php

                            if (
                                $registration["payment_status"]
                                === "verified"
                            ):

                            ?>

                                <strong class="confirmed">

                                    ✓ Payment Verified

                                </strong>


                            <?php

                            elseif (
                                $registration["payment_status"]
                                === "pending"
                            ):

                            ?>

                                <strong
                                    style="color:#b7791f;"
                                >

                                    ⏳ Verification Pending

                                </strong>


                            <?php else: ?>


                                <strong
                                    style="color:#b91c1c;"
                                >

                                    Payment Not Verified

                                </strong>


                            <?php endif; ?>


                        </div>


                    </div>


                    <?php

                    if (
                        $registration["payment_status"]
                        === "verified"
                    ):

                    ?>


                        <a
                            href="my_qr.php?registration_id=<?php
                                echo $registration[
                                    "registration_id"
                                ];
                            ?>"
                            class="qr-btn"
                        >

                            View QR Code

                        </a>


                    <?php else: ?>


                        <div class="note">

                            QR code will be available
                            after payment verification.

                        </div>


                    <?php endif; ?>


                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <div
                class="qr-card"
                style="text-align:center;"
            >

                <h2>
                    No Paid Events
                </h2>


                <p class="subtitle">

                    You have not registered for any
                    paid events yet.

                </p>


                <a
                    href="events.php"
                    class="qr-btn"
                >

                    Browse Events

                </a>

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