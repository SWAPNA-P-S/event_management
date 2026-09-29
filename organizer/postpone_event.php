<?php
session_start();
require_once "../config/db.php";

/* =========================================================
   ACCESS CONTROL
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$user_role = $_SESSION["role"] ?? "";

/*
   Allowed:
   - organizer
   - student organizer
   - admin/core management
*/
$allowed_roles = ["organizer", "student_organizer", "admin", "core_admin"];

if (!in_array($user_role, $allowed_roles)) {
    die("Access denied.");
}


/* =========================================================
   GET EVENT ID
========================================================= */

$event_id = isset($_GET["event_id"]) ? intval($_GET["event_id"]) : 0;

if ($event_id <= 0) {
    die("Invalid event ID.");
}


/* =========================================================
   FETCH EVENT
========================================================= */

$stmt = $conn->prepare("
    SELECT
        event_id,
        event_name,
        event_date,
        start_time,
        end_time,
        venue,
        status,
        request_status,
        original_event_date,
        postponed_from_date,
        postponement_reason,
        postponement_status
    FROM events
    WHERE event_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $event_id);
$stmt->execute();

$event_result = $stmt->get_result();
$event = $event_result->fetch_assoc();

if (!$event) {
    die("Event not found.");
}


/* =========================================================
   PREVENT MULTIPLE POSTPONEMENT
========================================================= */

if (
    !empty($event["postponed_from_date"]) ||
    (
        isset($event["postponement_status"]) &&
        $event["postponement_status"] !== "none" &&
        $event["postponement_status"] !== ""
    )
) {
    die("This event has already been postponed.");
}


/* =========================================================
   FETCH CURRENT STAGE BOOKING
========================================================= */

$current_booking = null;

