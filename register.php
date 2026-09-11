<?php

include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $college = trim($_POST["college"]);
    $department = trim($_POST["department"]);
    $student_id = trim($_POST["student_id"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Check whether passwords match
    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Check whether email already exists
    $check_email = $conn->prepare(
        "SELECT id FROM users WHERE email = ?"
    );

    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();

    if ($check_email->num_rows > 0) {
        die("Email already registered.");
    }

    $check_email->close();


    // Check whether student ID already exists
    $check_student = $conn->prepare(
        "SELECT id FROM users WHERE student_id = ?"
    );

    $check_student->bind_param("s", $student_id);
    $check_student->execute();
    $check_student->store_result();

    if ($check_student->num_rows > 0) {
        die("Student ID already registered.");
    }

    $check_student->close();


    // Hash password securely
    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    // Insert student into database
    $stmt = $conn->prepare(
        "INSERT INTO users
        (name, email, phone, college, department, student_id, password, role)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'student')"
    );

    $stmt->bind_param(
        "sssssss",
        $name,
        $email,
        $phone,
        $college,
        $department,
        $student_id,
        $hashed_password
    );


    if ($stmt->execute()) {

        ?>

        <!DOCTYPE html>
        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta name="viewport"
                  content="width=device-width, initial-scale=1.0">

            <title>Registration Successful</title>

            <style>

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background: #f3efff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }

                .success-card {
                    width: 90%;
                    max-width: 500px;
                    background: white;
                    padding: 40px;
                    border-radius: 18px;
                    text-align: center;
                    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.10);
                }

                .success-icon {
                    width: 70px;
                    height: 70px;
                    margin: 0 auto 20px;
                    border-radius: 50%;
                    background: #e1f7e8;
                    color: #218838;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 38px;
                }

                h1 {
                    color: #4f3295;
                    margin-bottom: 10px;
                }

                p {
                    color: #666;
                    line-height: 1.6;
                }

                .student-name {
                    font-weight: bold;
                    color: #4f3295;
                }

                .login-btn {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 13px 28px;
                    background: #5b3ca8;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: bold;
                }

                .login-btn:hover {
                    background: #472d8b;
                }

            </style>

        </head>

        <body>

            <div class="success-card">

                <div class="success-icon">
                    ✓
                </div>

                <h1>Registration Successful!</h1>

                <p>
                    Welcome,
                    <span class="student-name">
                        <?php echo htmlspecialchars($name); ?>
                    </span>
                    !
                </p>

                <p>
                    Your student account has been created successfully.
                    You can now login using your registered email and password.
                </p>

                <a href="login.html" class="login-btn">
                    Go to Login
                </a>

            </div>

        </body>

        </html>

        <?php

    } else {

        echo "Registration failed. Please try again.";

    }

    $stmt->close();
}

$conn->close();

?>