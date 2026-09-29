<?php

include "auth_student.php";
include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

if (
    !isset($_POST["registration_id"]) ||
    !is_numeric($_POST["registration_id"])
) {
    die("Invalid registration.");
}

$registration_id = (int) $_POST["registration_id"];
$user_id = $_SESSION["user_id"];

$transaction_id = trim($_POST["transaction_id"] ?? "");

if ($transaction_id === "") {
    die("Please enter the transaction ID / UTR number.");
}


/* =========================================================
   GET REGISTRATION + APPROVED EVENT
   ========================================================= */

$check = $conn->prepare(
    "SELECT
        r.registration_id,
        r.event_id,
        r.status AS registration_status,
        e.event_name,
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

$check->bind_param(
    "ii",
    $registration_id,
    $user_id
);

$check->execute();

$result = $check->get_result();

if ($result->num_rows == 0) {

    $check->close();
    $conn->close();

    die("Invalid registration or event is not available for payment.");
}

$registration = $result->fetch_assoc();

$check->close();


/* =========================================================
   CHECK PAID EVENT
   ========================================================= */

$event_fee = (float) $registration["fee"];

if ($event_fee <= 0) {

    $conn->close();

    die("This is a free event. Payment is not required.");
}


/* =========================================================
   CHECK DUPLICATE PAYMENT
   ========================================================= */

$payment_check = $conn->prepare(
    "SELECT payment_id
     FROM payments
     WHERE registration_id = ?
     LIMIT 1"
);

$payment_check->bind_param(
    "i",
    $registration_id
);

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
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f3fc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .message-container {
            background: white;
            width: 90%;
            max-width: 500px;
            padding: 45px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #fff3cd;
            color: #d39e00;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        h1 {
            color: #4c2a87;
            margin-bottom: 12px;
            font-size: 28px;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            background: #4c2a87;
            color: white;
        }

    </style>

</head>

<body>

    <div class="message-container">

        <div class="icon">
            !
        </div>

        <h1>Payment Already Submitted</h1>

        <p>
            Payment details for this registration have already been submitted.
            Please wait for verification.
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


/* =========================================================
   CHECK PAYMENT SCREENSHOT
   ========================================================= */

if (
    !isset($_FILES["payment_screenshot"]) ||
    $_FILES["payment_screenshot"]["error"] !== UPLOAD_ERR_OK
) {
    die("Please upload the payment screenshot.");
}


/* =========================================================
   CHECK FILE TYPE
   ========================================================= */

$allowed_types = [
    "image/jpeg",
    "image/png",
    "image/jpg",
    "image/webp"
];

$file_type = mime_content_type(
    $_FILES["payment_screenshot"]["tmp_name"]
);

if (!in_array($file_type, $allowed_types)) {
    die("Only JPG, PNG or WEBP payment screenshots are allowed.");
}


/* =========================================================
   CHECK FILE SIZE
   Maximum 5 MB
   ========================================================= */

$max_size = 5 * 1024 * 1024;

if ($_FILES["payment_screenshot"]["size"] > $max_size) {
    die("Payment screenshot must be less than 5 MB.");
}


/* =========================================================
   CREATE UPLOAD DIRECTORY
   ========================================================= */

$upload_dir = "uploads/payments/";

if (!is_dir($upload_dir)) {

    if (!mkdir($upload_dir, 0777, true)) {
        die("Failed to create payment upload folder.");
    }
}


/* =========================================================
   GENERATE UNIQUE FILE NAME
   ========================================================= */

$extension = strtolower(
    pathinfo(
        $_FILES["payment_screenshot"]["name"],
        PATHINFO_EXTENSION
    )
);

$file_name =
    "payment_" .
    $registration_id .
    "_" .
    time() .
    "." .
    $extension;

$file_path = $upload_dir . $file_name;


/* =========================================================
   MOVE UPLOADED FILE
   ========================================================= */

if (
    !move_uploaded_file(
        $_FILES["payment_screenshot"]["tmp_name"],
        $file_path
    )
) {
    die("Failed to upload payment screenshot.");
}


/* =========================================================
   INSERT PAYMENT
   ========================================================= */

$stmt = $conn->prepare(
    "INSERT INTO payments
    (
        registration_id,
        amount,
        payment_method,
        transaction_id,
        payment_screenshot,
        status
    )
    VALUES (?, ?, 'UPI', ?, ?, 'pending')"
);

$stmt->bind_param(
    "idss",
    $registration_id,
    $event_fee,
    $transaction_id,
    $file_path
);


/* =========================================================
   PAYMENT SUBMISSION SUCCESS
   ========================================================= */

if ($stmt->execute()) {

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment Submitted - Event Hub</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f3fc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-container {
            background: white;
            width: 90%;
            max-width: 500px;
            padding: 45px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #e8f8ee;
            color: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 45px;
        }

        .success-container h1 {
            color: #4c2a87;
            margin-bottom: 12px;
            font-size: 28px;
        }

        .success-container p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .status {
            background: #fff7e6;
            color: #b7791f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .primary-btn {
            background: #4c2a87;
            color: white;
        }

        .secondary-btn {
            background: #eee6ff;
            color: #4c2a87;
        }

    </style>

</head>

<body>

    <div class="success-container">

        <div class="success-icon">
            ✓
        </div>

        <h1>
            Payment Submitted Successfully!
        </h1>

        <p>
            Your payment details and screenshot have been submitted successfully.
        </p>

        <div class="status">
            ⏳ Payment is pending verification.
        </div>

        <div class="buttons">

            <a
                href="my_registration.php"
                class="btn primary-btn"
            >
                My Registrations
            </a>

            <a
                href="student_dashboard.php"
                class="btn secondary-btn"
            >
                Go to Dashboard
            </a>

        </div>

    </div>

</body>

</html>

<?php

} else {

    /*
     * If database insertion fails after file upload,
     * remove the uploaded screenshot.
     */

    if (file_exists($file_path)) {
        unlink($file_path);
    }

    echo "Payment submission failed. Please try again.";
}


$stmt->close();
$conn->close();

?>