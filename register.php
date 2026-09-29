<?php

include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    /* ==========================================
       GET FORM DATA
    ========================================== */

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $college = trim($_POST["college"]);
    $department = trim($_POST["department"]);

    // Form field is Registration Number
    // It will be stored in the existing student_id column
    $registration_number = trim($_POST["registration_number"]);

    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Get selected role
    $role = isset($_POST["role"])
        ? trim($_POST["role"])
        : "";


    /* ==========================================
       ALLOWED REGISTRATION ROLES
    ========================================== */

    $allowed_roles = [
        "student",
        "organizer",
        "stage_manager",
        "admin",
        "core_admin"
    ];

    if (!in_array($role, $allowed_roles, true)) {

        die("Invalid registration role.");

    }


    /* ==========================================
       BASIC VALIDATION
    ========================================== */

    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($college) ||
        empty($department) ||
        empty($registration_number) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        die("Please fill in all required fields.");

    }


    /* ==========================================
       PASSWORD CHECK
    ========================================== */

    if ($password !== $confirm_password) {

        die("Passwords do not match.");

    }


    /* ==========================================
       EMAIL CHECK
    ========================================== */

    $check_email = $conn->prepare(
        "SELECT id
         FROM users
         WHERE email = ?"
    );

    $check_email->bind_param(
        "s",
        $email
    );

    $check_email->execute();

    $check_email->store_result();

    if ($check_email->num_rows > 0) {

        die("Email already registered.");

    }

    $check_email->close();


    /* ==========================================
       REGISTRATION NUMBER CHECK
       Stored in existing student_id column
    ========================================== */

    $check_registration = $conn->prepare(
        "SELECT id
         FROM users
         WHERE student_id = ?"
    );

    $check_registration->bind_param(
        "s",
        $registration_number
    );

    $check_registration->execute();

    $check_registration->store_result();

    if ($check_registration->num_rows > 0) {

        die("Registration number already registered.");

    }

    $check_registration->close();


    /* ==========================================
       PASSWORD HASH
    ========================================== */

    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    /* ==========================================
       INSERT USER
    ========================================== */

    $stmt = $conn->prepare(
        "INSERT INTO users
        (
            name,
            email,
            phone,
            college,
            department,
            student_id,
            password,
            role
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssssssss",
        $name,
        $email,
        $phone,
        $college,
        $department,
        $registration_number,
        $hashed_password,
        $role
    );


    /* ==========================================
       SUCCESS
    ========================================== */

    if ($stmt->execute()) {

        ?>

        <!DOCTYPE html>

        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

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

                    box-shadow:
                        0 8px 25px
                        rgba(0, 0, 0, 0.10);

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


                .user-name {

                    font-weight: bold;

                    color: #4f3295;

                }


                .role-name {

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


                <h1>
                    Registration Successful!
                </h1>


                <p>

                    Welcome,

                    <span class="user-name">

                        <?php

                        echo htmlspecialchars($name);

                        ?>

                    </span>

                    !

                </p>


                <p>

                    Your

                    <span class="role-name">

                        <?php

                        if ($role === "stage_manager") {

                            echo "Stage Manager";

                        } elseif ($role === "organizer") {

                            echo "Organizer";

                        } elseif ($role === "admin") {

                            echo "Department Admin / HOD";

                        } elseif ($role === "core_admin") {

                            echo "Core Admin";

                        } else {

                            echo "Student";

                        }

                        ?>

                    </span>

                    account has been created successfully.

                    You can now login using your
                    registered email and password.

                </p>


                <a
                    href="login.html"
                    class="login-btn"
                >

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