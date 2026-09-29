<?php

session_start();

include "../db.php";

/* ==========================================
   LOGIN CHECK
========================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$organizer_id = (int)$_SESSION["user_id"];

/* ==========================================
   ORGANIZER ACCESS
========================================== */

$has_organizer_access = false;
$organizer_request_id = null;

if (
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "organizer"
) {
    $has_organizer_access = true;
}

$organizer_check = $conn->prepare(
    "SELECT tm.request_id
     FROM organizer_team_members tm
     INNER JOIN organizer_requests r
        ON tm.request_id = r.request_id
     WHERE tm.user_id = ?
       AND r.status = 'approved'
     ORDER BY tm.request_id ASC
     LIMIT 1"
);

$organizer_check->bind_param("i", $organizer_id);
$organizer_check->execute();

$organizer_result = $organizer_check->get_result();

if ($organizer_result->num_rows > 0) {

    $organizer_row = $organizer_result->fetch_assoc();

    $organizer_request_id =
        (int)$organizer_row["request_id"];

    $has_organizer_access = true;
}

$organizer_check->close();

if (!$has_organizer_access) {
    header("Location: ../student_dashboard.php");
    exit();
}

/* ==========================================
   VARIABLES
========================================== */

$message = "";
$message_type = "";

$edit_mode = false;
$event = null;

$event_id =
    isset($_GET["event_id"])
        ? (int)$_GET["event_id"]
        : 0;

/* ==========================================
   CORRECTION MODE
========================================== */

if ($event_id > 0) {

    $stmt = $conn->prepare(
        "SELECT *
         FROM events
         WHERE event_id = ?
           AND organizer_id = ?
           AND request_status = 'correction_required'"
    );

    $stmt->bind_param(
        "ii",
        $event_id,
        $organizer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $event = $result->fetch_assoc();
        $edit_mode = true;

        if (
            $organizer_request_id === null &&
            !empty($event["organizer_request_id"])
        ) {
            $organizer_request_id =
                (int)$event["organizer_request_id"];
        }

    } else {

        $message =
            "This event cannot be edited.";

        $message_type = "error";
    }

    $stmt->close();
}

/* ==========================================
   LOAD VENUES
========================================== */

$venues = [];

$venue_query = $conn->query(
    "SELECT DISTINCT location
     FROM stages
     WHERE location IS NOT NULL
       AND location <> ''
     ORDER BY location ASC"
);

if ($venue_query) {

    while ($row = $venue_query->fetch_assoc()) {
        $venues[] = $row["location"];
    }
}

/* ==========================================
   LOAD STAGES
========================================== */

$stages = [];

$stage_query = $conn->query(
    "SELECT
        stage_id,
        stage_name,
        location,
        capacity,
        status
     FROM stages
     ORDER BY location ASC, stage_name ASC"
);

if ($stage_query) {

    while ($row = $stage_query->fetch_assoc()) {
        $stages[] = $row;
    }
}

/* ==========================================
   LOAD EQUIPMENT

   Equipment is read from inventory.
   Assigned quantity comes from
   event_equipment_requirements.

   For correction mode, the current event's
   own requirement is added back so that the
   organizer can keep/change its quantity.
========================================== */

$equipment = [];

$equipment_query = $conn->query(
    "SELECT
        se.equipment_id,
        se.equipment_name,
        SUM(se.quantity) AS total_quantity,

        COALESCE(
            (
                SELECT SUM(eer.quantity)
                FROM event_equipment_requirements eer
                WHERE eer.equipment_id = se.equipment_id
                  AND eer.event_id <> " . (int)$event_id . "
            ),
            0
        ) AS assigned_quantity

     FROM stage_equipment se
     GROUP BY
        se.equipment_id,
        se.equipment_name

     ORDER BY se.equipment_name ASC"
);

if ($equipment_query) {

    while ($row = $equipment_query->fetch_assoc()) {

        $total =
            (int)$row["total_quantity"];

        $assigned =
            (int)$row["assigned_quantity"];

        $available =
            max(0, $total - $assigned);

        $equipment[] = [

            "id" =>
                (int)$row["equipment_id"],

            "name" =>
                $row["equipment_name"],

            "total" =>
                $total,

            "assigned" =>
                $assigned,

            "available" =>
                $available
        ];
    }
}

/* ==========================================
   EXISTING STAGE BOOKING
========================================== */

$existing_stage_booking = null;

