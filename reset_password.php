<?php

include "db.php";

$message = "";
$message_type = "";

$token = isset($_GET["token"]) ? $_GET["token"] : "";

if ($token == "") {
    $message = "Invalid or missing reset link.";
    $message_type = "error";
}

// Check whether token is valid
if ($token != "") {

    $stmt = $conn->prepare(
        "SELECT id, user_id, expires_at
         FROM password_resets
         WHERE token = ?"
    );

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $reset = $result->fetch_assoc();

        // Check expiry
        if (strtotime($reset["expires_at"]) < time()) {

            $message = "This reset link has expired.";
            $message_type = "error";

        }

    } else {

        $message = "Invalid reset link.";
        $message_type = "error";
    }

    $stmt->close();
}


// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token != "") {

    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    // Check token again
    $stmt = $conn->prepare(
        "SELECT id, user_id, expires_at
         FROM password_resets
         WHERE token = ?"
    );

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $reset = $result->fetch_assoc();

        if (strtotime($reset["expires_at"]) < time()) {

            $message = "This reset link has expired.";
            $message_type = "error";

        } elseif ($new_password !== $confirm_password) {

            $message = "Passwords do not match.";
            $message_type = "error";

        } elseif (strlen($new_password) < 6) {

            $message = "Password must contain at least 6 characters.";
            $message_type = "error";

        } else {

            // Create secure password hash
            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            // Update user's password
            $update = $conn->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "si",
                $hashed_password,
                $reset["user_id"]
            );

            if ($update->execute()) {

                // Delete token after successful reset
                $delete = $conn->prepare(
                    "DELETE FROM password_resets
                     WHERE id = ?"
                );

                $delete->bind_param(
                    "i",
                    $reset["id"]
                );

                $delete->execute();
                $delete->close();

                $message =
                    "Password reset successfully!";

                $message_type = "success";

            } else {

                $message =
                    "Unable to reset password. Please try again.";

                $message_type = "error";
            }

            $update->close();
        }

    } else {

        $message = "Invalid reset link.";
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

    <title>Reset Password - Event Hub</title>

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

        <h1>Reset Password</h1>

        <p class="subtitle">
            Create a new password for your account
        </p>

        <?php if ($message != ""): ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <?php if (
            $token != "" &&
            $message != "This reset link has expired." &&
            $message != "Invalid reset link." &&
            $message != "Invalid or missing reset link."
        ): ?>

            <form method="POST"
                  action="reset_password.php?token=<?php echo urlencode($token); ?>">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    placeholder="Enter new password"
                    required
                >


                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm new password"
                    required
                >


                <button type="submit">
                    Reset Password
                </button>

            </form>

        <?php endif; ?>


        <a href="login.html" class="back">
            ← Back to Login
        </a>

    </div>

</body>

</html>