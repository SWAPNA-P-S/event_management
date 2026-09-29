<?php

session_start();

include "db.php";


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    /* ==========================================
       GET LOGIN DATA
    ========================================== */

    $email = trim($_POST["email"]);
    $password = $_POST["password"];


    /* ==========================================
       FIND USER
    ========================================== */

    $stmt = $conn->prepare(
        "SELECT
            id,
            name,
            email,
            password,
            role
         FROM users
         WHERE email = ?"
    );

    $stmt->bind_param(
        "s",
        $email
    );

    $stmt->execute();

    $result = $stmt->get_result();


    /* ==========================================
       CHECK USER
    ========================================== */

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();


        /* ======================================
           PASSWORD VERIFY
        ====================================== */

        if (password_verify($password, $user["password"])) {


            /* ==================================
               CREATE SESSION
            ================================== */

            $_SESSION["user_id"] = $user["id"];

            $_SESSION["user_name"] = $user["name"];

            $_SESSION["user_email"] = $user["email"];

            $_SESSION["role"] = $user["role"];


            /* ==================================
               CORE ADMIN
            ================================== */

            if ($user["role"] === "core_admin") {

                header(
                    "Location: core_admin/core_admin_dashboard.php"
                );

                exit();

            }


            /* ==================================
               DEPARTMENT ADMIN / HOD
            ================================== */

            elseif ($user["role"] === "admin") {

                header(
                    "Location: admin/admin_dashboard.php"
                );

                exit();

            }


            /* ==================================
               ORGANIZER
            ================================== */

            elseif ($user["role"] === "organizer") {

                $_SESSION["is_organizer"] = true;

                header(
                    "Location: organizer/organizer_dashboard.php"
                );

                exit();

            }


            /* ==================================
               STAGE MANAGER
            ================================== */

            elseif ($user["role"] === "stage_manager") {

                $_SESSION["is_stage_manager"] = true;

                header(
                    "Location: stage_manager/dashboard.php"
                );

                exit();

            }


            /* ==================================
               STUDENT
               CHECK WHETHER STUDENT IS ALSO
               AN APPROVED ORGANIZER
            ================================== */

            elseif ($user["role"] === "student") {

                $user_id = $user["id"];


                $organizer_check = $conn->prepare(
                    "SELECT tm.team_member_id
                     FROM organizer_team_members tm
                     INNER JOIN organizer_requests r
                         ON tm.request_id = r.request_id
                     WHERE tm.user_id = ?
                       AND r.status = 'approved'
                     LIMIT 1"
                );

                $organizer_check->bind_param(
                    "i",
                    $user_id
                );

                $organizer_check->execute();

                $organizer_result =
                    $organizer_check->get_result();


                if ($organizer_result->num_rows > 0) {

                    $_SESSION["is_organizer"] = true;

                    header(
                        "Location: organizer/organizer_dashboard.php"
                    );

                    exit();

                } else {

                    $_SESSION["is_organizer"] = false;

                    header(
                        "Location: student_dashboard.php"
                    );

                    exit();

                }

                $organizer_check->close();

            }


            /* ==================================
               INVALID ROLE
            ================================== */

            else {

                echo "Invalid user role.";

            }

        }


        /* ======================================
           WRONG PASSWORD
        ====================================== */

        else {

            echo "
                <script>
                    alert('Incorrect password!');
                    window.location.href = 'login.html';
                </script>
            ";

        }

    }


    /* ==========================================
       EMAIL NOT FOUND
    ========================================== */

    else {

        echo "
            <script>
                alert('Email not found!');
                window.location.href = 'login.html';
            </script>
        ";

    }


    $stmt->close();

}


$conn->close();

?>