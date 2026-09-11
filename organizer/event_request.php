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


$organizer_id = $_SESSION["user_id"];


/* ==========================================
   CHECK ORGANIZER ACCESS
   - Global organizer role
   - OR approved organizer team member
========================================== */

$has_organizer_access = false;
$organizer_request_id = null;


/* Existing organizer role */

if (isset($_SESSION["role"]) && $_SESSION["role"] === "organizer") {

    $has_organizer_access = true;

}


/* Approved student organizer */

if (!$has_organizer_access) {

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

}


/* If no organizer access */

if (!$has_organizer_access) {

    header("Location: ../student_dashboard.php");
    exit();

}


/* If global organizer role, try to find approved team */

if ($organizer_request_id === null) {

    $request_check = $conn->prepare(
        "SELECT tm.request_id
         FROM organizer_team_members tm
         INNER JOIN organizer_requests r
             ON tm.request_id = r.request_id
         WHERE tm.user_id = ?
           AND r.status = 'approved'
         ORDER BY tm.request_id ASC
         LIMIT 1"
    );

    $request_check->bind_param("i", $organizer_id);
    $request_check->execute();

    $request_result = $request_check->get_result();

    if ($request_result->num_rows > 0) {

        $request_row = $request_result->fetch_assoc();

        $organizer_request_id =
            (int)$request_row["request_id"];

    }

    $request_check->close();

}


$message = "";
$message_type = "";

$edit_mode = false;
$event = null;


/* ==========================================
   CHECK EDIT / CORRECTION MODE
========================================== */

$event_id =
    isset($_GET["event_id"])
        ? (int)$_GET["event_id"]
        : 0;


if ($event_id > 0) {

    /*
     * Event can be corrected only by its
     * own organizer.
     */

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


        /*
         * Keep the existing team/event link.
         */

        if (
            $organizer_request_id === null
            &&
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


    $event_type =
        $_POST["event_type"] ?? "";


    $expected_participants =
        (int)(
            $_POST["expected_participants"] ?? 0
        );


    $fee =
        ($event_type === "Paid")
            ? (float)($_POST["fee"] ?? 0)
            : 0;


    $guest_details =
        trim($_POST["guest_details"] ?? "");


    $requirements =
        trim($_POST["requirements"] ?? "");


    /* ==========================================
       CORRECTION EVENT
       UPDATE & RESUBMIT
    ========================================== */

    if ($event_id_post > 0) {


        $stmt = $conn->prepare(
            "UPDATE events
             SET
                event_name = ?,
                description = ?,
                event_date = ?,
                start_time = ?,
                end_time = ?,
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
             AND request_status = 'correction_required'"
        );


        $stmt->bind_param(
            "ssssssdsissii",
            $event_name,
            $description,
            $event_date,
            $start_time,
            $end_time,
            $event_type,
            $fee,
            $objective,
            $expected_participants,
            $guest_details,
            $requirements,
            $event_id_post,
            $organizer_id
        );


        if ($stmt->execute()) {

            $message =
                "Event updated and resubmitted successfully!";

            $message_type = "success";

            $edit_mode = false;

        } else {

            $message =
                "Something went wrong: "
                . $stmt->error;

            $message_type = "error";

        }


        $stmt->close();

    }


    /* ==========================================
       NEW EVENT REQUEST
    ========================================== */

    else {


        $venue = "Not Assigned";

        $status = "pending";

        $request_status = "pending";


        /*
         * New event must be linked to the
         * approved organizing team.
         */

        if ($organizer_request_id === null) {

            $message =
                "No approved organizer team was found for your account.";

            $message_type = "error";

        } else {


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
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )"
            );


            $stmt->bind_param(
                "sssssssdsississi",
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


            if ($stmt->execute()) {

                $message =
                    "Event request submitted successfully!";

                $message_type = "success";

            } else {

                $message =
                    "Something went wrong: "
                    . $stmt->error;

                $message_type = "error";

            }


            $stmt->close();

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

textarea {
    min-height: 100px;
    resize: vertical;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
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

.success-link {
    display: inline-block;
    margin-top: 10px;
    color: #93c5fd;
    text-decoration: none;
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


<a href="approval_status.php" class="back">
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

    <?php echo htmlspecialchars($message); ?>

    <?php if ($message_type === "success"): ?>

        <br>

        <a
            href="approval_status.php"
            class="success-link"
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
        echo $event["event_id"];
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

    if (
        ($event["event_type"] ?? "")
        === "Free"
    ) {

        echo "selected";

    }

    ?>
>
    Free Event
</option>

<option
    value="Paid"
    <?php

    if (
        ($event["event_type"] ?? "")
        === "Paid"
    ) {

        echo "selected";

    }

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



<div class="form-group">

<label for="requirements">
    Requirements
</label>

<textarea
    id="requirements"
    name="requirements"
    placeholder="Mic, projector, lights, sound system, chairs, etc."
><?php

echo htmlspecialchars(
    $event["requirements"] ?? ""
);

?></textarea>

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

const eventType =
    document.getElementById("event_type");

const feeBox =
    document.getElementById("feeBox");

const fee =
    document.getElementById("fee");


function updateFeeBox() {

    if (eventType.value === "Paid") {

        feeBox.style.display = "block";

        fee.required = true;

    } else {

        feeBox.style.display = "none";

        fee.required = false;

        if (
            !<?php
            echo $edit_mode
                ? "true"
                : "false";
            ?>
        ) {

            fee.value = "";

        }

    }

}


eventType.addEventListener(
    "change",
    updateFeeBox
);

updateFeeBox();

</script>


</body>

</html>