<?php

include "auth_student.php";
include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$user_id = $_SESSION["user_id"];

$registration_id = $_POST["registration_id"] ?? "";
$rating = $_POST["rating"] ?? "";
$experience = $_POST["experience"] ?? "";
$comments = $_POST["comments"] ?? "";
$suggestions = $_POST["suggestions"] ?? "";


/* Basic validation */

if (empty($registration_id)) {
    die("Registration not found.");
}

if (empty($rating) || empty($experience)) {
    die("Please complete the required feedback fields.");
}


/* Check registration + verified attendance */

$stmt = $conn->prepare(
    "SELECT
        r.registration_id,
        e.event_name,
        a.verification_status
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     JOIN attendance a
     ON r.registration_id = a.registration_id
     WHERE r.registration_id = ?
     AND r.user_id = ?
     AND a.verification_status = 'verified'"
);

$stmt->bind_param(
    "ii",
    $registration_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Feedback is available only after verified attendance.");
}

$event = $result->fetch_assoc();

$stmt->close();


/* Check whether feedback already exists */

$check = $conn->prepare(
    "SELECT feedback_id
     FROM feedback
     WHERE registration_id = ?"
);

$check->bind_param(
    "i",
    $registration_id
);

$check->execute();

$check_result = $check->get_result();

if ($check_result->num_rows > 0) {
    die("Feedback has already been submitted for this event.");
}

$check->close();


/* Insert feedback */

$insert = $conn->prepare(
    "INSERT INTO feedback
    (
        registration_id,
        rating,
        experience,
        comments,
        suggestions
    )
    VALUES (?, ?, ?, ?, ?)"
);

$insert->bind_param(
    "iisss",
    $registration_id,
    $rating,
    $experience,
    $comments,
    $suggestions
);


if ($insert->execute()) {

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Feedback Submitted - Event Hub</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .success-card {
            background: white;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .success-icon {
            font-size: 55px;
            margin-bottom: 15px;
        }

        h1 {
            color: #166534;
            margin-bottom: 12px;
        }

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            margin: 5px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .dashboard-btn {
            background: #263a8b;
            color: white;
        }

        .feedback-btn {
            background: #eef2ff;
            color: #263a8b;
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

    <div class="success-card">

        <div class="success-icon">
            🎉
        </div>

        <h1>
            Feedback Submitted Successfully!
        </h1>

        <p>
            Thank you for sharing your experience about
            <strong>
                <?php echo htmlspecialchars($event["event_name"]); ?>
            </strong>.
        </p>

        <a
            href="feedback.php"
            class="btn feedback-btn"
        >
            Back to Feedback
        </a>

        <a
            href="student_dashboard.php"
            class="btn dashboard-btn"
        >
            Back to Dashboard
        </a>

    </div>

</main>

</body>

</html>

<?php

} else {

    die("Failed to submit feedback.");

}

$insert->close();
$conn->close();

?>