if ($edit_mode && $event_id > 0) {

    $booking_check = $conn->prepare(
        "SELECT
            booking_id,
            stage_id,
            booking_date,
            start_time,
            end_time,
            booking_status
         FROM stage_bookings
         WHERE event_id = ?
         ORDER BY booking_id DESC
         LIMIT 1"
    );

    $booking_check->bind_param(
        "i",
        $event_id
    );

    $booking_check->execute();

    $booking_result =
        $booking_check->get_result();

    if ($booking_result->num_rows > 0) {

        $existing_stage_booking =
            $booking_result->fetch_assoc();
    }

    $booking_check->close();
}

/* ==========================================
   EXISTING EQUIPMENT REQUIREMENTS
   FOR CORRECTION MODE
========================================== */

$existing_equipment = [];

if ($edit_mode && $event_id > 0) {

    $existing_equipment_query = $conn->prepare(
        "SELECT
            equipment_id,
            quantity
         FROM event_equipment_requirements
         WHERE event_id = ?"
    );

    $existing_equipment_query->bind_param(
        "i",
        $event_id
    );

    $existing_equipment_query->execute();

    $existing_equipment_result =
        $existing_equipment_query->get_result();

    while (
        $row =
            $existing_equipment_result->fetch_assoc()
    ) {

        $existing_equipment[
            (int)$row["equipment_id"]
        ] =
            (int)$row["quantity"];
    }

    $existing_equipment_query->close();
}

