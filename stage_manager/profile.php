<?php

session_start();

require_once "../db.php";

/* =========================
   LOGIN CHECK
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit;
}


/* =========================
   ROLE CHECK
========================= */

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "stage_manager"
) {
    header("Location: ../login.html");
    exit;
}


$user_id = (int) $_SESSION["user_id"];

$message = "";
$message_type = "";


/* =========================
   UPDATE PROFILE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $college = trim($_POST["college"] ?? "");
    $department = trim($_POST["department"] ?? "");


    if ($name === "") {

        $message = "Name cannot be empty.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET
                name = ?,
                phone = ?,
                college = ?,
                department = ?
            WHERE id = ?
              AND role = 'stage_manager'
        ");

        $stmt->bind_param(
            "ssssi",
            $name,
            $phone,
            $college,
            $department,
            $user_id
        );


        if ($stmt->execute()) {

            $_SESSION["name"] = $name;

            $message = "Profile updated successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to update profile.";
            $message_type = "error";
        }


        $stmt->close();
    }
}


/* =========================
   FETCH USER DETAILS
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        college,
        department,
        student_id,
        role
    FROM users
    WHERE id = ?
      AND role = 'stage_manager'
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {

    session_destroy();

    header("Location: ../login.html");
    exit;
}


/* =========================
   DISPLAY VALUES
========================= */

$name = $user["name"] ?? "";
$email = $user["email"] ?? "";
$phone = $user["phone"] ?? "";
$college = $user["college"] ?? "";
$department = $user["department"] ?? "";
$student_id = $user["student_id"] ?? "";
$role = $user["role"] ?? "stage_manager";


/* =========================
   ROLE DISPLAY
========================= */

