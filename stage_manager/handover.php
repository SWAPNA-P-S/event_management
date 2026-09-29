<?php

session_start();

require_once "../db.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "stage_manager"
) {
    echo "Access denied.";
    exit();
}


/* =========================================================
   HELPER FUNCTION
   CHECK WHETHER REQUIREMENT EXISTS
========================================================= */

function requirementExists($requirements, $keywords)
{
    $requirements = strtolower($requirements);

    foreach ($keywords as $keyword) {

        if (strpos($requirements, strtolower($keyword)) !== false) {
            return true;
        }
    }

    return false;
}


/* =========================================================
   UPDATE HANDOVER CHECKLIST
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $booking_id = isset($_POST["booking_id"])
        ? (int) $_POST["booking_id"]
        : 0;

    $cleaned = isset($_POST["cleaned"]) ? 1 : 0;

    $microphone_ready =
        isset($_POST["microphone_ready"]) ? 1 : 0;

    $sound_checked =
        isset($_POST["sound_checked"]) ? 1 : 0;

    $lights_checked =
        isset($_POST["lights_checked"]) ? 1 : 0;

    $projector_ready =
        isset($_POST["projector_ready"]) ? 1 : 0;

    $equipment_ready =
        isset($_POST["equipment_ready"]) ? 1 : 0;

    $handed_over =
        isset($_POST["handed_over"]) ? 1 : 0;

    $remarks = trim(
        $_POST["remarks"] ?? ""
    );


    if ($booking_id > 0) {


        /* =================================================
           GET EVENT + REQUIREMENTS
        ================================================= */

        $requirement_stmt = $conn->prepare(
            "SELECT
                sb.event_id,
                e.event_name,
                e.requirements,
                e.organizer_id
             FROM stage_bookings sb
             INNER JOIN events e
                ON sb.event_id = e.event_id
             WHERE sb.booking_id = ?
             LIMIT 1"
        );

        $requirement_stmt->bind_param(
            "i",
            $booking_id
        );

        $requirement_stmt->execute();

        $requirement_result =
            $requirement_stmt->get_result();

        $requirement_row =
            $requirement_result->fetch_assoc();

        $requirement_stmt->close();


        if (!$requirement_row) {

            header(
                "Location: handover.php"
            );

            exit();
        }


        $event_id =
            (int) $requirement_row["event_id"];

        $event_name =
            $requirement_row["event_name"];

        $requirements =
            $requirement_row["requirements"] ?? "";

        $organizer_id =
            (int) $requirement_row["organizer_id"];


        /* =================================================
           DETERMINE REQUIRED ITEMS
        ================================================= */

        $microphone_required = requirementExists(
            $requirements,
            [
                "mic",
                "microphone"
            ]
        );

        $sound_required = requirementExists(
            $requirements,
            [
                "sound",
                "speaker",
                "audio"
            ]
        );

        $lights_required = requirementExists(
            $requirements,
            [
                "light",
                "lights",
                "lighting"
            ]
        );

        $projector_required = requirementExists(
            $requirements,
            [
                "projector"
            ]
        );


        /* =================================================
           AUTOMATIC STAGE READY CHECK
        ================================================= */

        $stage_ready = 1;


        /* Stage must always be cleaned */

        if ($cleaned !== 1) {
            $stage_ready = 0;
        }


        /* Microphone */

        if (
            $microphone_required &&
            $microphone_ready !== 1
        ) {
            $stage_ready = 0;
        }


        /* Sound */

        if (
            $sound_required &&
            $sound_checked !== 1
        ) {
            $stage_ready = 0;
        }


        /* Lights */

        if (
            $lights_required &&
            $lights_checked !== 1
        ) {
            $stage_ready = 0;
        }


        /* Projector */

        if (
            $projector_required &&
            $projector_ready !== 1
        ) {
            $stage_ready = 0;
        }


        /* General equipment */

        if ($equipment_ready !== 1) {
            $stage_ready = 0;
        }


        /* =================================================
           CHECK EXISTING HANDOVER RECORD
        ================================================= */

        $check = $conn->prepare(
            "SELECT
                handover_id,
                stage_ready
             FROM stage_handover
             WHERE booking_id = ?
             LIMIT 1"
        );

        $check->bind_param(
            "i",
            $booking_id
        );

        $check->execute();

        $check_result =
            $check->get_result();

        $existing_handover =
            $check_result->fetch_assoc();

        $check->close();


        /*
         * Remember previous ready status.
         * This helps us create notification only
         * when stage becomes ready for the first time.
         */

        $previous_stage_ready = 0;

        if ($existing_handover) {

            $previous_stage_ready =
                (int) $existing_handover["stage_ready"];
        }


        /* =================================================
           SAVE HANDOVER
        ================================================= */

        if ($existing_handover) {


            /* =============================================
               UPDATE EXISTING RECORD
            ============================================= */

            $update = $conn->prepare(
                "UPDATE stage_handover
                 SET
                    cleaned = ?,
                    equipment_ready = ?,
                    sound_checked = ?,
                    lights_checked = ?,
                    stage_ready = ?,
                    handed_over = ?,
                    handover_time = CASE
                        WHEN ? = 1
                        THEN NOW()
                        ELSE handover_time
                    END,
                    remarks = ?
                 WHERE booking_id = ?"
            );

            $update->bind_param(
                "iiiiiiisi",
                $cleaned,
                $equipment_ready,
                $sound_checked,
                $lights_checked,
                $stage_ready,
                $handed_over,
                $handed_over,
                $remarks,
                $booking_id
            );

            $update->execute();

            $update->close();


        } else {


            /* =============================================
               CREATE NEW HANDOVER RECORD
            ============================================= */

            $handover_time = null;

            if ($handed_over === 1) {

                $handover_time =
                    date("Y-m-d H:i:s");
            }


            $insert = $conn->prepare(
                "INSERT INTO stage_handover
                (
                    booking_id,
                    cleaned,
                    equipment_ready,
                    sound_checked,
                    lights_checked,
                    stage_ready,
                    handed_over,
                    handover_time,
                    remarks
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $insert->bind_param(
                "iiiiiiiss",
                $booking_id,
                $cleaned,
                $equipment_ready,
                $sound_checked,
                $lights_checked,
                $stage_ready,
                $handed_over,
                $handover_time,
                $remarks
            );

            $insert->execute();

            $insert->close();
        }


        /* =================================================
           ORGANIZER NOTIFICATION
        ================================================= */

        /*
         * Only create notification when:
         *
         * previous status = NOT READY
         * current status  = READY
         *
         * This prevents duplicate notifications.
         */

        if (
            $stage_ready === 1 &&
            $previous_stage_ready === 0
        ) {


            /* =============================================
               CHECK WHETHER NOTIFICATION ALREADY EXISTS
            ============================================= */

            $notification_check = $conn->prepare(
                "SELECT update_id
                 FROM updates
                 WHERE event_id = ?
                 AND update_type = 'stage_ready'
                 LIMIT 1"
            );

            $notification_check->bind_param(
                "i",
                $event_id
            );

            $notification_check->execute();

            $notification_result =
                $notification_check->get_result();

            $notification_exists =
                $notification_result->num_rows > 0;

            $notification_check->close();


            /* =============================================
               INSERT NOTIFICATION
            ============================================= */

            if (!$notification_exists) {

                $notification_title =
                    "Stage Ready";

                $notification_message =
                    "Your stage is ready for tomorrow's event: "
                    . $event_name
                    . ".";

                $notification_type =
                    "stage_ready";


                $notification_insert = $conn->prepare(
                    "INSERT INTO updates
                    (
                        event_id,
                        title,
                        message,
                        update_type
                    )
                    VALUES (?, ?, ?, ?)"
                );

                $notification_insert->bind_param(
                    "isss",
                    $event_id,
                    $notification_title,
                    $notification_message,
                    $notification_type
                );

                $notification_insert->execute();

                $notification_insert->close();
            }
        }
    }


    /* =================================================
       REDIRECT
    ================================================= */

    header(
        "Location: handover.php?updated=1"
    );

    exit();
}