/* ==========================================
   FORM SUBMISSION
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id_post =
        isset($_POST["event_id"])
            ? (int)$_POST["event_id"]
            : 0;

    $event_name =
        trim($_POST["event_name"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $objective =
        trim($_POST["objective"] ?? "");

    $event_date =
        $_POST["event_date"] ?? "";

    $start_time =
        $_POST["start_time"] ?? "";

    $end_time =
        $_POST["end_time"] ?? "";

    $venue =
        trim($_POST["venue"] ?? "");

    $stage_id =
        isset($_POST["stage_id"])
            ? (int)$_POST["stage_id"]
            : 0;

    $event_type =
        $_POST["event_type"] ?? "";

    $expected_participants =
        (int)($_POST["expected_participants"] ?? 0);

    $fee =
        ($event_type === "Paid")
            ? (float)($_POST["fee"] ?? 0)
            : 0;

    $guest_details =
        trim($_POST["guest_details"] ?? "");

    /* ==========================================
       SELECTED EQUIPMENT
    ========================================== */

    $selected_equipment =
        $_POST["equipment"] ?? [];

    if (!is_array($selected_equipment)) {
        $selected_equipment = [];
    }

    $equipment_quantities =
        $_POST["equipment_quantity"] ?? [];

    if (!is_array($equipment_quantities)) {
        $equipment_quantities = [];
    }

    $selected_equipment_data = [];
    $requirements_list = [];

    foreach ($selected_equipment as $equipment_id_value) {

        $equipment_id_value =
            (int)$equipment_id_value;

        if ($equipment_id_value <= 0) {
            continue;
        }

        $found_equipment = null;

        foreach ($equipment as $item) {

            if (
                (int)$item["id"]
                ===
                $equipment_id_value
            ) {

                $found_equipment = $item;
                break;
            }
        }

        if ($found_equipment === null) {
            continue;
        }

        $quantity =
            isset(
                $equipment_quantities[
                    $equipment_id_value
                ]
            )
                ? (int)$equipment_quantities[
                    $equipment_id_value
                ]
                : 0;

        if ($quantity < 1) {

            $message =
                "Please enter a valid quantity for "
                . $found_equipment["name"] . ".";

            $message_type = "error";
            break;
        }

        if (
            $quantity >
            $found_equipment["available"]
        ) {

            $message =
                $found_equipment["name"]
                . " has only "
                . $found_equipment["available"]
                . " available.";

            $message_type = "error";
            break;
        }

        $selected_equipment_data[] = [

            "equipment_id" =>
                $found_equipment["id"],

            "name" =>
                $found_equipment["name"],

            "quantity" =>
                $quantity
        ];

        $requirements_list[] =
            $found_equipment["name"]
            . ": "
            . $quantity;
    }

    $requirements =
        implode(
            ", ",
            $requirements_list
        );

    /* ==========================================
       BASIC VALIDATION
    ========================================== */

    if ($message === "" && $event_name === "") {

        $message =
            "Please enter the event name.";

        $message_type = "error";

    } elseif (
        $message === "" &&
        $venue === ""
    ) {

        $message =
            "Please select a venue.";

        $message_type = "error";

    } elseif (
        $message === "" &&
        $stage_id <= 0
    ) {

        $message =
            "Please select a stage.";

        $message_type = "error";

    } elseif (
        $message === "" &&
        $event_date === ""
    ) {

        $message =
            "Please select the event date.";

        $message_type = "error";

    } elseif (
        $message === "" &&
        ($start_time === "" || $end_time === "")
    ) {

        $message =
            "Please select both start time and end time.";

        $message_type = "error";

    } elseif (
        $message === "" &&
        $start_time >= $end_time
    ) {

        $message =
            "End time must be later than start time.";

        $message_type = "error";
    }

    /* ==========================================
       VERIFY STAGE
    ========================================== */

    if ($message === "") {

        $stage_check = $conn->prepare(
            "SELECT
                stage_id,
                stage_name,
                location
             FROM stages
             WHERE stage_id = ?
               AND location = ?
             LIMIT 1"
        );

        $stage_check->bind_param(
            "is",
            $stage_id,
            $venue
        );

        $stage_check->execute();

        $stage_result =
            $stage_check->get_result();

        if ($stage_result->num_rows === 0) {

            $message =
                "Invalid stage selected for this venue.";

            $message_type = "error";
        }

        $stage_check->close();
    }

    /* ==========================================
       STAGE AVAILABILITY
    ========================================== */

    if ($message === "") {

        $availability_sql =
            "SELECT booking_id
             FROM stage_bookings
             WHERE stage_id = ?
               AND booking_date = ?
               AND booking_status
                   IN ('assigned', 'booked')
               AND start_time < ?
               AND end_time > ?";

        if ($event_id_post > 0) {

            $availability_sql .=
                " AND event_id <> ?";
        }

        $availability_sql .=
            " LIMIT 1";

        $availability_check =
            $conn->prepare(
                $availability_sql
            );

        if ($event_id_post > 0) {

            $availability_check->bind_param(
                "isssi",
                $stage_id,
                $event_date,
                $end_time,
                $start_time,
                $event_id_post
            );

        } else {

            $availability_check->bind_param(
                "isss",
                $stage_id,
                $event_date,
                $end_time,
                $start_time
            );
        }

        $availability_check->execute();

        $availability_result =
            $availability_check->get_result();

        if ($availability_result->num_rows > 0) {

            $message =
                "This stage is already assigned or booked for the selected date and time.";

            $message_type = "error";
        }

        $availability_check->close();
    }

    /* ==========================================
       CORRECTION EVENT
    ========================================== */

    if (
        $message === "" &&
        $event_id_post > 0
    ) {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare(
                "UPDATE events
                 SET
                    event_name = ?,
                    description = ?,
                    event_date = ?,
                    start_time = ?,
                    end_time = ?,
                    venue = ?,
                    event_type = ?,
                    fee = ?,
                    objective = ?,
                    expected_participants = ?,
                    guest_details = ?,
                    requirements = ?,
                    request_status = 'pending',
                    correction_reason = NULL
                 WHERE event_id = ?
                   AND organizer_id = ?
                   AND request_status =
                       'correction_required'"
            );

            $stmt->bind_param(
                "sssssssdsissii",
                $event_name,
                $description,
                $event_date,
                $start_time,
                $end_time,
                $venue,
                $event_type,
                $fee,
                $objective,
                $expected_participants,
                $guest_details,
                $requirements,
                $event_id_post,
                $organizer_id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Event update failed."
                );
            }

            $stmt->close();

            /* Update stage booking */

            $booking_update = $conn->prepare(
                "UPDATE stage_bookings
                 SET
                    stage_id = ?,
                    booking_date = ?,
                    start_time = ?,
                    end_time = ?,
                    booking_status = 'pending',
                    confirmed_at = NULL
                 WHERE event_id = ?"
            );

            $booking_update->bind_param(
                "isssi",
                $stage_id,
                $event_date,
                $start_time,
                $end_time,
                $event_id_post
            );

            $booking_update->execute();

            if ($booking_update->affected_rows === 0) {

                $booking_insert = $conn->prepare(
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
                    VALUES
                    (?, ?, ?, ?, ?, 0, 'pending')"
                );

                $booking_insert->bind_param(
                    "iisss",
                    $stage_id,
                    $event_id_post,
                    $event_date,
                    $start_time,
                    $end_time
                );

                if (!$booking_insert->execute()) {

                    $booking_insert->close();

                    throw new Exception(
                        "Stage booking update failed."
                    );
                }

                $booking_insert->close();
            }

            $booking_update->close();

            /* ==========================================
               REPLACE EQUIPMENT REQUIREMENTS
            ========================================== */

            $delete_equipment =
                $conn->prepare(
                    "DELETE FROM event_equipment_requirements
                     WHERE event_id = ?"
                );

            $delete_equipment->bind_param(
                "i",
                $event_id_post
            );

            $delete_equipment->execute();
            $delete_equipment->close();

            foreach (
                $selected_equipment_data
                as $item
            ) {

                $insert_equipment =
                    $conn->prepare(
                        "INSERT INTO
                         event_equipment_requirements
                         (
                            event_id,
                            equipment_id,
                            quantity
                         )
                         VALUES (?, ?, ?)"
                    );

                $insert_equipment->bind_param(
                    "iii",
                    $event_id_post,
                    $item["equipment_id"],
                    $item["quantity"]
                );

                if (
                    !$insert_equipment->execute()
                ) {

                    $insert_equipment->close();

                    throw new Exception(
                        "Equipment requirement could not be saved."
                    );
                }

                $insert_equipment->close();
            }

            $conn->commit();

            $message =
                "Event updated and resubmitted successfully!";

            $message_type = "success";

            $edit_mode = false;

        } catch (Exception $e) {

            $conn->rollback();

            $message =
                $e->getMessage();

            $message_type = "error";
        }
    }

    /* ==========================================
       NEW EVENT
    ========================================== */

    elseif ($message === "") {

        if ($organizer_request_id === null) {

            $message =
                "No approved organizer team was found for your account.";

            $message_type = "error";

        } else {

            $conn->begin_transaction();

            try {

                $status = "pending";
                $request_status = "pending";

                /* Insert event */

                $stmt = $conn->prepare(
                    "INSERT INTO events
                    (
                        event_name,
                        description,
                        event_date,
                        start_time,
                        end_time,
                        venue,
                        event_type,
                        fee,
                        status,
                        organizer_id,
                        request_status,
                        objective,
                        expected_participants,
                        guest_details,
                        requirements,
                        organizer_request_id
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?
                    )"
                );

                $stmt->bind_param(
                    "sssssssdsisisssi",
                    $event_name,
                    $description,
                    $event_date,
                    $start_time,
                    $end_time,
                    $venue,
                    $event_type,
                    $fee,
                    $status,
                    $organizer_id,
                    $request_status,
                    $objective,
                    $expected_participants,
                    $guest_details,
                    $requirements,
                    $organizer_request_id
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        "Event could not be created."
                    );
                }

                $new_event_id =
                    $conn->insert_id;

                $stmt->close();

                /* ==========================================
                   CREATE STAGE BOOKING
                ========================================== */

                $booking_insert =
                    $conn->prepare(
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
                        VALUES
                        (?, ?, ?, ?, ?, 0, 'pending')"
                    );

                $booking_insert->bind_param(
                    "iisss",
                    $stage_id,
                    $new_event_id,
                    $event_date,
                    $start_time,
                    $end_time
                );

                if (
                    !$booking_insert->execute()
                ) {

                    $booking_insert->close();

                    throw new Exception(
                        "Stage booking could not be created."
                    );
                }

                $booking_insert->close();

                /* ==========================================
                   SAVE EQUIPMENT REQUIREMENTS
                ========================================== */

                foreach (
                    $selected_equipment_data
                    as $item
                ) {

                    $insert_equipment =
                        $conn->prepare(
                            "INSERT INTO
                             event_equipment_requirements
                             (
                                event_id,
                                equipment_id,
                                quantity
                             )
                             VALUES (?, ?, ?)"
                        );

                    $insert_equipment->bind_param(
                        "iii",
                        $new_event_id,
                        $item["equipment_id"],
                        $item["quantity"]
                    );

                    if (
                        !$insert_equipment->execute()
                    ) {

                        $insert_equipment->close();

                        throw new Exception(
                            "Equipment requirement could not be saved."
                        );
                    }

                    $insert_equipment->close();
                }

                $conn->commit();

                $message =
                    "Event request submitted successfully!";

                $message_type = "success";

            } catch (Exception $e) {

                $conn->rollback();

                $message =
                    $e->getMessage();

                $message_type = "error";
            }
        }
    }
}

