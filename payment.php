<?php

include "auth_student.php";
include "db.php";

if (!isset($_GET["registration_id"]) || !is_numeric($_GET["registration_id"])) {
    die("Registration not found.");
}

$registration_id = (int) $_GET["registration_id"];
$user_id = $_SESSION["user_id"];


/* =========================================================
   GET REGISTRATION + OFFICIALLY APPROVED EVENT
   ========================================================= */

$stmt = $conn->prepare(
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
        e.fee
     FROM registrations r
     INNER JOIN events e
        ON r.event_id = e.event_id
     INNER JOIN event_brochures eb
        ON e.event_id = eb.event_id
     WHERE r.registration_id = ?
       AND r.user_id = ?
       AND e.request_status = 'approved'
       AND eb.hod_status = 'approved'
       AND eb.status = 'approved'
     ORDER BY eb.brochure_id DESC
     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $registration_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    $stmt->close();
    $conn->close();

    die("Registration not found or event is not available for payment.");
}

$registration = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CHECK WHETHER THIS IS A PAID EVENT
   ========================================================= */

$event_fee = (float) $registration["fee"];

if ($event_fee <= 0) {

    $conn->close();

    die("This is a free event. No payment is required.");
}


/* =========================================================
   CHECK WHETHER PAYMENT IS ALREADY COMPLETED
   ========================================================= */

$payment_check = $conn->prepare(
    "SELECT payment_id
     FROM payments
     WHERE registration_id = ?
     LIMIT 1"
);

$payment_check->bind_param("i", $registration_id);
$payment_check->execute();

$payment_result = $payment_check->get_result();

if ($payment_result->num_rows > 0) {

    $payment_check->close();
    $conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment Already Submitted - Event Hub</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fb;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .message-card {
            width: 90%;
            max-width: 500px;
            background: white;
            padding: 45px;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        h1 {
            color: #2e7d32;
            margin-bottom: 15px;
        }

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            padding: 12px 22px;
            background: #263a8b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

    </style>

</head>

<body>

    <div class="message-card">

        <div class="icon">
            ✓
        </div>

        <h1>Payment Already Submitted</h1>

        <p>
            Your payment details for this registration have already been submitted.
        </p>

        <a href="my_registration.php" class="btn">
            My Registrations
        </a>

    </div>

</body>

</html>

<?php

    exit();
}

$payment_check->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment - Event Hub</title>

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

        .header {
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 60px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #243b80;
        }

        .back-btn {
            text-decoration: none;
            color: #243b80;
            font-weight: bold;
        }

        .main-container {
            max-width: 800px;
            margin: auto;
            padding: 45px 20px;
        }

        h1 {
            text-align: center;
            color: #172554;
            font-size: 34px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .card h2 {
            color: #1e3a8a;
            margin-bottom: 20px;
        }

        .event-info p {
            margin-bottom: 12px;
            color: #555;
        }

        .fee {
            border-top: 1px solid #ddd;
            margin-top: 20px;
            padding-top: 18px;
            display: flex;
            justify-content: space-between;
        }

        .fee strong {
            color: #243b80;
            font-size: 24px;
        }

        .upi {
            background: #eef1ff;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .upi h3 {
            color: #1e3a8a;
            margin-bottom: 5px;
        }

        .upi p {
            color: #666;
        }

        .qr {
            width: 180px;
            height: 180px;
            border: 2px dashed #777;
            border-radius: 10px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
        }

        .qr span {
            font-size: 28px;
            font-weight: bold;
            color: #777;
        }

        .qr-text {
            text-align: center;
            color: #555;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
        }

        .help {
            font-size: 13px;
            color: #777;
            margin-top: 8px;
        }

        .complete-btn {
            display: block;
            width: 100%;
            margin-top: 28px;
            padding: 14px;
            background: #263a8b;
            color: white;
            text-align: center;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
        }

        .complete-btn:hover {
            background: #1d2f73;
        }

        .secure {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin: 20px;
        }

        footer {
            background: #172554;
            color: #dbe3ff;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 600px) {

            .header {
                padding: 0 20px;
            }

            .main-container {
                padding: 30px 15px;
            }

            .card {
                padding: 22px;
            }

        }

    </style>

</head>

<body>

    <header class="header">

        <div class="logo">
            🎓 Event Hub
        </div>

        <a href="events.php" class="back-btn">
            ← Back to Events
        </a>

    </header>


    <main class="main-container">

        <h1>Complete Payment</h1>

        <p class="subtitle">
            Complete your payment to confirm your event registration.
        </p>


        <!-- Event Information -->

        <div class="card">

            <h2>
                <?php echo htmlspecialchars($registration["event_name"]); ?>
            </h2>

            <div class="event-info">

                <p>
                    📅 <strong>Date:</strong>
                    <?php
                    echo date(
                        "d F Y",
                        strtotime($registration["event_date"])
                    );
                    ?>
                </p>

                <p>
                    ⏰ <strong>Time:</strong>
                    <?php
                    echo date(
                        "h:i A",
                        strtotime($registration["start_time"])
                    );
                    ?>
                    -
                    <?php
                    echo date(
                        "h:i A",
                        strtotime($registration["end_time"])
                    );
                    ?>
                </p>

                <p>
                    📍 <strong>Venue:</strong>
                    <?php
                    echo htmlspecialchars($registration["venue"]);
                    ?>
                </p>

            </div>

            <div class="fee">

                <span>
                    Registration Fee
                </span>

                <strong>
                    ₹<?php echo number_format($event_fee, 2); ?>
                </strong>

            </div>

        </div>


        <!-- Payment Card -->

        <div class="card">

            <h2>
                Payment Method
            </h2>

            <form
                action="payment_process.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="registration_id"
                    value="<?php echo $registration_id; ?>"
                >


                <div class="upi">

                    <h3>
                        📱 UPI Payment
                    </h3>

                    <p>
                        Scan the QR code using any UPI app.
                    </p>

                </div>


                <div class="qr">

                    <span>
                        QR
                    </span>

                </div>


                <p class="qr-text">

                    Scan this QR code to pay
                    <strong>
                        ₹<?php echo number_format($event_fee, 2); ?>
                    </strong>

                </p>


                <label>
                    Transaction ID / UTR Number
                </label>

                <input
                    type="text"
                    name="transaction_id"
                    placeholder="Enter your transaction ID"
                    required
                >


                <label>
                    Upload Payment Screenshot
                </label>

                <input
                    type="file"
                    name="payment_screenshot"
                    accept="image/*"
                    required
                >

                <p class="help">
                    Please upload a clear screenshot of your successful payment.
                </p>


                <button
                    type="submit"
                    class="complete-btn"
                >
                    Payment Completed
                </button>

            </form>

        </div>


        <p class="secure">
            🔒 Your payment information is securely handled.
        </p>

    </main>


    <footer>
        © 2026 Event Hub. All Rights Reserved.
    </footer>

</body>

</html>