/* =========================================================
   FETCH UPCOMING STAGE BOOKINGS
========================================================= */

$booking_query = "
    SELECT
        sb.booking_id,
        sb.booking_date,
        sb.start_time,
        sb.end_time,
        sb.booking_status,

        s.stage_name,
        s.location,

        e.event_name,
        e.requirements,

        sh.handover_id,
        sh.cleaned,
        sh.equipment_ready,
        sh.sound_checked,
        sh.lights_checked,
        sh.stage_ready,
        sh.handed_over,
        sh.handover_time,
        sh.remarks

    FROM stage_bookings sb

    INNER JOIN stages s
        ON sb.stage_id = s.stage_id

    INNER JOIN events e
        ON sb.event_id = e.event_id

    LEFT JOIN stage_handover sh
        ON sb.booking_id = sh.booking_id

    WHERE sb.booking_date >= CURDATE()

    AND sb.booking_status IN (
        'pending',
        'assigned',
        'confirmed'
    )

    ORDER BY
        sb.booking_date ASC,
        sb.start_time ASC
";

$booking_result =
    $conn->query($booking_query);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Stage Handover | Stage Manager
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #333;
        }


        .layout {
            display: flex;
            min-height: 100vh;
        }


        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            width: 240px;
            background: #24153f;
            color: white;
            padding: 25px 15px;

            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
        }


        .logo {
            text-align: center;
            margin-bottom: 35px;
        }


        .logo h2 {
            font-size: 21px;
            margin-bottom: 5px;
        }


        .logo p {
            font-size: 12px;
            color: #cfc6df;
        }


        .nav a {
            display: block;
            text-decoration: none;
            color: #ddd;

            padding: 13px 15px;
            margin-bottom: 8px;

            border-radius: 8px;

            font-size: 14px;
        }


        .nav a:hover,
        .nav a.active {
            background: #6b46a1;
            color: white;
        }


        .logout {
            margin-top: 30px;

            border-top: 1px solid #4a3a61;

            padding-top: 20px;
        }


        .logout a {
            color: #ffb4b4;
        }


        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 240px;

            width: calc(100% - 240px);

            padding: 35px;
        }


        .top-section {
            margin-bottom: 30px;
        }


        .top-section h1 {
            color: #24153f;

            font-size: 30px;

            margin-bottom: 8px;
        }


        .top-section p {
            color: #777;

            font-size: 14px;
        }


        /* =========================
           SUCCESS
        ========================= */

        .success-message {
            background: #e4f8eb;

            color: #218838;

            padding: 14px 18px;

            border-radius: 9px;

            margin-bottom: 25px;

            font-weight: bold;
        }


        /* =========================
           EVENT CARD
        ========================= */

        .event-card {
            background: white;

            border-radius: 15px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.06);
        }


        .event-header {
            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 20px;

            margin-bottom: 22px;
        }


        .event-header h2 {
            color: #35204f;

            margin-bottom: 7px;
        }


        .event-header p {
            color: #777;

            font-size: 13px;
        }


        .status {
            padding: 7px 13px;

            border-radius: 20px;

            background: #eee7fa;

            color: #5b3b8c;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;
        }


        /* =========================
           DETAILS
        ========================= */

        .details {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 25px;
        }


        .detail-box {
            background: #f8f6fb;

            padding: 15px;

            border-radius: 10px;
        }


        .detail-box span {
            display: block;

            color: #888;

            font-size: 12px;

            margin-bottom: 6px;
        }


        .detail-box strong {
            color: #3c2a50;

            font-size: 14px;
        }


        /* =========================
           REQUIREMENTS
        ========================= */

        .requirements {
            background: #faf8fd;

            border-left:
                4px solid #6b46a1;

            padding: 15px;

            border-radius: 8px;

            margin-bottom: 25px;
        }


        .requirements h3 {
            color: #4a3567;

            font-size: 14px;

            margin-bottom: 7px;
        }


        .requirements p {
            color: #666;

            font-size: 13px;

            line-height: 1.6;
        }


        /* =========================
           CHECKLIST
        ========================= */

        .checklist-title {
            color: #35204f;

            margin-bottom: 15px;
        }


        .checklist {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 12px;

            margin-bottom: 20px;
        }


        .check-item {
            display: flex;

            align-items: center;

            gap: 10px;

            background: #f8f6fb;

            padding: 14px 15px;

            border-radius: 8px;

            cursor: pointer;
        }


        .check-item:hover {
            background: #f0ebf8;
        }


        .check-item input {
            width: 19px;
            height: 19px;

            cursor: pointer;
        }


        .check-item label {
            cursor: pointer;

            font-size: 14px;

            color: #444;
        }


        /* =========================
           REMARKS
        ========================= */

        .remarks-label {
            display: block;

            font-weight: bold;

            color: #4a3567;

            margin-bottom: 8px;
        }


        textarea {
            width: 100%;

            min-height: 90px;

            padding: 12px;

            border:
                1px solid #ddd;

            border-radius: 8px;

            resize: vertical;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;
        }


        textarea:focus {
            border-color: #6b46a1;
        }


        /* =========================
           BUTTON
        ========================= */

        .save-btn {
            margin-top: 15px;

            padding: 12px 25px;

            background: #6b46a1;

            color: white;

            border: none;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;
        }


        .save-btn:hover {
            background: #563681;
        }


        /* =========================
           READY STATUS
        ========================= */

        .ready-message {
            margin-top: 18px;

            padding: 14px 16px;

            border-radius: 8px;

            background: #e4f8eb;

            color: #218838;

            font-size: 13px;

            font-weight: bold;
        }


        .not-ready-message {
            margin-top: 18px;

            padding: 14px 16px;

            border-radius: 8px;

            background: #fff7e6;

            color: #9a6700;

            font-size: 13px;
        }


        /* =========================
           EMPTY
        ========================= */

        .empty {
            background: white;

            border-radius: 15px;

            padding: 50px 20px;

            text-align: center;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.06);
        }


        .empty h2 {
            color: #4a3567;

            margin-bottom: 8px;
        }


        .empty p {
            color: #777;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .details {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 800px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;

                width:
                    calc(100% - 210px);

                padding: 25px;
            }

            .checklist {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 650px) {

            .layout {
                display: block;
            }

            .sidebar {
                position: relative;

                width: 100%;

                min-height: auto;
            }

            .main {
                margin-left: 0;

                width: 100%;

                padding: 20px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .event-header {
                flex-direction: column;
            }

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">


        <div class="logo">

            <h2>
                Stage Manager
            </h2>

            <p>
                Event Management System
            </p>

        </div>


        <nav class="nav">

            <a href="dashboard.php">
                📊 Dashboard
            </a>

            <a href="equipment.php">
                🎤 Equipment
            </a>

            <a
                href="handover.php"
                class="active"
            >
                🤝 Stage Handover
            </a>

            <a href="profile.php">
                👤 Profile
            </a>


            <div class="logout">

                <a href="../logout.php">
                    🚪 Logout
                </a>

            </div>

        </nav>

    </aside>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <div class="top-section">

            <h1>
                Stage Handover
            </h1>

            <p>
                Complete the preparation checklist for upcoming events.
            </p>

        </div>


        <?php if (isset($_GET["updated"])): ?>

            <div class="success-message">

                ✓ Stage preparation status updated successfully.

            </div>

        <?php endif; ?>


        <?php if (
            $booking_result &&
            $booking_result->num_rows > 0
        ): ?>


            <?php while (
                $booking =
                    $booking_result->fetch_assoc()
            ): ?>


                <?php

                $requirements =
                    $booking["requirements"] ?? "";


                $microphone_required =
                    requirementExists(
                        $requirements,
                        [
                            "mic",
                            "microphone"
                        ]
                    );


                $sound_required =
                    requirementExists(
                        $requirements,
                        [
                            "sound",
                            "speaker",
                            "audio"
                        ]
                    );


                $lights_required =
                    requirementExists(
                        $requirements,
                        [
                            "light",
                            "lights",
                            "lighting"
                        ]
                    );


                $projector_required =
                    requirementExists(
                        $requirements,
                        [
                            "projector"
                        ]
                    );

                ?>


                <div class="event-card">


                    <!-- EVENT HEADER -->

                    <div class="event-header">

                        <div>

                            <h2>

                                <?php
                                echo htmlspecialchars(
                                    $booking["event_name"]
                                );
                                ?>

                            </h2>

                            <p>

                                Booking ID:
                                <?php
                                echo $booking["booking_id"];
                                ?>

                            </p>

                        </div>


                        <span class="status">

                            <?php
                            echo htmlspecialchars(
                                ucfirst(
                                    $booking["booking_status"]
                                )
                            );
                            ?>

                        </span>

                    </div>


                    <!-- EVENT DETAILS -->

                    <div class="details">


                        <div class="detail-box">

                            <span>
                                Stage
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $booking["stage_name"]
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="detail-box">

                            <span>
                                Date
                            </span>

                            <strong>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $booking["booking_date"]
                                    )
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="detail-box">

                            <span>
                                Start Time
                            </span>

                            <strong>

                                <?php
                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $booking["start_time"]
                                    )
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="detail-box">

                            <span>
                                End Time
                            </span>

                            <strong>

                                <?php
                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $booking["end_time"]
                                    )
                                );
                                ?>

                            </strong>

                        </div>

                    </div>


                    <!-- REQUIREMENTS -->

                    <div class="requirements">

                        <h3>
                            Organizer's Stage Requirements
                        </h3>

                        <p>

                            <?php

                            if (!empty($requirements)) {

                                echo nl2br(
                                    htmlspecialchars(
                                        $requirements
                                    )
                                );

                            } else {

                                echo
                                    "No special requirements specified.";

                            }

                            ?>

                        </p>

                    </div>


                    <!-- CHECKLIST FORM -->

                    <form
                        action="handover.php"
                        method="POST"
                    >


                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?php
                                echo $booking["booking_id"];
                            ?>"
                        >


                        <h3 class="checklist-title">

                            Stage Preparation Checklist

                        </h3>


                        <div class="checklist">


                            <!-- STAGE CLEANED -->

                            <div class="check-item">

                                <input
                                    type="checkbox"
                                    id="cleaned_<?php
                                        echo $booking["booking_id"];
                                    ?>"
                                    name="cleaned"
                                    <?php
                                    echo
                                        $booking["cleaned"]
                                        ? "checked"
                                        : "";
                                    ?>
                                >

                                <label
                                    for="cleaned_<?php
                                        echo $booking["booking_id"];
                                    ?>"
                                >

                                    🧹 Stage Cleaned

                                </label>

                            </div>


                            <!-- REQUIRED EQUIPMENT -->

                            <div class="check-item">

                                <input
                                    type="checkbox"
                                    id="equipment_<?php
                                        echo $booking["booking_id"];
                                    ?>"
                                    name="equipment_ready"
                                    <?php
                                    echo
                                        $booking["equipment_ready"]
                                        ? "checked"
                                        : "";
                                    ?>
                                >

                                <label
                                    for="equipment_<?php
                                        echo $booking["booking_id"];
                                    ?>"
                                >

                                    📦 Required Equipment Set

                                </label>

                            </div>


                            <!-- MICROPHONE -->

                            <?php if ($microphone_required): ?>

                                <div class="check-item">

                                    <input
                                        type="checkbox"
                                        id="microphone_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                        name="microphone_ready"
                                    >

                                    <label
                                        for="microphone_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                    >

                                        🎤 Microphone Set

                                    </label>

                                </div>

                            <?php endif; ?>


                            <!-- SOUND -->

                            <?php if ($sound_required): ?>

                                <div class="check-item">

                                    <input
                                        type="checkbox"
                                        id="sound_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                        name="sound_checked"
                                        <?php
                                        echo
                                            $booking["sound_checked"]
                                            ? "checked"
                                            : "";
                                        ?>
                                    >

                                    <label
                                        for="sound_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                    >

                                        🔊 Sound System Checked

                                    </label>

                                </div>

                            <?php endif; ?>


                            <!-- LIGHTS -->

                            <?php if ($lights_required): ?>

                                <div class="check-item">

                                    <input
                                        type="checkbox"
                                        id="lights_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                        name="lights_checked"
                                        <?php
                                        echo
                                            $booking["lights_checked"]
                                            ? "checked"
                                            : "";
                                        ?>
                                    >

                                    <label
                                        for="lights_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                    >

                                        💡 Lights Set & Checked

                                    </label>

                                </div>

                            <?php endif; ?>


                            <!-- PROJECTOR -->

                            <?php if ($projector_required): ?>

                                <div class="check-item">

                                    <input
                                        type="checkbox"
                                        id="projector_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                        name="projector_ready"
                                    >

                                    <label
                                        for="projector_<?php
                                            echo $booking["booking_id"];
                                        ?>"
                                    >

                                        📽️ Projector Ready

                                    </label>

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- REMARKS -->

                        <label
                            class="remarks-label"
                            for="remarks_<?php
                                echo $booking["booking_id"];
                            ?>"
                        >

                            Remarks

                        </label>


                        <textarea
                            id="remarks_<?php
                                echo $booking["booking_id"];
                            ?>"
                            name="remarks"
                            placeholder="Enter any preparation notes..."
                        ><?php
                        echo htmlspecialchars(
                            $booking["remarks"] ?? ""
                        );
                        ?></textarea>


                        <button
                            type="submit"
                            class="save-btn"
                        >

                            Save Preparation Status

                        </button>


                        <!-- CURRENT STATUS -->

                        <?php if (
                            !empty(
                                $booking["stage_ready"]
                            )
                        ): ?>

                            <div class="ready-message">

                                ✅ <strong>Stage Ready!</strong>

                                All required preparation
                                checks have been completed.

                                <br><br>

                                🔔 Organizer notification has been
                                created.

                            </div>

                        <?php else: ?>

                            <div class="not-ready-message">

                                ⏳ Stage preparation is still pending.
                                Complete all required checklist items.

                            </div>

                        <?php endif; ?>


                    </form>


                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="empty">

                <h2>
                    No Upcoming Stage Bookings
                </h2>

                <p>
                    There are no upcoming stage bookings
                    requiring preparation.
                </p>

            </div>


        <?php endif; ?>


    </main>

</div>


</body>

</html>


<?php

$conn->close();

?>