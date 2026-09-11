<?php

include "db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);

    // Check if email exists
    $stmt = $conn->prepare(
        "SELECT id, name FROM users WHERE email = ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        $user_id = $user["id"];
        $user_name = $user["name"];

        // Generate secure random token
        $token = bin2hex(random_bytes(32));

        // Token valid for 30 minutes
        $expires_at = date(
            "Y-m-d H:i:s",
            time() + (30 * 60)
        );

        // Delete old reset tokens for this user
        $delete = $conn->prepare(
            "DELETE FROM password_resets WHERE user_id = ?"
        );

        $delete->bind_param("i", $user_id);
        $delete->execute();
        $delete->close();

        // Store new reset token
        $reset = $conn->prepare(
            "INSERT INTO password_resets
            (user_id, token, expires_at)
            VALUES (?, ?, ?)"
        );

        $reset->bind_param(
            "iss",
            $user_id,
            $token,
            $expires_at
        );

        if ($reset->execute()) {

            /*
             * Reset link
             *
             * Since we are using XAMPP locally:
             */
            $reset_link =
                "http://localhost:8080/event_management/reset_password.php?token="
                . $token;

            /*
             * Temporary display for testing.
             *
             * Later this link will be sent through email.
             */
            $message =
                "Reset link generated successfully!<br><br>"
                . "<a href='" . htmlspecialchars($reset_link) . "'>"
                . "Click here to reset your password"
                . "</a>";

            $message_type = "success";

        } else {

            $message = "Unable to create reset link.";
            $message_type = "error";
        }

        $reset->close();

    } else {

        $message = "No account found with this email address.";
        $message_type = "error";
    }

    $stmt->close();
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Forgot Password - Event Hub</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f4f7fb;
        }

        .box {
            width: 420px;
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        h1 {
            text-align: center;
            color: #172554;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            color: #334155;
            margin-bottom: 7px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
        }

        .back:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

    <div class="box">

        <h1>Forgot Password?</h1>

        <p class="subtitle">
            Enter your registered email address
        </p>

        <?php if ($message != ""): ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo $message; ?>

            </div>

        <?php endif; ?>


        <form method="POST"
              action="forgot_password.php">

            <label for="email">
                Email Address
            </label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your registered email"
                required
            >

            <button type="submit">
                Send Reset Link
            </button>

        </form>


        <a href="login.html" class="back">
            ← Back to Login
        </a>

    </div>

</body>

</html>