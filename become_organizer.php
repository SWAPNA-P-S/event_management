<?php

session_start();
require_once "db.php";


// Only logged-in students can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.html");
    exit();
}

$student_id = $_SESSION["user_id"];

$message = "";
$message_type = "";


// --------------------------------------------------
// Check existing pending organizer request
// --------------------------------------------------

$check = $conn->prepare(
    "SELECT request_id, team_name, status
     FROM organizer_requests
     WHERE requested_by = ?
     AND status = 'pending'
     LIMIT 1"
);

$check->bind_param("i", $student_id);
$check->execute();

$existing_request = $check->get_result()->fetch_assoc();

$check->close();


// --------------------------------------------------
// Get other students
// --------------------------------------------------

$students = [];

$stmt_students = $conn->prepare(
    "SELECT id, name, email, student_id
     FROM users
     WHERE role = 'student'
     AND id != ?
     ORDER BY name"
);

$stmt_students->bind_param("i", $student_id);
$stmt_students->execute();

$result_students = $stmt_students->get_result();

while ($student = $result_students->fetch_assoc()) {
    $students[] = $student;
}

$stmt_students->close();


// --------------------------------------------------
// Submit Organizer Request
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$existing_request) {

    $team_name = trim($_POST["team_name"] ?? "");
    $reason = trim($_POST["reason"] ?? "");

    $member1 = intval($_POST["member1"] ?? 0);
    $member2 = intval($_POST["member2"] ?? 0);


    // Basic validation

    if ($team_name === "") {

        $message = "Please enter a team name.";
        $message_type = "error";

    } elseif ($member1 <= 0 || $member2 <= 0) {

        $message = "Please select two other students.";
        $message_type = "error";

    } elseif ($member1 === $member2) {

        $message = "Please select two different students.";
        $message_type = "error";

    } elseif ($member1 === $student_id || $member2 === $student_id) {

        $message = "You cannot select yourself as another team member.";
        $message_type = "error";

    } else {


        // --------------------------------------------------
        // Create organizer request
        // --------------------------------------------------

        $stmt = $conn->prepare(
            "INSERT INTO organizer_requests
            (requested_by, team_name, reason, status)
            VALUES (?, ?, ?, 'pending')"
        );

        $stmt->bind_param(
            "iss",
            $student_id,
            $team_name,
            $reason
        );


        if ($stmt->execute()) {

            $request_id = $stmt->insert_id;

            $stmt->close();


            // --------------------------------------------------
            // Add logged-in student as Team Leader
            // --------------------------------------------------

            $leader = $conn->prepare(
                "INSERT INTO organizer_team_members
                (request_id, user_id, member_role)
                VALUES (?, ?, 'leader')"
            );

            $leader->bind_param(
                "ii",
                $request_id,
                $student_id
            );

            $leader->execute();

            $leader->close();


            // --------------------------------------------------
            // Add Member 2
            // --------------------------------------------------

            $member = $conn->prepare(
                "INSERT INTO organizer_team_members
                (request_id, user_id, member_role)
                VALUES (?, ?, 'member')"
            );

            $member->bind_param(
                "ii",
                $request_id,
                $member1
            );

            $member->execute();


            // --------------------------------------------------
            // Add Member 3
            // --------------------------------------------------

            // IMPORTANT:
            // request_id + member2 = TWO values
            $member->bind_param(
                "ii",
                $request_id,
                $member2
            );

            $member->execute();

            $member->close();


            // --------------------------------------------------
            // Update page state
            // --------------------------------------------------

            $existing_request = [
                "request_id" => $request_id,
                "team_name" => $team_name,
                "status" => "pending"
            ];

            $message = "Organizer request submitted successfully!";
            $message_type = "success";


        } else {

            $message = "Something went wrong. Please try again.";
            $message_type = "error";

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Become an Organizer</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f0ff;
            color: #222;
        }

        .container {
            width: 90%;
            max-width: 700px;
            margin: 40px auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h1 {
            color: #5b3ca8;
            margin-top: 0;
        }

        .description {
            color: #666;
            line-height: 1.6;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
        }

        textarea {
            height: 100px;
            resize: vertical;
        }

        button {
            margin-top: 25px;
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #5b3ca8;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #472d8b;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dff5e5;
            color: #176b32;
        }

        .error {
            background: #ffe1e1;
            color: #a00000;
        }

        .pending {
            background: #fff4d6;
            padding: 20px;
            border-radius: 10px;
            line-height: 1.7;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #5b3ca8;
            font-weight: bold;
        }

    </style>

</head>


<body>

<div class="container">

    <div class="card">

        <h1>Become an Organizer</h1>

        <p class="description">
            Form a three-member student team and request
            Organizer access. Your request will be reviewed
            by the Admin.
        </p>


        <?php if ($message !== ""): ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <?php if ($existing_request): ?>

            <div class="pending">

                <strong>Organizer Request Status</strong>

                <p>
                    Your organizer team request has been submitted.
                </p>

                <p>
                    <strong>Team Name:</strong>
                    <?php echo htmlspecialchars($existing_request["team_name"]); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    Pending Admin Approval
                </p>

            </div>


        <?php elseif (count($students) < 2): ?>

            <div class="message error">

                At least two other student accounts are required
                to create a three-member organizing team.

            </div>


        <?php else: ?>


            <form method="POST">

                <label for="team_name">
                    Team Name
                </label>

                <input
                    type="text"
                    id="team_name"
                    name="team_name"
                    placeholder="Enter organizing team name"
                    required
                >


                <label for="member1">
                    Team Member 2
                </label>

                <select
                    id="member1"
                    name="member1"
                    required
                >

                    <option value="">
                        -- Select Student --
                    </option>

                    <?php foreach ($students as $student): ?>

                        <option value="<?php echo $student["id"]; ?>">

                            <?php
                            echo htmlspecialchars(
                                $student["name"] .
                                " (" .
                                $student["student_id"] .
                                ")"
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <label for="member2">
                    Team Member 3
                </label>

                <select
                    id="member2"
                    name="member2"
                    required
                >

                    <option value="">
                        -- Select Student --
                    </option>

                    <?php foreach ($students as $student): ?>

                        <option value="<?php echo $student["id"]; ?>">

                            <?php
                            echo htmlspecialchars(
                                $student["name"] .
                                " (" .
                                $student["student_id"] .
                                ")"
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <label for="reason">
                    Reason / Purpose
                </label>

                <textarea
                    id="reason"
                    name="reason"
                    placeholder="Why do you want to organize events?"
                ></textarea>


                <button type="submit">
                    Submit Organizer Request
                </button>

            </form>

        <?php endif; ?>


        <a
            href="student_dashboard.php"
            class="back"
        >
            ← Back to Student Dashboard
        </a>

    </div>

</div>

</body>

</html>

<?php

$conn->close();

?>