$role_display = "Stage Manager";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Stage Manager Profile</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #172033;

        }


        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;

            width: 245px;

            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #102a68 0%,
                    #173f91 100%
                );

            color: white;

            padding: 28px 18px;

            box-shadow:
                4px 0 18px rgba(
                    0,
                    0,
                    0,
                    0.08
                );

            z-index: 1000;

        }


        .logo-area {

            text-align: center;

            padding-bottom: 24px;

            margin-bottom: 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,0.15);

        }


        .logo-circle {

            width: 58px;
            height: 58px;

            margin: 0 auto 10px;

            border-radius: 50%;

            display: flex;

            align-items: center;
            justify-content: center;

            background: white;

            color: #173f91;

            font-size: 27px;

            font-weight: bold;

        }


        .logo-area h2 {

            margin: 0;

            font-size: 19px;

        }


        .logo-area p {

            margin: 6px 0 0;

            font-size: 12px;

            color:
                rgba(255,255,255,0.72);

        }


        .menu {

            display: flex;

            flex-direction: column;

            gap: 7px;

        }


        .menu a {

            text-decoration: none;

            color:
                rgba(255,255,255,0.86);

            padding: 13px 15px;

            border-radius: 10px;

            font-size: 14px;

            transition: 0.2s;

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .menu a:hover {

            background:
                rgba(255,255,255,0.10);

            color: white;

        }


        .menu a.active {

            background: white;

            color: #173f91;

            font-weight: 600;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,0.10);

        }


        .logout {

            margin-top: 20px;

            border-top:
                1px solid
                rgba(255,255,255,0.15);

            padding-top: 20px;

        }


        .logout a {

            color: #ffd7d7 !important;

        }


        /* =========================
           MAIN
        ========================= */

        .main {

            margin-left: 245px;

            min-height: 100vh;

            padding: 28px 34px 50px;

        }


        /* =========================
           TOP BAR
        ========================= */

        .topbar {

            display: flex;

            align-items: center;

            justify-content: space-between;

            background: white;

            border-radius: 16px;

            padding: 20px 25px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 22px
                rgba(20,40,80,0.06);

        }


        .topbar h1 {

            margin: 0;

            color: #102a68;

            font-size: 27px;

        }


        .topbar p {

            margin: 6px 0 0;

            color: #6b7280;

            font-size: 14px;

        }


        .user-badge {

            display: flex;

            align-items: center;

            gap: 11px;

        }


        .avatar-small {

            width: 43px;
            height: 43px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #173f91
                );

            color: white;

            display: flex;

            align-items: center;
            justify-content: center;

            font-weight: bold;

            font-size: 17px;

        }


        .user-info strong {

            display: block;

            font-size: 14px;

            color: #172033;

        }


        .user-info span {

            display: block;

            margin-top: 3px;

            font-size: 12px;

            color: #6b7280;

        }


        /* =========================
           PROFILE CARD
        ========================= */

        .profile-wrapper {

            max-width: 1050px;

            margin: 0 auto;

        }


        .profile-card {

            background: white;

            border-radius: 20px;

            overflow: hidden;

            box-shadow:
                0 8px 30px
                rgba(20,40,80,0.07);

        }


        /* =========================
           PROFILE HEADER
        ========================= */

        .profile-header {

            padding: 32px;

            background:
                linear-gradient(
                    135deg,
                    #102a68,
                    #2563eb
                );

            color: white;

            display: flex;

            align-items: center;

            gap: 22px;

        }


        .profile-avatar {

            width: 88px;
            height: 88px;

            border-radius: 50%;

            background: white;

            color: #173f91;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 32px;

            font-weight: bold;

            box-shadow:
                0 7px 18px
                rgba(0,0,0,0.16);

        }


        .profile-header h2 {

            margin: 0;

            font-size: 25px;

        }


        .profile-header p {

            margin: 7px 0 0;

            color:
                rgba(255,255,255,0.80);

            font-size: 14px;

        }


        .role-badge {

            display: inline-block;

            margin-top: 10px;

            padding: 6px 12px;

            border-radius: 20px;

            background:
                rgba(255,255,255,0.16);

            border:
                1px solid
                rgba(255,255,255,0.22);

            font-size: 12px;

        }


        /* =========================
           MESSAGE
        ========================= */

        .message {

            margin: 25px 32px 0;

            padding: 13px 16px;

            border-radius: 10px;

            font-size: 14px;

        }


        .message.success {

            background: #ecfdf3;

            color: #166534;

            border:
                1px solid #bbf7d0;

        }


        .message.error {

            background: #fef2f2;

            color: #991b1b;

            border:
                1px solid #fecaca;

        }


        /* =========================
           PROFILE BODY
        ========================= */

        .profile-body {

            padding: 32px;

        }


        .section-title {

            margin: 0 0 20px;

            color: #102a68;

            font-size: 19px;

        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;

        }


        .form-group {

            display: flex;

            flex-direction: column;

        }


        .form-group.full {

            grid-column: 1 / -1;

        }


        .form-group label {

            margin-bottom: 8px;

            color: #374151;

            font-size: 13px;

            font-weight: 600;

        }


        .form-group input {

            width: 100%;

            padding: 13px 14px;

            border: 1px solid #dbe2ea;

            border-radius: 10px;

            background: white;

            color: #172033;

            font-size: 14px;

            outline: none;

            transition: 0.2s;

        }


        .form-group input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,0.10);

        }


        .form-group input[readonly] {

            background: #f5f7fa;

            color: #6b7280;

            cursor: not-allowed;

        }


        .field-note {

            margin-top: 6px;

            font-size: 11px;

            color: #8a94a6;

        }


        /* =========================
           ACTIONS
        ========================= */

        .profile-actions {

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            margin-top: 28px;

            padding-top: 22px;

            border-top:
                1px solid #edf0f4;

        }


        .btn {

            border: none;

            border-radius: 10px;

            padding: 12px 22px;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

            transition: 0.2s;

        }


        .btn-primary {

            background: #2563eb;

            color: white;

        }


        .btn-primary:hover {

            background: #1d4ed8;

            transform: translateY(-1px);

        }


        .btn-secondary {

            background: #eef2f7;

            color: #374151;

        }


        .btn-secondary:hover {

            background: #e2e8f0;

        }


        /* =========================
           ACCOUNT INFO
        ========================= */

        .account-info {

            margin-top: 25px;

            padding: 22px;

            border-radius: 14px;

            background: #f8fafc;

            border:
                1px solid #e8edf3;

        }


        .account-info h3 {

            margin: 0 0 15px;

            color: #102a68;

            font-size: 16px;

        }


        .account-grid {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;

        }


        .account-item {

            background: white;

            border-radius: 10px;

            padding: 14px;

            border:
                1px solid #edf0f4;

        }


        .account-item span {

            display: block;

            font-size: 11px;

            color: #8a94a6;

            margin-bottom: 5px;

            text-transform: uppercase;

            letter-spacing: 0.3px;

        }


        .account-item strong {

            font-size: 13px;

            color: #253047;

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .sidebar {

                width: 210px;

            }

            .main {

                margin-left: 210px;

                padding: 22px;

            }

            .account-grid {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 700px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;

            }

            .main {

                margin-left: 0;

                padding: 15px;

            }

            .topbar {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }

            .form-grid {

                grid-template-columns: 1fr;

            }

            .form-group.full {

                grid-column: auto;

            }

            .profile-header {

                flex-direction: column;

                text-align: center;

            }

        }

    </style>

</head>