$stmt = $conn->prepare("
    SELECT
        sb.booking_id,
        sb.stage_id,
        sb.booking_date,
        sb.start_time,
        sb.end_time,
        sb.booking_status,
        s.stage_name,
        s.location
    FROM stage_bookings sb
    LEFT JOIN stages s
        ON sb.stage_id = s.stage_id
    WHERE sb.event_id = ?
    ORDER BY sb.booking_id DESC
    LIMIT 1
");

$stmt->bind_param("i", $event_id);
$stmt->execute();

$booking_result = $stmt->get_result();
$current_booking = $booking_result->fetch_assoc();


/* =========================================================
   FETCH ALL STAGES
========================================================= */

$stages = [];

$stage_result = $conn->query("
    SELECT
        stage_id,
        stage_name,
        location,
        capacity,
        description,
        status
    FROM stages
    ORDER BY stage_name ASC
");

if ($stage_result) {
    while ($row = $stage_result->fetch_assoc()) {
        $stages[] = $row;
    }
}


/* =========================================================
   FETCH CURRENT BROCHURE
========================================================= */

$current_brochure = null;

$stmt = $conn->prepare("
    SELECT
        brochure_id,
        brochure_file,
        upload_date,
        status,
        correction_reason
    FROM event_brochures
    WHERE event_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $event_id);
$stmt->execute();

$brochure_result = $stmt->get_result();
$current_brochure = $brochure_result->fetch_assoc();


/* =========================================================
   AJAX STAGE AVAILABILITY CHECK
========================================================= */

if (
    isset($_GET["action"]) &&
    $_GET["action"] === "check_stage_availability"
) {

    header("Content-Type: application/json");

    $check_date = $_GET["date"] ?? "";
    $check_start = $_GET["start_time"] ?? "";
    $check_end = $_GET["end_time"] ?? "";

    if (
        empty($check_date) ||
        empty($check_start) ||
        empty($check_end)
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Date and time are required."
        ]);
        exit;
    }

    if ($check_start >= $check_end) {
        echo json_encode([
            "success" => false,
            "message" => "End time must be after start time."
        ]);
        exit;
    }

    $availability = [];

    foreach ($stages as $stage) {

        if (
            isset($stage["status"]) &&
            strtolower($stage["status"]) !== "available"
        ) {
            $availability[$stage["stage_id"]] = [
                "available" => false,
                "reason" => "Stage is currently unavailable."
            ];
            continue;
        }

        $stage_id = intval($stage["stage_id"]);

        /*
           Check overlapping bookings.

           Overlap condition:

           existing start < new end
           AND
           existing end > new start
        */

        $stmt = $conn->prepare("
            SELECT booking_id
            FROM stage_bookings
            WHERE stage_id = ?
              AND booking_date = ?
              AND event_id <> ?
              AND booking_status IN ('assigned', 'booked', 'pending')
              AND start_time < ?
              AND end_time > ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "isiss",
            $stage_id,
            $check_date,
            $event_id,
            $check_end,
            $check_start
        );

        $stmt->execute();

        $conflict_result = $stmt->get_result();

        if ($conflict_result->num_rows > 0) {

            $availability[$stage_id] = [
                "available" => false,
                "reason" => "Already booked for this time."
            ];

        } else {

            $availability[$stage_id] = [
                "available" => true,
                "reason" => "Available"
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "availability" => $availability
    ]);

    exit;
}


/* =========================================================
   VARIABLES
========================================================= */

$error = "";
$success = "";

$new_date = "";
$new_start_time = "";
$new_end_time = "";
$new_stage_id = "";
$reason = "";


/* =========================================================
   FORM SUBMISSION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_date = trim($_POST["new_date"] ?? "");
    $new_start_time = trim($_POST["new_start_time"] ?? "");
    $new_end_time = trim($_POST["new_end_time"] ?? "");
    $new_stage_id = intval($_POST["new_stage_id"] ?? 0);
    $reason = trim($_POST["reason"] ?? "");


    /* -----------------------------------------------------
       BASIC VALIDATION
    ----------------------------------------------------- */

    if (
        empty($new_date) ||
        empty($new_start_time) ||
        empty($new_end_time) ||
        $new_stage_id <= 0 ||
        empty($reason)
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!isset($_FILES["brochure"]) || $_FILES["brochure"]["error"] === UPLOAD_ERR_NO_FILE) {

        $error = "Please upload the updated brochure.";

    } else {

        /* -------------------------------------------------
           DATE VALIDATION
        ------------------------------------------------- */

        if ($new_date <= date("Y-m-d")) {

            $error = "The new event date must be a future date.";

        }

        /* -------------------------------------------------
           TIME VALIDATION
        ------------------------------------------------- */

        elseif ($new_start_time >= $new_end_time) {

            $error = "End time must be after start time.";

        }

        else {

            /* ---------------------------------------------
               CHECK STAGE EXISTS
            --------------------------------------------- */

            $selected_stage = null;

            foreach ($stages as $stage) {

                if (intval($stage["stage_id"]) === $new_stage_id) {
                    $selected_stage = $stage;
                    break;
                }
            }


            if (!$selected_stage) {

                $error = "Invalid stage selected.";

            }

            /* ---------------------------------------------
               CHECK STAGE STATUS
            --------------------------------------------- */

            elseif (
                isset($selected_stage["status"]) &&
                strtolower($selected_stage["status"]) !== "available"
            ) {

                $error = "The selected stage is currently unavailable.";

            }

            else {

                /* -----------------------------------------
                   CHECK STAGE BOOKING CONFLICT
                ----------------------------------------- */

                $stmt = $conn->prepare("
                    SELECT
                        booking_id,
                        event_id,
                        start_time,
                        end_time
                    FROM stage_bookings
                    WHERE stage_id = ?
                      AND booking_date = ?
                      AND event_id <> ?
                      AND booking_status IN ('assigned', 'booked', 'pending')
                      AND start_time < ?
                      AND end_time > ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "isiss",
                    $new_stage_id,
                    $new_date,
                    $event_id,
                    $new_end_time,
                    $new_start_time
                );

                $stmt->execute();

                $conflict_result = $stmt->get_result();

                if ($conflict_result->num_rows > 0) {

                    $conflict = $conflict_result->fetch_assoc();

                    $error =
                        "The selected stage is already booked from " .
                        date("h:i A", strtotime($conflict["start_time"])) .
                        " to " .
                        date("h:i A", strtotime($conflict["end_time"])) .
                        " on the selected date. Please choose another stage or time.";

                }
            }
        }


        /* -------------------------------------------------
           BROCHURE VALIDATION
        ------------------------------------------------- */

        if (empty($error)) {

            $brochure = $_FILES["brochure"];

            if ($brochure["error"] !== UPLOAD_ERR_OK) {

                $error = "There was a problem uploading the brochure.";

            } else {

                $allowed_extensions = [
                    "pdf",
                    "jpg",
                    "jpeg",
                    "png"
                ];

                $file_extension = strtolower(
                    pathinfo($brochure["name"], PATHINFO_EXTENSION)
                );

                if (!in_array($file_extension, $allowed_extensions)) {

                    $error =
                        "Invalid brochure format. Only PDF, JPG, JPEG and PNG files are allowed.";

                } elseif ($brochure["size"] > 10 * 1024 * 1024) {

                    $error = "Brochure size must not exceed 10 MB.";

                }
            }
        }


        /* -------------------------------------------------
           SAVE EVERYTHING
        ------------------------------------------------- */

        if (empty($error)) {

            $upload_directory = "../uploads/";

            if (!is_dir($upload_directory)) {
                mkdir($upload_directory, 0777, true);
            }


            /*
               Generate unique filename.
            */

            $unique_name =
                "brochure_" .
                $event_id .
                "_postponed_" .
                date("Ymd_His") .
                "_" .
                bin2hex(random_bytes(4)) .
                "." .
                $file_extension;

            $new_brochure_path =
                $upload_directory . $unique_name;


            if (!move_uploaded_file(
                $brochure["tmp_name"],
                $new_brochure_path
            )) {

                $error = "Unable to save the uploaded brochure.";

            } else {

                $old_brochure_file = null;

                try {

                    $conn->begin_transaction();


                    /* -------------------------------------
                       UPDATE EVENT
                    ------------------------------------- */

                    $original_event_date =
                        !empty($event["original_event_date"])
                        ? $event["original_event_date"]
                        : $event["event_date"];

                    $old_event_date = $event["event_date"];


                    $stmt = $conn->prepare("
                        UPDATE events
                        SET
                            original_event_date = ?,
                            postponed_from_date = ?,
                            event_date = ?,
                            start_time = ?,
                            end_time = ?,
                            venue = ?,
                            postponement_reason = ?,
                            postponement_status = 'pending',
                            request_status = 'pending',
                            status = 'pending',
                            publication_status = 'draft'
                        WHERE event_id = ?
                    ");

                    $venue_value = $selected_stage["location"];

                    $stmt->bind_param(
                        "sssssssi",
                        $original_event_date,
                        $old_event_date,
                        $new_date,
                        $new_start_time,
                        $new_end_time,
                        $venue_value,
                        $reason,
                        $event_id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Unable to update event.");
                    }


                    /* -------------------------------------
                       UPDATE / CREATE STAGE BOOKING
                    ------------------------------------- */

                    if ($current_booking) {

                        $booking_id =
                            intval($current_booking["booking_id"]);

                        $stmt = $conn->prepare("
                            UPDATE stage_bookings
                            SET
                                stage_id = ?,
                                booking_date = ?,
                                start_time = ?,
                                end_time = ?,
                                booking_status = 'pending',
                                confirmed_at = NULL
                            WHERE booking_id = ?
                        ");

                        $stmt->bind_param(
                            "isssi",
                            $new_stage_id,
                            $new_date,
                            $new_start_time,
                            $new_end_time,
                            $booking_id
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Unable to update stage booking."
                            );
                        }

                    } else {

                        $stmt = $conn->prepare("
                            INSERT INTO stage_bookings
                            (
                                stage_id,
                                event_id,
                                booking_date,
                                start_time,
                                end_time,
                                buffer_minutes,
                                booking_status,
                                confirmed_at
                            )
                            VALUES
                            (?, ?, ?, ?, ?, 0, 'pending', NULL)
                        ");

                        $stmt->bind_param(
                            "iisss",
                            $new_stage_id,
                            $event_id,
                            $new_date,
                            $new_start_time,
                            $new_end_time
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Unable to create stage booking."
                            );
                        }
                    }


                    /* -------------------------------------
                       UPDATE / CREATE BROCHURE
                    ------------------------------------- */

                    if ($current_brochure) {

                        $old_brochure_file =
                            $current_brochure["brochure_file"];

                        $brochure_status = "pending";

                        if (
                            isset($current_brochure["status"]) &&
                            $current_brochure["status"] === "correction_required"
                        ) {
                            $brochure_status = "resubmitted";
                        }


                        $stmt = $conn->prepare("
                            UPDATE event_brochures
                            SET
                                brochure_file = ?,
                                upload_date = CURRENT_TIMESTAMP,
                                status = ?,
                                correction_reason = NULL
                            WHERE brochure_id = ?
                        ");

                        $brochure_id =
                            intval($current_brochure["brochure_id"]);

                        $stmt->bind_param(
                            "ssi",
                            $unique_name,
                            $brochure_status,
                            $brochure_id
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Unable to update brochure."
                            );
                        }

                    } else {

                        $stmt = $conn->prepare("
                            INSERT INTO event_brochures
                            (
                                event_id,
                                brochure_file,
                                status
                            )
                            VALUES
                            (?, ?, 'pending')
                        ");

                        $stmt->bind_param(
                            "is",
                            $event_id,
                            $unique_name
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Unable to save brochure."
                            );
                        }
                    }


                    /* -------------------------------------
                       COMMIT
                    ------------------------------------- */

                    $conn->commit();


                    /*
                       Delete old brochure only AFTER
                       successful database commit.
                    */

                    if (!empty($old_brochure_file)) {

                        $old_path =
                            $upload_directory . $old_brochure_file;

                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }


                    /* -------------------------------------
                       REDIRECT
                    ------------------------------------- */

                    header(
                        "Location: postponement_verification.php?event_id=" .
                        $event_id
                    );

                    exit;

                } catch (Exception $e) {

                    $conn->rollback();

                    /*
                       Remove newly uploaded file because
                       database operation failed.
                    */

                    if (file_exists($new_brochure_path)) {
                        unlink($new_brochure_path);
                    }

                    $error =
                        "Unable to complete postponement: " .
                        $e->getMessage();
                }
            }
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

    <title>Postpone Event</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f1ff;
            color: #29203d;
        }

        .container {
            width: 90%;
            max-width: 900px;
            margin: 40px auto;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(80, 50, 120, 0.12);
        }

        h1 {
            margin-top: 0;
            color: #633f91;
        }

        h2 {
            color: #633f91;
            font-size: 20px;
            margin-top: 30px;
        }

        .event-info {
            background: #f3edff;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 25px;
        }

        .event-info p {
            margin: 8px 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 22px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d6c9e8;
            border-radius: 10px;
            font-size: 15px;
            background: white;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #7650a8;
            box-shadow: 0 0 0 3px rgba(118, 80, 168, 0.12);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .time-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .stage-info {
            background: #faf8ff;
            border: 1px solid #e1d7f0;
            border-radius: 12px;
            padding: 16px;
            margin-top: 10px;
        }

        .stage-status {
            margin-top: 12px;
            font-size: 14px;
            font-weight: bold;
        }

        .available {
            color: #168447;
        }

        .booked {
            color: #d13d3d;
        }

        .checking {
            color: #876f9f;
        }

        .brochure-box {
            background: #faf8ff;
            border: 2px dashed #cbb9e2;
            border-radius: 14px;
            padding: 20px;
        }

        .brochure-box input {
            background: white;
        }

        .help-text {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: #75677f;
            line-height: 1.5;
        }

        .current-brochure {
            background: #f3edff;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 14px;
            font-size: 14px;
        }

        .warning {
            background: #fff5df;
            border-left: 5px solid #e5a72b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .error {
            background: #ffe9e9;
            color: #b32626;
            border-left: 5px solid #d33;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #e9fff1;
            color: #17763e;
            border-left: 5px solid #29a35a;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .checklist {
            background: #f8f5fc;
            padding: 18px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .checklist div {
            margin: 8px 0;
        }

        .submit-btn {
            width: 100%;
            border: none;
            padding: 14px;
            margin-top: 25px;
            border-radius: 10px;
            background: #6f49a0;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #593887;
        }

        .submit-btn:disabled {
            background: #b7abc5;
            cursor: not-allowed;
        }

        .required {
            color: red;
        }

        @media (max-width: 650px) {

            .time-row {
                grid-template-columns: 1fr;
            }

            .container {
                width: 95%;
            }

            .card {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>Postpone Event</h1>

        <div class="event-info">

            <p>
                <strong>Event:</strong>
                <?= htmlspecialchars($event["event_name"]) ?>
            </p>

            <p>
                <strong>Current Date:</strong>
                <?= htmlspecialchars($event["event_date"]) ?>
            </p>

            <p>
                <strong>Current Time:</strong>
                <?= htmlspecialchars($event["start_time"]) ?>
                -
                <?= htmlspecialchars($event["end_time"]) ?>
            </p>

            <p>
                <strong>Current Venue:</strong>
                <?= htmlspecialchars($event["venue"]) ?>
            </p>

            <?php if ($current_booking): ?>

                <p>
                    <strong>Current Stage:</strong>
                    <?= htmlspecialchars($current_booking["stage_name"]) ?>
                </p>

            <?php endif; ?>

        </div>


        <div class="warning">

            <strong>Important:</strong>

            Since the event date/time/venue may change,
            you must upload an
            <strong>updated brochure</strong>
            containing the new event details.

            The new brochure will be sent for management review.

        </div>


        <?php if (!empty($error)): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($success)): ?>

            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data"
            id="postponeForm"
        >


            <!-- =========================================
                 NEW DATE
            ========================================== -->

            <h2>1. New Event Schedule</h2>

            <div class="form-group">

                <label for="new_date">
                    New Event Date
                    <span class="required">*</span>
                </label>

                <input
                    type="date"
                    id="new_date"
                    name="new_date"
                    value="<?= htmlspecialchars($new_date) ?>"
                    min="<?= date("Y-m-d", strtotime("+1 day")) ?>"
                    required
                >

            </div>


            <div class="time-row">

                <div class="form-group">

                    <label for="new_start_time">
                        New Start Time
                        <span class="required">*</span>
                    </label>

                    <input
                        type="time"
                        id="new_start_time"
                        name="new_start_time"
                        value="<?= htmlspecialchars($new_start_time) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="new_end_time">
                        New End Time
                        <span class="required">*</span>
                    </label>

                    <input
                        type="time"
                        id="new_end_time"
                        name="new_end_time"
                        value="<?= htmlspecialchars($new_end_time) ?>"
                        required
                    >

                </div>

            </div>


            <!-- =========================================
                 STAGE
            ========================================== -->

            <h2>2. Select New Venue / Stage</h2>

            <div class="form-group">

                <label for="new_stage_id">
                    Venue / Stage
                    <span class="required">*</span>
                </label>

                <select
                    name="new_stage_id"
                    id="new_stage_id"
                    required
                >

                    <option value="">
                        -- Select Venue / Stage --
                    </option>

                    <?php foreach ($stages as $stage): ?>

                        <?php
                        $is_unavailable =
                            isset($stage["status"]) &&
                            strtolower($stage["status"]) !== "available";
                        ?>

                        <option
                            value="<?= intval($stage["stage_id"]) ?>"
                            <?= (
                                $new_stage_id ==
                                intval($stage["stage_id"])
                            ) ? "selected" : "" ?>
                            <?= $is_unavailable ? "disabled" : "" ?>
                        >

                            <?= htmlspecialchars($stage["stage_name"]) ?>

                            -
                            <?= htmlspecialchars($stage["location"]) ?>

                            <?php if ($is_unavailable): ?>
                                (Currently Unavailable)
                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <div
                    id="stageAvailability"
                    class="stage-status checking"
                >
                    Select date and time to check stage availability.
                </div>

            </div>


            <!-- =========================================
                 BROCHURE UPLOAD
            ========================================== -->

            <h2>3. Updated Brochure</h2>

            <div class="brochure-box">

                <?php if ($current_brochure): ?>

                    <div class="current-brochure">

                        <strong>Current brochure:</strong>

                        <?= htmlspecialchars(
                            basename($current_brochure["brochure_file"])
                        ) ?>

                        <br>

                        <small>
                            Status:
                            <?= htmlspecialchars(
                                $current_brochure["status"]
                            ) ?>
                        </small>

                    </div>

                <?php endif; ?>


                <div class="form-group">

                    <label for="brochure">

                        Upload Updated Brochure

                        <span class="required">*</span>

                    </label>

                    <input
                        type="file"
                        name="brochure"
                        id="brochure"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    >

                    <span class="help-text">

                        Upload the new brochure containing the
                        <strong>updated date, time and venue/stage</strong>.

                        <br>

                        Allowed formats:
                        PDF, JPG, JPEG, PNG.

                        Maximum size:
                        10 MB.

                    </span>

                </div>

            </div>


            <!-- =========================================
                 REASON
            ========================================== -->

            <h2>4. Reason for Postponement</h2>

            <div class="form-group">

                <label for="reason">

                    Reason

                    <span class="required">*</span>

                </label>

                <textarea
                    name="reason"
                    id="reason"
                    placeholder="Enter the reason for postponing the event..."
                    required
                ><?= htmlspecialchars($reason) ?></textarea>

            </div>


            <!-- =========================================
                 CHECKLIST
            ========================================== -->

            <div class="checklist">

                <strong>Before submitting:</strong>

                <div>
                    ✓ New date selected
                </div>

                <div>
                    ✓ New start and end time selected
                </div>

                <div>
                    ✓ Stage availability checked
                </div>

                <div>
                    ✓ Updated brochure uploaded
                </div>

                <div>
                    ✓ Reason for postponement entered
                </div>

            </div>


            <button
                type="submit"
                class="submit-btn"
                id="submitBtn"
            >
                Submit Postponement
            </button>

        </form>

    </div>

</div>


<script>

const dateInput =
    document.getElementById("new_date");

const startInput =
    document.getElementById("new_start_time");

const endInput =
    document.getElementById("new_end_time");

const stageSelect =
    document.getElementById("new_stage_id");

const availabilityBox =
    document.getElementById("stageAvailability");

const submitBtn =
    document.getElementById("submitBtn");


let selectedStageAvailable = false;


/* =========================================================
   CHECK STAGE AVAILABILITY
========================================================= */

async function checkStageAvailability() {

    const date = dateInput.value;
    const start = startInput.value;
    const end = endInput.value;

    /*
       Reset
    */

    selectedStageAvailable = false;

    if (!date || !start || !end) {

        availabilityBox.textContent =
            "Select date and time to check stage availability.";

        availabilityBox.className =
            "stage-status checking";

        return;
    }


    if (start >= end) {

        availabilityBox.textContent =
            "End time must be after start time.";

        availabilityBox.className =
            "stage-status booked";

        return;
    }


    availabilityBox.textContent =
        "Checking stage availability...";

    availabilityBox.className =
        "stage-status checking";


    try {

        const response = await fetch(
            "postpone_event.php?action=check_stage_availability" +
            "&event_id=<?= $event_id ?>" +
            "&date=" + encodeURIComponent(date) +
            "&start_time=" + encodeURIComponent(start) +
            "&end_time=" + encodeURIComponent(end)
        );


        const data = await response.json();


        if (!data.success) {

            availabilityBox.textContent =
                data.message || "Unable to check availability.";

            availabilityBox.className =
                "stage-status booked";

            return;
        }


        /*
           Update dropdown options
        */

        const options =
            stageSelect.querySelectorAll("option");


        options.forEach(function(option) {

            const stageId = option.value;

            if (!stageId) {
                return;
            }


            /*
               Do not override stages that are
               permanently unavailable.
            */

            const stageData =
                data.availability[stageId];


            if (!stageData) {
                return;
            }


            if (stageData.available) {

                option.disabled = false;

                /*
                   Add available indicator.
                */

                const originalText =
                    option.textContent
                        .replace(
                            " - Available",
                            ""
                        )
                        .replace(
                            " - BOOKED",
                            ""
                        );

                option.textContent =
                    originalText + " - Available";

            } else {

                option.disabled = true;

                const originalText =
                    option.textContent
                        .replace(
                            " - Available",
                            ""
                        )
                        .replace(
                            " - BOOKED",
                            ""
                        );

                option.textContent =
                    originalText + " - BOOKED";

                /*
                   If currently selected stage became
                   unavailable, clear selection.
                */

                if (
                    stageSelect.value === stageId
                ) {

                    stageSelect.value = "";

                }

            }

        });


        /*
           Count available stages
        */

        let availableCount = 0;

        Object.values(
            data.availability
        ).forEach(function(item) {

            if (item.available) {
                availableCount++;
            }

        });


        if (availableCount > 0) {

            availabilityBox.textContent =
                availableCount +
                " stage(s) available for the selected date and time.";

            availabilityBox.className =
                "stage-status available";

        } else {

            availabilityBox.textContent =
                "No stage is available for the selected date and time. Please change the date or time.";

            availabilityBox.className =
                "stage-status booked";

        }


        /*
           Check currently selected stage
        */

        if (stageSelect.value) {

            const selected =
                data.availability[
                    stageSelect.value
                ];

            if (
                selected &&
                selected.available
            ) {

                selectedStageAvailable = true;

            } else {

                selectedStageAvailable = false;

            }

        }


    } catch (error) {

        console.error(error);

        availabilityBox.textContent =
            "Unable to check stage availability.";

        availabilityBox.className =
            "stage-status booked";

    }

}


/* =========================================================
   EVENTS
========================================================= */

dateInput.addEventListener(
    "change",
    checkStageAvailability
);

startInput.addEventListener(
    "change",
    checkStageAvailability
);

endInput.addEventListener(
    "change",
    checkStageAvailability
);

stageSelect.addEventListener(
    "change",
    checkStageAvailability
);


/* =========================================================
   FORM SUBMIT VALIDATION
========================================================= */

document
    .getElementById("postponeForm")
    .addEventListener("submit", function(event) {

        const date =
            dateInput.value;

        const start =
            startInput.value;

        const end =
            endInput.value;

        const stage =
            stageSelect.value;

        const brochure =
            document.getElementById("brochure").files.length;


        if (!date || !start || !end || !stage) {

            event.preventDefault();

            alert(
                "Please complete the date, time and stage details."
            );

            return;

        }


        if (start >= end) {

            event.preventDefault();

            alert(
                "End time must be after start time."
            );

            return;

        }


        if (!brochure) {

            event.preventDefault();

            alert(
                "Please upload the updated brochure."
            );

            return;

        }

    });


/*
   Run availability check if values already exist.
*/

if (
    dateInput.value &&
    startInput.value &&
    endInput.value
) {

    checkStageAvailability();

}

</script>

</body>

</html>