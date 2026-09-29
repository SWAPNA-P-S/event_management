<?php

session_start();

require_once "../db.php";


/* =========================================================
   1. LOGIN CHECK
   ========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   2. ORGANIZER ACCESS CHECK
   ========================================================= */

$is_organizer = false;
$approved_request_ids = [];

if (
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "organizer"
) {

    $is_organizer = true;

} else {

    $organizer_check = $conn->prepare(
        "SELECT tm.request_id
         FROM organizer_team_members tm
         INNER JOIN organizer_requests r
             ON tm.request_id = r.request_id
         WHERE tm.user_id = ?
           AND r.status = 'approved'"
    );

    $organizer_check->bind_param("i", $user_id);
    $organizer_check->execute();

    $organizer_result = $organizer_check->get_result();

    while ($row = $organizer_result->fetch_assoc()) {

        $approved_request_ids[] = (int)$row["request_id"];

    }

    $organizer_check->close();

    if (count($approved_request_ids) > 0) {

        $is_organizer = true;

    }
}


if (!$is_organizer) {

    echo "<script>
            alert('You do not have organizer access.');
            window.location.href='../student_dashboard.php';
          </script>";

    exit();
}


/* =========================================================
   3. VARIABLES
   ========================================================= */

$message = "";
$message_type = "";

$selected_event_id = isset($_GET["event_id"])
    ? (int)$_GET["event_id"]
    : (isset($_POST["event_id"]) ? (int)$_POST["event_id"] : 0);

$selected_stage_id = isset($_GET["stage_id"])
    ? (int)$_GET["stage_id"]
    : (isset($_POST["stage_id"]) ? (int)$_POST["stage_id"] : 0);

$selected_date = isset($_GET["booking_date"])
    ? $_GET["booking_date"]
    : (isset($_POST["booking_date"]) ? $_POST["booking_date"] : "");

$selected_slot_id = isset($_POST["slot_id"])
    ? (int)$_POST["slot_id"]
    : 0;


/* =========================================================
   4. GET APPROVED ORGANIZER EVENTS
   ========================================================= */

$events = [];

if (
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "organizer"
) {

    $event_stmt = $conn->prepare(
        "SELECT
            event_id,
            event_name,
            event_date,
            start_time,
            end_time,
            venue,
            organizer_request_id
         FROM events
         WHERE organizer_id = ?
           AND request_status = 'approved'
         ORDER BY event_date ASC, start_time ASC"
    );

    $event_stmt->bind_param("i", $user_id);

} else {

    if (count($approved_request_ids) > 0) {

        $placeholders = implode(
            ",",
            array_fill(
                0,
                count($approved_request_ids),
                "?"
            )
        );

        $types = str_repeat(
            "i",
            count($approved_request_ids)
        );

        $sql =
            "SELECT
                event_id,
                event_name,
                event_date,
                start_time,
                end_time,
                venue,
                organizer_request_id
             FROM events
             WHERE organizer_request_id IN ($placeholders)
               AND request_status = 'approved'
             ORDER BY event_date ASC, start_time ASC";

        $event_stmt = $conn->prepare($sql);

        $bind_values = [];
        $bind_values[] = $types;

        foreach ($approved_request_ids as $key => $request_id) {

            $bind_values[] =
                &$approved_request_ids[$key];

        }

        call_user_func_array(
            [$event_stmt, "bind_param"],
            $bind_values
        );
    }
}


if (isset($event_stmt)) {

    $event_stmt->execute();

    $event_result = $event_stmt->get_result();

    while ($row = $event_result->fetch_assoc()) {

        $events[] = $row;

    }

    $event_stmt->close();
}


/* =========================================================
   5. GET AVAILABLE STAGES
   ========================================================= */

$stages = [];

$stage_stmt = $conn->prepare(
    "SELECT
        stage_id,
        stage_name,
        location,
        capacity,
        description,
        status
     FROM stages
     WHERE status = 'available'
     ORDER BY stage_name ASC"
);

$stage_stmt->execute();

$stage_result = $stage_stmt->get_result();

while ($row = $stage_result->fetch_assoc()) {

    $stages[] = $row;

}

$stage_stmt->close();


/* =========================================================
   6. GET AVAILABLE TIME SLOTS
   ========================================================= */

$time_slots = [];

