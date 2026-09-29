<?php
session_start();
require_once "../db.php";

/* =========================================================
   ACCESS CONTROL
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$user_role = $_SESSION["role"] ?? "";

$allowed_roles = [
    "organizer",
    "student_organizer",
    "admin",
    "core_admin"
];

if (!in_array($user_role, $allowed_roles)) {
    die("Access denied.");
}


/* =========================================================
   GET EVENT ID
========================================================= */

$event_id = isset($_GET["event_id"])
    ? intval($_GET["event_id"])
    : 0;

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
        description,
        event_date,
        start_time,
        end_time,
        venue,
        event_type,
        fee,
        status,
        request_status,
        publication_status,
        organizer_id,
        cancellation_reason,
        cancellation_requested_at
    FROM events
    WHERE event_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $event_id);
$stmt->execute();

$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    die("Event not found.");
}


/* =========================================================
   CHECK WHETHER EVENT IS PUBLICLY PUBLISHED
========================================================= */

$is_published =
    strtolower($event["publication_status"] ?? "") === "published";


/* =========================================================
   PREVENT CANCELLATION OF ALREADY CANCELLED EVENT
========================================================= */

if (
    strtolower($event["status"] ?? "") === "cancelled"
) {
    die("This event has already been cancelled.");
}


/* =========================================================
   PREVENT DUPLICATE CANCELLATION REQUEST
========================================================= */

if (
    strtolower($event["status"] ?? "") ===
    "cancellation_requested"
) {
    die("A cancellation request for this event is already pending.");
}


/* =========================================================
   CANCELLATION SHOULD ONLY BE FOR PUBLISHED EVENTS
========================================================= */

if (!$is_published) {
    die(
        "Only publicly published events can be submitted for cancellation."
    );
}


/* =========================================================
   VARIABLES
========================================================= */

$error = "";
$success = "";

$reason = "";


