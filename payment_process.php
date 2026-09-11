<?php

include "auth_student.php";
include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$registration_id = $_POST["registration_id"];
$transaction_id = $_POST["transaction_id"];

// Check registration belongs to logged-in student
$check = $conn->prepare(
    "SELECT r.registration_id, e.fee
     FROM registrations r
     JOIN events e ON r.event_id = e.event_id
     WHERE r.registration_id = ?
     AND r.user_id = ?"
);

$check->bind_param(
    "ii",
    $registration_id,
    $_SESSION["user_id"]
);

$check->execute();

$result = $check->get_result();

if ($result->num_rows == 0) {
    die("Invalid registration.");
}

$registration = $result->fetch_assoc();

$check->close();


// Check screenshot
if (!isset($_FILES["payment_screenshot"]) ||
    $_FILES["payment_screenshot"]["error"] !== 0) {

    die("Please upload the payment screenshot.");
}


// Create uploads folder if it does not exist
$upload_dir = "uploads/payments/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}


// Generate unique file name
$file_name = time() . "_" . basename($_FILES["payment_screenshot"]["name"]);

$file_path = $upload_dir . $file_name;


// Move uploaded file
if (!move_uploaded_file(
    $_FILES["payment_screenshot"]["tmp_name"],
    $file_path
)) {
    die("Failed to upload screenshot.");
}


// Insert payment
$stmt = $conn->prepare(
    "INSERT INTO payments
    (registration_id, amount, payment_method, transaction_id,
     payment_screenshot, status)
    VALUES (?, ?, 'UPI', ?, ?, 'pending')"
);

$stmt->bind_param(
    "idss",
    $registration_id,
    $registration["fee"],
    $transaction_id,
    $file_path
);


if ($stmt->execute()) {

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment Successful - Event Hub</title>

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

        <h1>Payment Submitted Successfully!</h1>

        <p>
            Your payment details and screenshot have been submitted successfully.
        </p>

        <div class="status">
            ⏳ Payment is pending verification.
        </div>

        <div class="buttons">

            <a href="student_dashboard.php" class="btn primary-btn">
                Go to Dashboard
            </a>

            <a href="events.php" class="btn secondary-btn">
                Back to Events
            </a>

        </div>

    </div>

</body>

</html>

<?php

} else {

    echo "Payment submission failed. Please try again.";

}


$stmt->close();
$conn->close();

?>