$conn->close();

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
<?php
echo $edit_mode
    ? "Correct Event Request"
    : "Event Request";
?>
| Event Hub
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #071426;
    color: #ffffff;
}

.container {
    width: 90%;
    max-width: 900px;
    margin: 40px auto;
}

.back {
    color: #60a5fa;
    text-decoration: none;
    font-size: 15px;
}

h1 {
    margin-bottom: 8px;
}

.subtitle {
    color: #9ca3af;
    margin-bottom: 30px;
}

.correction-notice {
    background: #3b1d12;
    border: 1px solid #7c2d12;
    color: #fed7aa;
    padding: 18px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.correction-notice h3 {
    margin-top: 0;
    color: #fdba74;
}

.form-box {
    background: #0d1b2e;
    padding: 30px;
    border-radius: 15px;
    border: 1px solid #1e3a5f;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

input,
textarea,
select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #334155;
    background: #081525;
    color: white;
    font-size: 15px;
}

select option {
    background: #081525;
    color: white;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.info-box {
    background: #10243d;
    border: 1px solid #23466f;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    color: #bfdbfe;
}

.availability-box {
    margin-top: 8px;
    padding: 10px;
    border-radius: 7px;
    font-size: 14px;
}

.available {
    background: #064e3b;
    color: #a7f3d0;
}

.success {
    background: #064e3b;
    color: #a7f3d0;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #7f1d1d;
    color: #fecaca;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.submit-btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.submit-btn:hover {
    background: #1d4ed8;
}

.fee-box {
    display: none;
}

/* ==========================================
   EQUIPMENT DROPDOWN
========================================== */

.equipment-dropdown {
    position: relative;
    width: 100%;
}

.equipment-toggle {
    width: 100%;
    padding: 13px;
    background: #081525;
    color: #cbd5e1;
    border: 1px solid #334155;
    border-radius: 8px;
    text-align: left;
    cursor: pointer;
    font-size: 15px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.equipment-toggle:hover {
    border-color: #2563eb;
}

.equipment-panel {
    display: none;
    position: absolute;
    z-index: 50;
    top: calc(100% + 6px);
    left: 0;
    width: 100%;
    max-height: 420px;
    overflow-y: auto;
    background: #0d1b2e;
    border: 1px solid #334155;
    border-radius: 10px;
    padding: 10px;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.35);
}

.equipment-panel.open {
    display: block;
}

.equipment-item {
    border: 1px solid #263b55;
    border-radius: 9px;
    padding: 12px;
    margin-bottom: 8px;
    background: #0a1728;
}

.equipment-item:last-child {
    margin-bottom: 0;
}

.equipment-top {
    display: flex;
    align-items: center;
    gap: 10px;
}

.equipment-checkbox {
    width: 18px;
    height: 18px;
    accent-color: #2563eb;
    cursor: pointer;
}

.equipment-name {
    font-weight: bold;
    color: #e2e8f0;
    cursor: pointer;
    flex: 1;
}

.equipment-available {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 20px;
    background: #064e3b;
    color: #a7f3d0;
}

.equipment-unavailable {
    background: #7f1d1d;
    color: #fecaca;
}

.quantity-row {
    display: none;
    margin-top: 10px;
    padding-left: 28px;
}

.quantity-row.show {
    display: block;
}

.quantity-row label {
    font-size: 12px;
    color: #94a3b8;
    margin-bottom: 5px;
}

.quantity-input {
    width: 130px;
    padding: 8px 10px;
    font-size: 14px;
}

.quantity-input:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.selected-equipment {
    margin-top: 9px;
    color: #93c5fd;
    font-size: 13px;
}

.no-equipment {
    padding: 15px;
    text-align: center;
    color: #94a3b8;
}

.requirements-note {
    margin-top: 8px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}

@media (max-width: 650px) {

    .row {
        grid-template-columns: 1fr;
    }

    .form-box {
        padding: 20px;
    }
}

</style>

</head>

<body>

<div class="container">

<a
    href="approval_status.php"
    class="back"
>
    ← Back to Approval Status
</a>

<h1>

<?php if ($edit_mode): ?>

    ✏️ Correct Event Request

<?php else: ?>

    📋 Event Request

<?php endif; ?>

</h1>

<p class="subtitle">

<?php if ($edit_mode): ?>

    Update the requested details and resubmit your event for approval.

<?php else: ?>

    Submit a new event request for approval.

<?php endif; ?>

</p>

<?php if ($message !== ""): ?>

<div class="<?php echo $message_type; ?>">

    <?php
    echo htmlspecialchars($message);
    ?>

    <?php if ($message_type === "success"): ?>

        <br>

        <a
            href="approval_status.php"
            style="color:#93c5fd;"
        >
            View Approval Status →
        </a>

    <?php endif; ?>

</div>

<?php endif; ?>

<?php if ($edit_mode && $event): ?>

<div class="correction-notice">

    <h3>
        ⚠️ Correction Required
    </h3>

    <p>
        Admin has requested changes to this event.
        Please update the required details and resubmit.
    </p>

    <?php if (!empty($event["correction_reason"])): ?>

        <strong>
            Correction Reason:
        </strong>

        <p>
            <?php
            echo nl2br(
                htmlspecialchars(
                    $event["correction_reason"]
                )
            );
            ?>
        </p>

    <?php endif; ?>

</div>

<?php endif; ?>

<div class="form-box">

<form method="POST" action="">

<?php if ($edit_mode && $event): ?>

<input
    type="hidden"
    name="event_id"
    value="<?php
        echo (int)$event["event_id"];
    ?>"
>

<?php endif; ?>

<div class="form-group">

<label for="event_name">
    Event Name
</label>

<input
    type="text"
    id="event_name"
    name="event_name"
    placeholder="Enter event name"
    value="<?php
        echo htmlspecialchars(
            $event["event_name"] ?? ""
        );
    ?>"
    required
>

</div>

<div class="form-group">

<label for="description">
    Description
</label>

<textarea
    id="description"
    name="description"
    placeholder="Describe the event"
    required
><?php
echo htmlspecialchars(
    $event["description"] ?? ""
);
?></textarea>

</div>

<div class="form-group">

<label for="objective">
    Objective
</label>

<textarea
    id="objective"
    name="objective"
    placeholder="What is the objective of this event?"
    required
><?php
echo htmlspecialchars(
    $event["objective"] ?? ""
);
?></textarea>

</div>

<div class="row">

<div class="form-group">

<label for="event_date">
    Event Date
</label>

<input
    type="date"
    id="event_date"
    name="event_date"
    value="<?php
        echo htmlspecialchars(
            $event["event_date"] ?? ""
        );
    ?>"
    required
>

</div>

<div class="form-group">

<label for="expected_participants">
    Expected Participants
</label>

<input
    type="number"
    id="expected_participants"
    name="expected_participants"
    min="1"
    placeholder="Example: 100"
    value="<?php
        echo htmlspecialchars(
            $event["expected_participants"] ?? ""
        );
    ?>"
    required
>

</div>

</div>

<div class="row">

<div class="form-group">

<label for="venue">
    Venue
</label>

<select
    name="venue"
    id="venue"
    required
>

<option value="">
    Select Venue
</option>

<?php foreach ($venues as $venue_name): ?>

<option
    value="<?php
        echo htmlspecialchars($venue_name);
    ?>"
    <?php
    echo (
        ($event["venue"] ?? "")
        === $venue_name
    )
        ? "selected"
        : "";
    ?>
>

<?php
echo htmlspecialchars($venue_name);
?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group">

<label for="stage_id">
    Stage
</label>

<select
    name="stage_id"
    id="stage_id"
    required
>

<option value="">
    Select Stage
</option>

<?php foreach ($stages as $stage): ?>

<option
    value="<?php
        echo (int)$stage["stage_id"];
    ?>"
    data-venue="<?php
        echo htmlspecialchars(
            $stage["location"]
        );
    ?>"
    <?php

    echo (
        (
            $existing_stage_booking["stage_id"]
            ?? 0
        )
        ==
        $stage["stage_id"]
    )
        ? "selected"
        : "";

    ?>
>

<?php
echo htmlspecialchars(
    $stage["stage_name"]
);
?>

 -
<?php
echo htmlspecialchars(
    $stage["location"]
);
?>

</option>

<?php endforeach; ?>

</select>

<div
    id="stageInfo"
    class="availability-box"
    style="display:none;"
></div>

</div>

</div>

<div class="info-box">

    🏟️ <strong>Stage Booking Status</strong>

    <br><br>

    🟢 Available — stage is free for the selected time.

    <br>

    🟠 Assigned — Department Admin/HOD has approved the event.

    <br>

    🔴 Booked — Core Admin/PIMC has given final approval.

</div>

<div class="row">

<div class="form-group">

<label for="start_time">
    Start Time
</label>

<input
    type="time"
    id="start_time"
    name="start_time"
    value="<?php
        echo htmlspecialchars(
            $event["start_time"] ?? ""
        );
    ?>"
    required
>

</div>

<div class="form-group">

<label for="end_time">
    End Time
</label>

<input
    type="time"
    id="end_time"
    name="end_time"
    value="<?php
        echo htmlspecialchars(
            $event["end_time"] ?? ""
        );
    ?>"
    required
>

</div>

</div>

<div class="form-group">

<label for="event_type">
    Event Type
</label>

<select
    name="event_type"
    id="event_type"
    required
>

<option value="">
    Select Event Type
</option>

<option
    value="Free"
    <?php
    echo (
        ($event["event_type"] ?? "")
        === "Free"
    )
        ? "selected"
        : "";
    ?>
>
    Free Event
</option>

<option
    value="Paid"
    <?php
    echo (
        ($event["event_type"] ?? "")
        === "Paid"
    )
        ? "selected"
        : "";
    ?>
>
    Paid Event
</option>

</select>

</div>

<div
    class="form-group fee-box"
    id="feeBox"
>

<label for="fee">
    Registration Fee (₹)
</label>

<input
    type="number"
    name="fee"
    id="fee"
    min="0"
    step="0.01"
    placeholder="Enter registration fee"
    value="<?php
        echo htmlspecialchars(
            $event["fee"] ?? ""
        );
    ?>"
>

</div>

<div class="form-group">

<label for="guest_details">
    Chief Guest / Special Guest
</label>

<input
    type="text"
    id="guest_details"
    name="guest_details"
    placeholder="Guest name and designation"
    value="<?php
        echo htmlspecialchars(
            $event["guest_details"] ?? ""
        );
    ?>"
>

</div>

<!-- ==========================================
     EQUIPMENT REQUIREMENTS
========================================== -->

<div class="form-group">

<label>
    Technical & Infrastructure Requirements
</label>

<div class="equipment-dropdown">

<button
    type="button"
    class="equipment-toggle"
    id="equipmentToggle"
>

<span id="equipmentToggleText">
    Select required equipment
</span>

<span>▼</span>

</button>

<div
    class="equipment-panel"
    id="equipmentPanel"
>

<?php if (count($equipment) > 0): ?>

<?php foreach ($equipment as $item): ?>

<?php

$equipment_id =
    (int)$item["id"];

$existing_quantity =
    $existing_equipment[
        $equipment_id
    ]
    ?? 0;

$is_selected =
    $existing_quantity > 0;

?>

<div class="equipment-item">

<div class="equipment-top">

<input
    type="checkbox"
    class="equipment-checkbox"
    name="equipment[]"
    value="<?php
        echo $equipment_id;
    ?>"
    id="<?php
        echo 'equipment_' . $equipment_id;
    ?>"
    <?php
    echo $is_selected
        ? "checked"
        : "";
    ?>
>

<label
    class="equipment-name"
    for="<?php
        echo 'equipment_' . $equipment_id;
    ?>"
>

<?php

if (
    stripos(
        $item["name"],
        "microphone"
    ) !== false
) {
    echo "🎤 ";

} elseif (
    stripos(
        $item["name"],
        "sound"
    ) !== false
) {
    echo "🔊 ";

} elseif (
    stripos(
        $item["name"],
        "light"
    ) !== false
) {
    echo "💡 ";

} elseif (
    stripos(
        $item["name"],
        "projector"
    ) !== false
) {
    echo "📽️ ";

} elseif (
    stripos(
        $item["name"],
        "camera"
    ) !== false
) {
    echo "📷 ";

} elseif (
    stripos(
        $item["name"],
        "video"
    ) !== false
) {
    echo "🎥 ";

} else {
    echo "🔧 ";
}

echo htmlspecialchars(
    $item["name"]
);

?>

</label>

<span class="equipment-available">

<?php
echo $item["available"];
?>

available

</span>

</div>

<div
    class="quantity-row
    <?php
    echo $is_selected
        ? "show"
        : "";
    ?>"
>

<label>
    Required Quantity
</label>

<input
    type="number"
    class="quantity-input"
    name="equipment_quantity[<?php
        echo $equipment_id;
    ?>]"
    min="1"
    max="<?php
        echo $item["available"];
    ?>"
    value="<?php
        echo $existing_quantity > 0
            ? $existing_quantity
            : "";
    ?>"
    <?php
    echo $is_selected
        ? ""
        : "disabled";
    ?>
>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="no-equipment">
    No equipment has been added to the stage inventory yet.
</div>

<?php endif; ?>

</div>

</div>

<div
    class="selected-equipment"
    id="selectedEquipment"
>
    No equipment selected.
</div>

<div class="requirements-note">

Select only the equipment required for this event.
The quantity will be reserved against this event
after the request is submitted.

</div>

</div>

<button
    type="submit"
    class="submit-btn"
>

<?php if ($edit_mode): ?>

    🔄 Resubmit Corrected Request

<?php else: ?>

    Submit Event Request

<?php endif; ?>

</button>

</form>

</div>

</div>

<script>

/* ==========================================
   VENUE + STAGE
========================================== */

const venueSelect =
    document.getElementById("venue");

const stageSelect =
    document.getElementById("stage_id");

const stageOptions =
    Array.from(stageSelect.options);

function filterStages() {

    const selectedVenue =
        venueSelect.value;

    const currentStage =
        stageSelect.value;

    stageSelect.innerHTML = "";

    const defaultOption =
        document.createElement("option");

    defaultOption.value = "";
    defaultOption.textContent = "Select Stage";

    stageSelect.appendChild(
        defaultOption
    );

    stageOptions.forEach(
        function(option) {

            if (!option.value) {
                return;
            }

            if (
                option.dataset.venue ===
                selectedVenue
            ) {

                stageSelect.appendChild(
                    option.cloneNode(true)
                );
            }
        }
    );

    if (currentStage !== "") {
        stageSelect.value = currentStage;
    }

    updateStageInfo();
}

venueSelect.addEventListener(
    "change",
    filterStages
);

/* ==========================================
   STAGE INFO
========================================== */

function updateStageInfo() {

    const selectedOption =
        stageSelect.options[
            stageSelect.selectedIndex
        ];

    const stageInfo =
        document.getElementById(
            "stageInfo"
        );

    if (
        !selectedOption ||
        !selectedOption.value
    ) {

        stageInfo.style.display = "none";
        stageInfo.textContent = "";

        return;
    }

    stageInfo.style.display = "block";
    stageInfo.className =
        "availability-box available";

    stageInfo.textContent =
        "🟢 Stage selected. Final availability will be checked when the request is submitted.";
}

stageSelect.addEventListener(
    "change",
    updateStageInfo
);

const existingStageId =
    "<?php
    echo
        $existing_stage_booking["stage_id"]
        ?? "";
    ?>";

if (venueSelect.value !== "") {

    filterStages();

    if (existingStageId !== "") {

        stageSelect.value =
            existingStageId;

        updateStageInfo();
    }
}

/* ==========================================
   FEE
========================================== */

const eventType =
    document.getElementById(
        "event_type"
    );

const feeBox =
    document.getElementById(
        "feeBox"
    );

const fee =
    document.getElementById(
        "fee"
    );

function updateFeeBox() {

    if (
        eventType.value === "Paid"
    ) {

        feeBox.style.display = "block";
        fee.required = true;

    } else {

        feeBox.style.display = "none";
        fee.required = false;
    }
}

eventType.addEventListener(
    "change",
    updateFeeBox
);

updateFeeBox();

/* ==========================================
   EQUIPMENT DROPDOWN
========================================== */

const equipmentToggle =
    document.getElementById(
        "equipmentToggle"
    );

const equipmentPanel =
    document.getElementById(
        "equipmentPanel"
    );

const equipmentToggleText =
    document.getElementById(
        "equipmentToggleText"
    );

const selectedEquipment =
    document.getElementById(
        "selectedEquipment"
    );

equipmentToggle.addEventListener(
    "click",
    function(event) {

        event.stopPropagation();

        equipmentPanel.classList.toggle(
            "open"
        );
    }
);

document.addEventListener(
    "click",
    function(event) {

        if (
            !event.target.closest(
                ".equipment-dropdown"
            )
        ) {

            equipmentPanel.classList.remove(
                "open"
            );
        }
    }
);

/* ==========================================
   EQUIPMENT CHECKBOXES
========================================== */

const equipmentCheckboxes =
    document.querySelectorAll(
        ".equipment-checkbox"
    );

function updateEquipmentSelection() {

    const selectedNames = [];

    equipmentCheckboxes.forEach(
        function(checkbox) {

            const item =
                checkbox.closest(
                    ".equipment-item"
                );

            const quantityRow =
                item.querySelector(
                    ".quantity-row"
                );

            const quantityInput =
                item.querySelector(
                    ".quantity-input"
                );

            const name =
                checkbox
                    .closest(".equipment-item")
                    .querySelector(
                        ".equipment-name"
                    )
                    .textContent
                    .trim();

            if (checkbox.checked) {

                quantityRow.classList.add(
                    "show"
                );

                quantityInput.disabled =
                    false;

                if (
                    quantityInput.value === ""
                ) {

                    quantityInput.value = 1;
                }

                selectedNames.push(
                    name
                );

            } else {

                quantityRow.classList.remove(
                    "show"
                );

                quantityInput.disabled =
                    true;

                quantityInput.value = "";
            }
        }
    );

    if (
        selectedNames.length === 0
    ) {

        equipmentToggleText.textContent =
            "Select required equipment";

        selectedEquipment.textContent =
            "No equipment selected.";

    } else {

        equipmentToggleText.textContent =
            selectedNames.length
            + " equipment item"
            + (
                selectedNames.length > 1
                    ? "s"
                    : ""
            )
            + " selected";

        selectedEquipment.textContent =
            selectedNames.join(", ");
    }
}

equipmentCheckboxes.forEach(
    function(checkbox) {

        checkbox.addEventListener(
            "change",
            updateEquipmentSelection
        );
    }
);

/* ==========================================
   QUANTITY VALIDATION
========================================== */

const quantityInputs =
    document.querySelectorAll(
        ".quantity-input"
    );

quantityInputs.forEach(
    function(input) {

        input.addEventListener(
            "input",
            function() {

                const max =
                    parseInt(
                        input.max
                    );

                let value =
                    parseInt(
                        input.value
                    );

                if (
                    value > max
                ) {

                    input.value = max;

                    alert(
                        "Only "
                        + max
                        + " quantity is available."
                    );
                }

                if (
                    value < 1 &&
                    input.value !== ""
                ) {

                    input.value = 1;
                }
            }
        );
    }
);

/* ==========================================
   INITIAL STATE
========================================== */

updateEquipmentSelection();

</script>

</body>

</html>