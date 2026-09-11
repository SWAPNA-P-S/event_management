<?php

session_start();

include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Check user by email
    $stmt = $conn->prepare(
        "SELECT id, name, email, password, role
         FROM users
         WHERE email = ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        // Check password
        if (password_verify($password, $user["password"])) {

            // Store user details in session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["role"] = $user["role"];


            /*
             * ==================================================
             * ADMIN
             * ==================================================
             */

            if ($user["role"] === "admin") {

                header("Location: admin/admin_dashboard.php");
                exit();
            }


            /*
             * ==================================================
             * EXISTING ORGANIZER
             * ==================================================
             */

            elseif ($user["role"] === "organizer") {

                header("Location: organizer/organizer_dashboard.php");
                exit();
            }


            /*
             * ==================================================
             * STUDENT
             *
             * A student can also become an approved organizer.
             * We check organizer_team_members + organizer_requests.
             * ==================================================
             */

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

                $organizer_check->bind_param("i", $user_id);
                $organizer_check->execute();

                $organizer_result = $organizer_check->get_result();

                /*
                 * Approved organizer team member
                 */
                if ($organizer_result->num_rows > 0) {

                    $_SESSION["is_organizer"] = true;

                    header("Location: organizer/organizer_dashboard.php");
                    exit();

                }

                /*
                 * Normal student
                 */
                else {

                    $_SESSION["is_organizer"] = false;

                    header("Location: student_dashboard.php");
                    exit();
                }

                $organizer_check->close();
            }


            else {

                echo "Invalid user role.";
            }

        } else {

            echo "<script>
                    alert('Incorrect password!');
                    window.location.href='login.html';
                  </script>";
        }

    } else {

        echo "<script>
                alert('Email not found!');
                window.location.href='login.html';
              </script>";
    }

    $stmt->close();
}

$conn->close();

?>