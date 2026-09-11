<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        id,
        name,
        email,
        phone,
        college,
        department,
        student_id
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Profile not found.");
}

$user = $result->fetch_assoc();

$stmt->close();
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile - Event Hub</title>

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
            max-width: 800px;
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

        .profile-card {
            background: white;
            border-radius: 14px;
            padding: 35px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .profile-header {
            text-align: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 25px;
            margin-bottom: 25px;
        }

        .profile-icon {
            width: 80px;
            height: 80px;
            margin: auto;
            border-radius: 50%;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .profile-header h2 {
            margin-top: 15px;
            color: #1e3a8a;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .info-box {
            background: #f8f9ff;
            padding: 17px;
            border-radius: 9px;
        }

        .info-box span {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .info-box strong {
            color: #333;
            word-break: break-word;
        }

        .full-width {
            grid-column: 1 / -1;
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

            .profile-card {
                padding: 25px 20px;
            }

            .profile-info {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
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

        <h1>My Profile</h1>

        <p class="subtitle">
            View your registered student information.
        </p>


        <div class="profile-card">


            <div class="profile-header">

                <div class="profile-icon">
                    👤
                </div>

                <h2>
                    <?php
                    echo htmlspecialchars($user["name"]);
                    ?>
                </h2>

            </div>


            <div class="profile-info">


                <div class="info-box">

                    <span>📧 Email</span>

                    <strong>
                        <?php
                        echo htmlspecialchars($user["email"]);
                        ?>
                    </strong>

                </div>


                <div class="info-box">

                    <span>📱 Phone</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $user["phone"] ?: "Not provided"
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-box">

                    <span>🎓 Student ID</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $user["student_id"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-box">

                    <span>🏫 College</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $user["college"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-box full-width">

                    <span>📚 Department</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $user["department"]
                        );
                        ?>
                    </strong>

                </div>


            </div>

        </div>

    </div>

</main>

</body>

</html>