if (
    $selected_stage_id > 0 &&
    !empty($selected_date)
) {

    $slot_stmt = $conn->prepare(
        "SELECT
            slot_id,
            stage_id,
            slot_date,
            start_time,
            end_time,
            slot_status
         FROM stage_time_slots
         WHERE stage_id = ?
           AND slot_date = ?
           AND slot_status = 'available'
         ORDER BY start_time ASC"
    );

    $slot_stmt->bind_param(
        "is",
        $selected_stage_id,
        $selected_date
    );

    $slot_stmt->execute();

    $slot_result = $slot_stmt->get_result();

    while ($row = $slot_result->fetch_assoc()) {

        $time_slots[] = $row;

    }

    $slot_stmt->close();
}


/* =========================================================
   7. BOOKING SUBMISSION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = (int)($_POST["event_id"] ?? 0);
    $stage_id = (int)($_POST["stage_id"] ?? 0);
    $booking_date = trim($_POST["booking_date"] ?? "");
    $slot_id = (int)($_POST["slot_id"] ?? 0);

    $mic_required =
        isset($_POST["mic_required"]) ? 1 : 0;

    $sound_required =
        isset($_POST["sound_required"]) ? 1 : 0;

    $lights_required =
        isset($_POST["lights_required"]) ? 1 : 0;

    $projector_required =
        isset($_POST["projector_required"]) ? 1 : 0;

    $other_requirements =
        trim($_POST["other_requirements"] ?? "");


    /* -----------------------------------------------------
       BASIC VALIDATION
       ----------------------------------------------------- */

    if (
        $event_id <= 0 ||
        $stage_id <= 0 ||
        $slot_id <= 0 ||
        empty($booking_date)
    ) {

        $message =
            "Please select Event, Stage, Date and Time Slot.";

        $message_type = "error";

    } elseif ($booking_date < date("Y-m-d")) {

        $message =
            "You cannot book a stage for a past date.";

        $message_type = "error";

    } else {


        /* =================================================
           8. VERIFY EVENT BELONGS TO ORGANIZER
           ================================================= */

        $event_valid = false;
        $event_date = "";
        $event_start = "";
        $event_end = "";

        if (
            isset($_SESSION["role"]) &&
            $_SESSION["role"] === "organizer"
        ) {

            $verify_event = $conn->prepare(
                "SELECT
                    event_id,
                    event_date,
                    start_time,
                    end_time
                 FROM events
                 WHERE event_id = ?
                   AND organizer_id = ?
                   AND request_status = 'approved'
                 LIMIT 1"
            );

            $verify_event->bind_param(
                "ii",
                $event_id,
                $user_id
            );

        } else {

            $verify_event = $conn->prepare(
                "SELECT
                    e.event_id,
                    e.event_date,
                    e.start_time,
                    e.end_time
                 FROM events e
                 INNER JOIN organizer_team_members tm
                     ON e.organizer_request_id = tm.request_id
                 INNER JOIN organizer_requests r
                     ON tm.request_id = r.request_id
                 WHERE e.event_id = ?
                   AND tm.user_id = ?
                   AND r.status = 'approved'
                   AND e.request_status = 'approved'
                 LIMIT 1"
            );

            $verify_event->bind_param(
                "ii",
                $event_id,
                $user_id
            );
        }

        $verify_event->execute();

        $verify_result =
            $verify_event->get_result();

        if ($verify_result->num_rows === 1) {

            $event_data =
                $verify_result->fetch_assoc();

            $event_valid = true;

            $event_date =
                $event_data["event_date"];

            $event_start =
                $event_data["start_time"];

            $event_end =
                $event_data["end_time"];
        }

        $verify_event->close();


        if (!$event_valid) {

            $message =
                "Invalid event or you do not have access to this event.";

            $message_type = "error";

        }


        /* =================================================
           9. CHECK BOOKING DATE
           ================================================= */

        elseif ($booking_date !== $event_date) {

            $message =
                "Stage booking date must match the event date.";

            $message_type = "error";

        }


        else {


            /* =================================================
               10. GET SELECTED TIME SLOT
               ================================================= */

            $slot_stmt = $conn->prepare(
                "SELECT
                    slot_id,
                    stage_id,
                    slot_date,
                    start_time,
                    end_time,
                    slot_status
                 FROM stage_time_slots
                 WHERE slot_id = ?
                   AND stage_id = ?
                   AND slot_date = ?
                   AND slot_status = 'available'
                 LIMIT 1"
            );

            $slot_stmt->bind_param(
                "iis",
                $slot_id,
                $stage_id,
                $booking_date
            );

            $slot_stmt->execute();

            $slot_result =
                $slot_stmt->get_result();


            if ($slot_result->num_rows !== 1) {

                $message =
                    "Selected time slot is no longer available.";

                $message_type = "error";

                $slot_stmt->close();

            } else {

                $slot =
                    $slot_result->fetch_assoc();

                $slot_stmt->close();


                $slot_start =
                    $slot["start_time"];

                $slot_end =
                    $slot["end_time"];


                /* =============================================
                   11. CHECK EVENT TIME
                   ============================================= */

                if (
                    $slot_start < $event_start ||
                    $slot_end > $event_end
                ) {

                    $message =
                        "Selected stage time slot is outside the event time.";

                    $message_type = "error";

                } else {


                    /* =========================================
                       12. CHECK BOOKING CONFLICT
                       ========================================= */

                    $conflict_stmt = $conn->prepare(
                        "SELECT booking_id
                         FROM stage_bookings
                         WHERE stage_id = ?
                           AND booking_date = ?
                           AND booking_status <> 'cancelled'
                           AND start_time < ?
                           AND end_time > ?
                         LIMIT 1"
                    );

                    $conflict_stmt->bind_param(
                        "isss",
                        $stage_id,
                        $booking_date,
                        $slot_end,
                        $slot_start
                    );

                    $conflict_stmt->execute();

                    $conflict_result =
                        $conflict_stmt->get_result();

                    $has_conflict =
                        ($conflict_result->num_rows > 0);

                    $conflict_stmt->close();


                    if ($has_conflict) {

                        $message =
                            "This stage is already booked for the selected time.";

                        $message_type = "error";

                    } else {


                        /* =====================================
                           13. START TRANSACTION
                           ===================================== */

                        $conn->begin_transaction();


                        try {


                            /* =================================
                               INSERT BOOKING
                               ================================= */

                            $booking_status = "pending";

                            $buffer_minutes = 0;

                            $insert_booking = $conn->prepare(
                                "INSERT INTO stage_bookings
                                (
                                    stage_id,
                                    event_id,
                                    booking_date,
                                    start_time,
                                    end_time,
                                    buffer_minutes,
                                    booking_status
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?)"
                            );

                            $insert_booking->bind_param(
                                "iisssis",
                                $stage_id,
                                $event_id,
                                $booking_date,
                                $slot_start,
                                $slot_end,
                                $buffer_minutes,
                                $booking_status
                            );

                            if (!$insert_booking->execute()) {

                                throw new Exception(
                                    "Booking could not be created."
                                );
                            }

                            $booking_id =
                                $insert_booking->insert_id;

                            $insert_booking->close();


                            /* =================================
                               INSERT REQUIREMENTS
                               ================================= */

                            $requirement_stmt = $conn->prepare(
                                "INSERT INTO stage_requirements
                                (
                                    booking_id,
                                    mic_required,
                                    sound_required,
                                    lights_required,
                                    projector_required,
                                    other_requirements
                                )
                                VALUES (?, ?, ?, ?, ?, ?)"
                            );

                            $requirement_stmt->bind_param(
                                "iiiiis",
                                $booking_id,
                                $mic_required,
                                $sound_required,
                                $lights_required,
                                $projector_required,
                                $other_requirements
                            );

                            if (!$requirement_stmt->execute()) {

                                throw new Exception(
                                    "Stage requirements could not be saved."
                                );
                            }

                            $requirement_stmt->close();


                            /* =================================
                               MARK TIME SLOT BOOKED
                               ================================= */

                            $slot_status = "booked";

                            $update_slot = $conn->prepare(
                                "UPDATE stage_time_slots
                                 SET slot_status = ?
                                 WHERE slot_id = ?
                                   AND slot_status = 'available'"
                            );

                            $update_slot->bind_param(
                                "si",
                                $slot_status,
                                $slot_id
                            );

                            if (
                                !$update_slot->execute() ||
                                $update_slot->affected_rows !== 1
                            ) {

                                throw new Exception(
                                    "Time slot is no longer available."
                                );
                            }

                            $update_slot->close();


                            /* =================================
                               CREATE HANDOVER RECORD
                               ================================= */

                            $handover_stmt = $conn->prepare(
                                "INSERT INTO stage_handover
                                (
                                    booking_id,
                                    cleaned,
                                    equipment_ready,
                                    sound_checked,
                                    lights_checked,
                                    stage_ready,
                                    handed_over
                                )
                                VALUES (?, 0, 0, 0, 0, 0, 0)"
                            );

                            if (!$handover_stmt->bind_param(
                                "i",
                                $booking_id
                            )) {

                                throw new Exception(
                                    "Handover preparation failed."
                                );
                            }

                            if (!$handover_stmt->execute()) {

                                throw new Exception(
                                    "Handover record could not be created."
                                );
                            }

                            $handover_stmt->close();


                            /* =================================
                               COMMIT
                               ================================= */

                            $conn->commit();


                            $message =
                                "Stage booking submitted successfully. Booking ID: "
                                . $booking_id;

                            $message_type = "success";


                            $selected_event_id = 0;
                            $selected_stage_id = 0;
                            $selected_date = "";
                            $selected_slot_id = 0;

                            $time_slots = [];


                        } catch (Exception $e) {

                            $conn->rollback();

                            $message =
                                "Unable to create stage booking. Please try again.";

                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
}


/* =========================================================
   14. GET MY BOOKINGS
   ========================================================= */

$my_bookings = [];

if (
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "organizer"
) {

    $booking_stmt = $conn->prepare(
        "SELECT
            sb.booking_id,
            sb.booking_date,
            sb.start_time,
            sb.end_time,
            sb.booking_status,
            s.stage_name,
            s.location,
            e.event_name
         FROM stage_bookings sb
         INNER JOIN stages s
             ON sb.stage_id = s.stage_id
         INNER JOIN events e
             ON sb.event_id = e.event_id
         WHERE e.organizer_id = ?
         ORDER BY sb.booking_date DESC,
                  sb.start_time DESC"
    );

    $booking_stmt->bind_param(
        "i",
        $user_id
    );

} else {

    if (count($approved_request_ids) > 0) {

        $placeholders = implode(
            ",",
            array_fill(
                0,
                count($approved_request_ids),
                "?"
            )
        );

        $types =
            str_repeat(
                "i",
                count($approved_request_ids)
            );

        $sql =
            "SELECT
                sb.booking_id,
                sb.booking_date,
                sb.start_time,
                sb.end_time,
                sb.booking_status,
                s.stage_name,
                s.location,
                e.event_name
             FROM stage_bookings sb
             INNER JOIN stages s
                 ON sb.stage_id = s.stage_id
             INNER JOIN events e
                 ON sb.event_id = e.event_id
             WHERE e.organizer_request_id IN ($placeholders)
             ORDER BY sb.booking_date DESC,
                      sb.start_time DESC";

        $booking_stmt =
            $conn->prepare($sql);

        $bind_values = [];

        $bind_values[] = $types;

        foreach ($approved_request_ids as $key => $request_id) {

            $bind_values[] =
                &$approved_request_ids[$key];

        }

        call_user_func_array(
            [$booking_stmt, "bind_param"],
            $bind_values
        );
    }
}


if (isset($booking_stmt)) {

    $booking_stmt->execute();

    $booking_result =
        $booking_stmt->get_result();

    while ($row =
        $booking_result->fetch_assoc()
    ) {

        $my_bookings[] = $row;

    }

    $booking_stmt->close();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Stage Management | Organizer</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

.header {
    background: #1e3a8a;
    color: white;
    padding: 20px 30px;
}

.header h1 {
    margin: 0 0 5px;
    font-size: 25px;
}

.header p {
    margin: 0;
    opacity: .9;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 30px auto;
}

.back {
    display: inline-block;
    margin-bottom: 20px;
    color: #1e3a8a;
    text-decoration: none;
    font-weight: bold;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

.card h2 {
    margin-top: 0;
    color: #1e3a8a;
}

.info {
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    padding: 14px;
    margin-bottom: 22px;
    line-height: 1.6;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.full {
    grid-column: 1 / -1;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: bold;
    margin-bottom: 7px;
}

select,
input,
textarea {
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

.btn {
    border: none;
    padding: 13px 22px;
    border-radius: 7px;
    background: #2563eb;
    color: white;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

.btn:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #64748b;
}

.btn-secondary:hover {
    background: #475569;
}

.message {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
}

.message.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.requirements {
    border: 1px solid #dbeafe;
    background: #f8fbff;
    padding: 20px;
    border-radius: 10px;
}

.requirements h3 {
    margin-top: 0;
    color: #1e3a8a;
}

.check-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 15px;
}

.check-item {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 12px;
    border-radius: 7px;
}

.check-item label {
    margin: 0;
    font-weight: normal;
    cursor: pointer;
}

.check-item input {
    margin-right: 8px;
}

.slot-box {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    padding: 18px;
    border-radius: 9px;
}

.slot-box h3 {
    margin-top: 0;
    color: #1e3a8a;
}

.no-slots {
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    padding: 12px;
    border-radius: 7px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f8fafc;
    color: #1e3a8a;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    text-transform: capitalize;
}

.status.pending {
    background: #fef3c7;
    color: #92400e;
}

.status.assigned {
    background: #dbeafe;
    color: #1e40af;
}

.status.confirmed {
    background: #dcfce7;
    color: #166534;
}

.status.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #64748b;
}

@media (max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .check-row {
        grid-template-columns: 1fr;
    }

    .container {
        width: 95%;
    }
}

</style>

</head>


<body>


<div class="header">

    <h1>🎤 Stage Management</h1>

    <p>Organizer / Event Committee</p>

</div>


<div class="container">


<a
    href="organizer_dashboard.php"
    class="back"
>
    ← Back to Organizer Dashboard
</a>


<?php if (!empty($message)): ?>

<div class="message <?php echo htmlspecialchars($message_type); ?>">

    <?php echo htmlspecialchars($message); ?>

</div>

<?php endif; ?>


<div class="card">

<h2>🎪 Book Stage for Your Event</h2>


<div class="info">

<strong>How it works:</strong>

Select your approved event → choose an available stage →
select an available time slot → specify stage requirements →
confirm the booking.

The Stage Manager will see the assigned booking and prepare
the stage.

</div>


<?php if (count($events) === 0): ?>

<div class="empty">

    No approved events are available for stage management.

</div>

<?php else: ?>


<form method="GET" action="stage_booking.php">

<div class="form-grid">


<div class="form-group full">

<label for="event_id">
    Select Event *
</label>

<select
    name="event_id"
    id="event_id"
    required
>

<option value="">
    -- Select Event --
</option>

<?php foreach ($events as $event): ?>

<option
    value="<?php echo (int)$event["event_id"]; ?>"
    data-date="<?php echo htmlspecialchars($event["event_date"]); ?>"
    <?php
    echo (
        $selected_event_id ==
        $event["event_id"]
    )
        ? "selected"
        : "";
    ?>
>

<?php echo htmlspecialchars($event["event_name"]); ?>

—
<?php echo htmlspecialchars($event["event_date"]); ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label for="stage_id">
    Select Stage *
</label>

<select
    name="stage_id"
    id="stage_id"
    required
>

<option value="">
    -- Select Stage --
</option>

<?php foreach ($stages as $stage): ?>

<option
    value="<?php echo (int)$stage["stage_id"]; ?>"
    <?php
    echo (
        $selected_stage_id ==
        $stage["stage_id"]
    )
        ? "selected"
        : "";
    ?>
>

<?php echo htmlspecialchars($stage["stage_name"]); ?>

<?php if (!empty($stage["location"])): ?>

—
<?php echo htmlspecialchars($stage["location"]); ?>

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label for="booking_date">
    Booking Date *
</label>

<input
    type="date"
    name="booking_date"
    id="booking_date"
    value="<?php echo htmlspecialchars($selected_date); ?>"
    min="<?php echo date("Y-m-d"); ?>"
    required
>

</div>


<div class="form-group full">

<button
    type="submit"
    class="btn btn-secondary"
>
    🔍 Check Available Time Slots
</button>

</div>

</div>

</form>


<?php if (
    $selected_stage_id > 0 &&
    !empty($selected_date)
): ?>


<div class="slot-box" style="margin-top:25px;">

<h3>🕒 Available Time Slots</h3>


<?php if (count($time_slots) === 0): ?>

<div class="no-slots">

    No available time slots found for this stage and date.

</div>

<?php else: ?>


<form method="POST" action="stage_booking.php">

<input
    type="hidden"
    name="event_id"
    value="<?php echo (int)$selected_event_id; ?>"
>

<input
    type="hidden"
    name="stage_id"
    value="<?php echo (int)$selected_stage_id; ?>"
>

<input
    type="hidden"
    name="booking_date"
    value="<?php echo htmlspecialchars($selected_date); ?>"
>


<div class="form-group">

<label for="slot_id">
    Select Time Slot *
</label>

<select
    name="slot_id"
    id="slot_id"
    required
>

<option value="">
    -- Select Available Slot --
</option>

<?php foreach ($time_slots as $slot): ?>

<option
    value="<?php echo (int)$slot["slot_id"]; ?>"
    <?php
    echo (
        $selected_slot_id ==
        $slot["slot_id"]
    )
        ? "selected"
        : "";
    ?>
>

<?php echo date(
    "h:i A",
    strtotime($slot["start_time"])
); ?>

—

<?php echo date(
    "h:i A",
    strtotime($slot["end_time"])
); ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div
    class="requirements"
    style="margin-top:22px;"
>

<h3>🔧 Stage Requirements</h3>


<div class="check-row">


<div class="check-item">

<label>

<input
    type="checkbox"
    name="mic_required"
    value="1"
>

🎤 Microphone

</label>

</div>


<div class="check-item">

<label>

<input
    type="checkbox"
    name="sound_required"
    value="1"
>

🔊 Sound System

</label>

</div>


<div class="check-item">

<label>

<input
    type="checkbox"
    name="lights_required"
    value="1"
>

💡 Lights

</label>

</div>


<div class="check-item">

<label>

<input
    type="checkbox"
    name="projector_required"
    value="1"
>

📽️ Projector

</label>

</div>


</div>


<div class="form-group">

<label for="other_requirements">

Other Requirements

</label>

<textarea
    name="other_requirements"
    id="other_requirements"
    placeholder="Enter any additional stage requirements..."
></textarea>

</div>

</div>


<div style="margin-top:20px;">

<button
    type="submit"
    class="btn"
>
    📅 Confirm Stage Booking
</button>

</div>


</form>


<?php endif; ?>

</div>


<?php endif; ?>


<?php endif; ?>

</div>


<div class="card">

<h2>📋 My Stage Bookings</h2>


<?php if (count($my_bookings) === 0): ?>

<div class="empty">

No stage bookings found.

</div>

<?php else: ?>


<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Event</th>

<th>Stage</th>

<th>Date</th>

<th>Time</th>

<th>Status</th>

</tr>

</thead>


<tbody>

<?php foreach ($my_bookings as $booking): ?>

<?php

$status_class =
    strtolower(
        $booking["booking_status"]
    );

?>

<tr>

<td>

<?php echo htmlspecialchars(
    $booking["event_name"]
); ?>

</td>


<td>

<?php echo htmlspecialchars(
    $booking["stage_name"]
); ?>

<br>

<small>

<?php echo htmlspecialchars(
    $booking["location"]
); ?>

</small>

</td>


<td>

<?php echo htmlspecialchars(
    $booking["booking_date"]
); ?>

</td>


<td>

<?php echo date(
    "h:i A",
    strtotime($booking["start_time"])
); ?>

—

<?php echo date(
    "h:i A",
    strtotime($booking["end_time"])
); ?>

</td>


<td>

<span
    class="status <?php echo htmlspecialchars($status_class); ?>"
>

<?php echo htmlspecialchars(
    $booking["booking_status"]
); ?>

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php endif; ?>

</div>


</div>


<script>

const eventSelect =
    document.getElementById("event_id");

const dateInput =
    document.getElementById("booking_date");


if (eventSelect && dateInput) {

    eventSelect.addEventListener(
        "change",
        function () {

            const option =
                this.options[this.selectedIndex];

            if (!option) {
                return;
            }

            const eventDate =
                option.getAttribute("data-date");

            if (eventDate) {

                dateInput.value = eventDate;

            }

        }
    );

}

</script>


</body>

</html>

<?php

$conn->close();

?>