<body>


    <!-- =========================
         SIDEBAR
    ========================= -->

    <aside class="sidebar">


        <div class="logo-area">

            <div class="logo-circle">
                🎤
            </div>

            <h2>
                Stage Manager
            </h2>

            <p>
                Event Management System
            </p>

        </div>


        <nav class="menu">

            <a href="dashboard.php">

                📊
                Dashboard

            </a>


            <a href="equipment.php">

                🎛️
                Equipment

            </a>


            <a href="handover.php">

                🎤
                Stage Handover

            </a>


            <a
                href="profile.php"
                class="active"
            >

                👤
                Profile

            </a>


            <div class="logout">

                <a href="../logout.php">

                    🚪
                    Logout

                </a>

            </div>

        </nav>

    </aside>



    <!-- =========================
         MAIN CONTENT
    ========================= -->

    <main class="main">


        <!-- TOP BAR -->

        <div class="topbar">

            <div>

                <h1>
                    My Profile
                </h1>

                <p>
                    Manage your Stage Manager account details.
                </p>

            </div>


            <div class="user-badge">

                <div class="avatar-small">

                    <?php
                    echo strtoupper(
                        substr(
                            $name,
                            0,
                            1
                        )
                    );
                    ?>

                </div>


                <div class="user-info">

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $name
                        );
                        ?>

                    </strong>

                    <span>
                        Stage Manager
                    </span>

                </div>

            </div>

        </div>



        <div class="profile-wrapper">


            <div class="profile-card">


                <!-- PROFILE HEADER -->

                <div class="profile-header">


                    <div class="profile-avatar">

                        <?php
                        echo strtoupper(
                            substr(
                                $name,
                                0,
                                1
                            )
                        );
                        ?>

                    </div>


                    <div>

                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $name
                            );
                            ?>

                        </h2>


                        <p>

                            <?php
                            echo htmlspecialchars(
                                $email
                            );
                            ?>

                        </p>


                        <span class="role-badge">

                            🎤
                            <?php
                            echo $role_display;
                            ?>

                        </span>

                    </div>

                </div>



                <!-- MESSAGE -->

                <?php if ($message !== ""): ?>

                    <div
                        class="message
                        <?php
                        echo htmlspecialchars(
                            $message_type
                        );
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $message
                        );
                        ?>

                    </div>

                <?php endif; ?>



                <!-- PROFILE BODY -->

                <div class="profile-body">


                    <h2 class="section-title">
                        Personal Information
                    </h2>


                    <form
                        method="POST"
                        action="profile.php"
                    >


                        <div class="form-grid">


                            <!-- NAME -->

                            <div class="form-group">

                                <label for="name">
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $name
                                    );
                                    ?>"
                                    required
                                >

                            </div>



                            <!-- PHONE -->

                            <div class="form-group">

                                <label for="phone">
                                    Phone Number
                                </label>

                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $phone
                                    );
                                    ?>"
                                >

                            </div>



                            <!-- EMAIL -->

                            <div class="form-group">

                                <label for="email">
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $email
                                    );
                                    ?>"
                                    readonly
                                >

                                <span class="field-note">
                                    Email cannot be changed here.
                                </span>

                            </div>



                            <!-- STUDENT ID -->

                            <div class="form-group">

                                <label for="student_id">
                                    Staff / Student ID
                                </label>

                                <input
                                    type="text"
                                    id="student_id"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $student_id
                                    );
                                    ?>"
                                    readonly
                                >

                                <span class="field-note">
                                    ID is linked to your account.
                                </span>

                            </div>



                            <!-- COLLEGE -->

                            <div class="form-group">

                                <label for="college">
                                    College
                                </label>

                                <input
                                    type="text"
                                    id="college"
                                    name="college"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $college
                                    );
                                    ?>"
                                >

                            </div>



                            <!-- DEPARTMENT -->

                            <div class="form-group">

                                <label for="department">
                                    Department
                                </label>

                                <input
                                    type="text"
                                    id="department"
                                    name="department"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $department
                                    );
                                    ?>"
                                >

                            </div>



                            <!-- ROLE -->

                            <div class="form-group full">

                                <label for="role">
                                    Account Role
                                </label>

                                <input
                                    type="text"
                                    id="role"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $role_display
                                    );
                                    ?>"
                                    readonly
                                >

                                <span class="field-note">
                                    Your role is managed by the system administrator.
                                </span>

                            </div>


                        </div>



                        <!-- ACTION BUTTONS -->

                        <div class="profile-actions">


                            <a
                                href="dashboard.php"
                                class="btn btn-secondary"
                            >

                                Back to Dashboard

                            </a>


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                💾 Save Changes

                            </button>


                        </div>


                    </form>



                    <!-- ACCOUNT INFORMATION -->

                    <div class="account-info">


                        <h3>
                            Account Information
                        </h3>


                        <div class="account-grid">


                            <div class="account-item">

                                <span>
                                    Account ID
                                </span>

                                <strong>
                                    #<?php
                                    echo (int)$user["id"];
                                    ?>
                                </strong>

                            </div>


                            <div class="account-item">

                                <span>
                                    Role
                                </span>

                                <strong>
                                    Stage Manager
                                </strong>

                            </div>


                            <div class="account-item">

                                <span>
                                    Login Email
                                </span>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $email
                                    );
                                    ?>
                                </strong>

                            </div>


                        </div>

                    </div>


                </div>

            </div>

        </div>


    </main>


</body>

</html>