/* =========================================================
   FORM SUBMISSION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $reason = trim(
        $_POST["cancellation_reason"] ?? ""
    );


    /* -----------------------------------------------------
       VALIDATION
    ----------------------------------------------------- */

    if (empty($reason)) {

        $error =
            "Please enter the reason for cancelling the event.";

    } elseif (strlen($reason) < 10) {

        $error =
            "Please provide a proper cancellation reason.";

    } else {

        try {

            $conn->begin_transaction();


            /* ---------------------------------------------
               UPDATE EVENT
            --------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE events
                SET
                    status = 'cancellation_requested',
                    cancellation_reason = ?,
                    cancellation_requested_at = CURRENT_TIMESTAMP
                WHERE event_id = ?
                  AND publication_status = 'published'
                  AND status <> 'cancelled'
                  AND status <> 'cancellation_requested'
            ");

            $stmt->bind_param(
                "si",
                $reason,
                $event_id
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to submit cancellation request."
                );
            }


            /* ---------------------------------------------
               CHECK WHETHER EVENT WAS ACTUALLY UPDATED
            --------------------------------------------- */

            if ($stmt->affected_rows !== 1) {

                throw new Exception(
                    "This event cannot be submitted for cancellation."
                );
            }


            /* ---------------------------------------------
               COMMIT
            --------------------------------------------- */

            $conn->commit();


            $success =
                "Cancellation request submitted successfully. " .
                "It is now waiting for management approval.";

            /*
               Refresh event information
            */

            $event["status"] =
                "cancellation_requested";

            $event["cancellation_reason"] =
                $reason;

            $event["cancellation_requested_at"] =
                date("Y-m-d H:i:s");


        } catch (Exception $e) {

            $conn->rollback();

            $error =
                "Unable to submit cancellation request: " .
                $e->getMessage();
        }
    }
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

    <title>Cancel Event</title>


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
            max-width: 850px;
            margin: 40px auto;
        }


        .card {
            background: white;
            border-radius: 18px;
            padding: 30px;
            box-shadow:
                0 8px 25px
                rgba(80, 50, 120, 0.12);
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
            padding: 20px;
            margin-bottom: 25px;
        }


        .event-info p {
            margin: 9px 0;
        }


        .published-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            background: #e6fff0;
            color: #17763e;
            font-size: 13px;
            font-weight: bold;
        }


        .warning {
            background: #fff5df;
            border-left: 5px solid #e5a72b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.6;
        }


        .warning strong {
            color: #8a5c00;
        }


        .danger-box {
            background: #fff0f0;
            border-left: 5px solid #d33;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.6;
        }


        .danger-box strong {
            color: #b32626;
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


        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }


        .form-group {
            margin-bottom: 22px;
        }


        textarea {
            width: 100%;
            min-height: 140px;
            padding: 13px 14px;
            border: 1px solid #d6c9e8;
            border-radius: 10px;
            font-size: 15px;
            font-family: Arial, sans-serif;
            resize: vertical;
        }


        textarea:focus {
            outline: none;
            border-color: #7650a8;
            box-shadow:
                0 0 0 3px
                rgba(118, 80, 168, 0.12);
        }


        .help-text {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: #75677f;
            line-height: 1.5;
        }


        .checklist {
            background: #f8f5fc;
            padding: 18px;
            border-radius: 12px;
            margin-top: 20px;
        }


        .checklist div {
            margin: 9px 0;
        }


        .submit-btn {
            width: 100%;
            border: none;
            padding: 14px;
            margin-top: 25px;
            border-radius: 10px;
            background: #b32626;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }


        .submit-btn:hover {
            background: #8f1e1e;
        }


        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: #633f91;
            text-decoration: none;
            font-weight: bold;
        }


        .back-link:hover {
            text-decoration: underline;
        }


        .required {
            color: red;
        }


        @media (max-width: 650px) {

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

        <h1>Cancel Published Event</h1>


        <!-- =============================================
             EVENT INFORMATION
        ============================================== -->

        <div class="event-info">

            <p>
                <strong>Event:</strong>
                <?= htmlspecialchars(
                    $event["event_name"]
                ) ?>
            </p>


            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars(
                    $event["event_date"]
                ) ?>
            </p>


            <p>
                <strong>Time:</strong>
                <?= htmlspecialchars(
                    $event["start_time"]
                ) ?>

                -

                <?= htmlspecialchars(
                    $event["end_time"]
                ) ?>
            </p>


            <p>
                <strong>Venue:</strong>
                <?= htmlspecialchars(
                    $event["venue"]
                ) ?>
            </p>


            <?php if (!empty($event["event_type"])): ?>

                <p>
                    <strong>Event Type:</strong>
                    <?= htmlspecialchars(
                        $event["event_type"]
                    ) ?>
                </p>

            <?php endif; ?>


            <span class="published-badge">
                ✓ Publicly Published
            </span>

        </div>


        <!-- =============================================
             WARNING
        ============================================== -->

        <div class="warning">

            <strong>Important:</strong>

            Submitting this form will

            <strong>
                NOT immediately cancel the event.
            </strong>

            It will send a cancellation request to
            management for review.

        </div>


        <div class="danger-box">

            <strong>Cancellation process:</strong>

            <br><br>

            Organizer submits request
            → Management reviews
            → If approved, event becomes
            <strong>Cancelled</strong>
            → Stage/venue booking can then be released.

        </div>


        <!-- =============================================
             MESSAGES
        ============================================== -->

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


        <?php if (
            $event["status"] !==
            "cancellation_requested"
        ): ?>


            <!-- =========================================
                 FORM
            ========================================== -->

            <form
                method="POST"
                id="cancelForm"
            >


                <h2>Cancellation Request</h2>


                <div class="form-group">

                    <label for="cancellation_reason">

                        Reason for Cancellation

                        <span class="required">*</span>

                    </label>


                    <textarea
                        name="cancellation_reason"
                        id="cancellation_reason"
                        placeholder="Enter the reason for cancelling this event..."
                        required
                    ><?= htmlspecialchars($reason) ?></textarea>


                    <span class="help-text">

                        Please provide a clear reason.
                        This reason will be visible to
                        management during the review.

                    </span>

                </div>


                <!-- =====================================
                     CHECKLIST
                ====================================== -->

                <div class="checklist">

                    <strong>
                        Before submitting:
                    </strong>


                    <div>
                        ✓ This is the correct event
                    </div>


                    <div>
                        ✓ The event is currently publicly published
                    </div>


                    <div>
                        ✓ A valid cancellation reason is provided
                    </div>


                    <div>
                        ✓ I understand that cancellation
                        requires management approval
                    </div>

                </div>


                <button
                    type="submit"
                    class="submit-btn"
                >
                    Submit Cancellation Request
                </button>

            </form>


        <?php else: ?>


            <!-- =========================================
                 PENDING MESSAGE
            ========================================== -->

            <div class="success">

                <strong>
                    Cancellation Request Pending
                </strong>

                <br><br>

                This event has already been submitted
                for cancellation review.

                <br><br>

                <strong>Reason:</strong>

                <?= htmlspecialchars(
                    $event["cancellation_reason"] ?? ""
                ) ?>

            </div>

        <?php endif; ?>


        <a
            href="javascript:history.back()"
            class="back-link"
        >
            ← Go Back
        </a>

    </div>

</div>


<script>

/* =========================================================
   FORM CONFIRMATION
========================================================= */

const cancelForm =
    document.getElementById("cancelForm");


if (cancelForm) {

    cancelForm.addEventListener(
        "submit",
        function(event) {

            const reason =
                document
                    .getElementById(
                        "cancellation_reason"
                    )
                    .value
                    .trim();


            if (reason.length < 10) {

                event.preventDefault();

                alert(
                    "Please provide a proper cancellation reason."
                );

                return;
            }


            const confirmed =
                confirm(
                    "Are you sure you want to submit a cancellation request for this published event?\n\n" +
                    "The event will NOT be cancelled immediately. " +
                    "Management approval is required."
                );


            if (!confirmed) {

                event.preventDefault();

            }

        }
    );

}

</script>

</body>

</html>