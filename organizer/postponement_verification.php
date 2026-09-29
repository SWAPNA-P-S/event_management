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

$user_id = $_SESSION["user_id"];


/* ==========================================
   ORGANIZER ACCESS CHECK
========================================== */

$has_organizer_access = false;

if (
    isset($_SESSION["role"])
    &&
    $_SESSION["role"] === "organizer"
) {

    $has_organizer_access = true;

}


if (!$has_organizer_access) {

    $access_check = $conn->prepare(
        "SELECT tm.team_member_id
         FROM organizer_team_members tm
         INNER JOIN organizer_requests r
             ON tm.request_id = r.request_id
         WHERE tm.user_id = ?
         AND r.status = 'approved'
         LIMIT 1"
    );

    $access_check->bind_param("i", $user_id);

    $access_check->execute();

    $access_result = $access_check->get_result();

    if ($access_result->num_rows > 0) {

        $has_organizer_access = true;

        $_SESSION["is_organizer"] = true;

    }

    $access_check->close();

}


if (!$has_organizer_access) {

    header("Location: ../student_dashboard.php");
    exit();

}


/* ==========================================
   EVENT ID
========================================== */

if (
    !isset($_GET["event_id"])
    ||
    !is_numeric($_GET["event_id"])
) {

    die("Invalid event ID.");

}

$event_id = intval($_GET["event_id"]);


/* ==========================================
   FETCH EVENT
========================================== */

if (
    isset($_SESSION["role"])
    &&
    $_SESSION["role"] === "organizer"
) {

    $stmt = $conn->prepare(
        "SELECT
            event_id,
            event_name,
            event_date,
            start_time,
            end_time,
            venue,
            requirements,
            request_status,
            status,
            postponement_reason,
            postponed_from_date,
            postponement_status
         FROM events
         WHERE event_id = ?
         AND organizer_id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "ii",
        $event_id,
        $user_id
    );

} else {

    $stmt = $conn->prepare(
        "SELECT DISTINCT
            e.event_id,
            e.event_name,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.requirements,
            e.request_status,
            e.status,
            e.postponement_reason,
            e.postponed_from_date,
            e.postponement_status
         FROM events e
         INNER JOIN organizer_team_members tm
             ON e.organizer_request_id = tm.request_id
         INNER JOIN organizer_requests r
             ON tm.request_id = r.request_id
         WHERE e.event_id = ?
         AND tm.user_id = ?
         AND r.status = 'approved'
         LIMIT 1"
    );

    $stmt->bind_param(
        "ii",
        $event_id,
        $user_id
    );

}


$stmt->execute();

$result = $stmt->get_result();

$event = $result->fetch_assoc();

$stmt->close();


if (!$event) {

    die("Event not found or access denied.");

}


/* ==========================================
   POSTPONEMENT STATUS
========================================== */

$postponement_status =
    $event["postponement_status"] ?? "none";


/* ==========================================
   HANDLE VERIFICATION
========================================== */

$message = "";
$message_type = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* ==========================================
       ONLY REQUESTED STATUS CAN BE VERIFIED
    ========================================== */

    if ($postponement_status !== "requested") {

        $message =
            "This postponement is not currently waiting for committee verification.";

        $message_type = "error";

    } else {


        $venue_verified =
            isset($_POST["venue_verified"]);

        $stage_verified =
            isset($_POST["stage_verified"]);

        $technical_verified =
            isset($_POST["technical_verified"]);

        $brochure_verified =
            isset($_POST["brochure_verified"]);

        $committee_verified =
            isset($_POST["committee_verified"]);


        /* ==========================================
           ALL CHECKS REQUIRED
        ========================================== */

        if (
            !$venue_verified
            ||
            !$stage_verified
            ||
            !$technical_verified
            ||
            !$brochure_verified
            ||
            !$committee_verified
        ) {

            $message =
                "Please complete all verification items before submitting.";

            $message_type = "error";

        } else {


            /* ==========================================
               MARK POSTPONEMENT AS VERIFIED
            ========================================== */

            $update = $conn->prepare(
                "UPDATE events
                 SET
                    postponement_status = 'verified',
                    request_status = 'pending',
                    status = 'pending',
                    publication_status = 'draft'
                 WHERE event_id = ?
                 AND postponement_status = 'requested'"
            );

            $update->bind_param(
                "i",
                $event_id
            );


            if ($update->execute()) {

                $message =
                    "Verification completed successfully. The postponed event has been submitted to Core Admin for final review.";

                $message_type = "success";

                $postponement_status = "verified";

            } else {

                $message =
                    "Unable to submit the verification.";

                $message_type = "error";

            }


            $update->close();

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

<title>
    Postponement Verification | Event Hub
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #071426;

    color: white;

}

.container {

    width: 92%;

    max-width: 900px;

    margin: 40px auto;

}

.back {

    color: #60a5fa;

    text-decoration: none;

    font-size: 15px;

}

h1 {

    margin-top: 25px;

    margin-bottom: 8px;

}

.subtitle {

    color: #9ca3af;

    line-height: 1.6;

    margin-bottom: 30px;

}


/* EVENT CARD */

.event-card {

    background: #0d1b2e;

    border: 1px solid #1e3a5f;

    border-radius: 15px;

    padding: 25px;

    margin-bottom: 25px;

}

.event-card h2 {

    margin-top: 0;

    color: #c4b5fd;

}

.details {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px;

}

.detail {

    background: #081525;

    padding: 15px;

    border-radius: 8px;

}

.label {

    color: #94a3b8;

    font-size: 13px;

    margin-bottom: 6px;

}

.value {

    color: white;

    font-size: 15px;

}


/* VERIFICATION */

.verify-card {

    background: #0d1b2e;

    border: 1px solid #1e3a5f;

    border-radius: 15px;

    padding: 25px;

}

.verify-card h2 {

    margin-top: 0;

    color: #e2e8f0;

}

.description {

    color: #cbd5e1;

    line-height: 1.6;

}


/* CHECKBOX */

.check-item {

    background: #081525;

    border: 1px solid #334155;

    border-radius: 10px;

    padding: 16px;

    margin-bottom: 12px;

    display: flex;

    align-items: flex-start;

    gap: 12px;

}

.check-item input {

    width: 18px;

    height: 18px;

    margin-top: 2px;

}

.check-content strong {

    display: block;

    margin-bottom: 5px;

    color: white;

}

.check-content span {

    color: #94a3b8;

    font-size: 13px;

    line-height: 1.5;

}


/* NOTICE */

.notice {

    background: #24163d;

    border: 1px solid #6d28d9;

    border-radius: 10px;

    padding: 18px;

    margin: 20px 0;

    color: #ddd6fe;

    line-height: 1.6;

}


/* ALERT */

.alert {

    padding: 15px;

    border-radius: 9px;

    margin-bottom: 20px;

    line-height: 1.5;

}

.alert-error {

    background: #3b1d12;

    border: 1px solid #7c2d12;

    color: #fdba74;

}

.alert-success {

    background: #064e3b;

    border: 1px solid #059669;

    color: #a7f3d0;

}


/* BUTTON */

.btn {

    display: inline-block;

    padding: 12px 20px;

    border: none;

    border-radius: 8px;

    font-size: 14px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;

}

.submit-btn {

    background: #7c3aed;

    color: white;

}

.submit-btn:hover {

    background: #6d28d9;

}

.back-btn {

    background: #334155;

    color: white;

    margin-left: 8px;

}


/* STATUS */

.status-box {

    background: #111c31;

    border: 1px solid #334155;

    border-radius: 10px;

    padding: 18px;

    margin-bottom: 20px;

}

.status-box strong {

    color: #c4b5fd;

}


/* RESPONSIVE */

@media (max-width: 700px) {

    .details {

        grid-template-columns: 1fr;

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
    🔍 Postponement Verification
</h1>


<p class="subtitle">

    Verify all changed arrangements before submitting
    the postponed event to Core Admin for final review.

</p>


<?php if (!empty($message)): ?>

    <div
        class="alert
        <?php
        echo $message_type === "success"
            ? "alert-success"
            : "alert-error";
        ?>"
    >

        <?php

        echo htmlspecialchars(
            $message
        );

        ?>

    </div>

<?php endif; ?>


<!-- EVENT DETAILS -->

<div class="event-card">

    <h2>

        <?php

        echo htmlspecialchars(
            $event["event_name"]
        );

        ?>

    </h2>


    <div class="details">


        <div class="detail">

            <div class="label">
                Previous Date
            </div>

            <div class="value">

                <?php

                if (
                    !empty(
                        $event["postponed_from_date"]
                    )
                ) {

                    echo htmlspecialchars(
                        date(
                            "d M Y",
                            strtotime(
                                $event[
                                    "postponed_from_date"
                                ]
                            )
                        )
                    );

                } else {

                    echo "Not available";

                }

                ?>

            </div>

        </div>


        <div class="detail">

            <div class="label">
                New Date
            </div>

            <div class="value">

                <?php

                echo htmlspecialchars(
                    date(
                        "d M Y",
                        strtotime(
                            $event["event_date"]
                        )
                    )
                );

                ?>

            </div>

        </div>


        <div class="detail">

            <div class="label">
                New Time
            </div>

            <div class="value">

                <?php

                echo htmlspecialchars(
                    date(
                        "h:i A",
                        strtotime(
                            $event["start_time"]
                        )
                    )
                );

                ?>

                -

                <?php

                echo htmlspecialchars(
                    date(
                        "h:i A",
                        strtotime(
                            $event["end_time"]
                        )
                    )
                );

                ?>

            </div>

        </div>


        <div class="detail">

            <div class="label">
                Venue
            </div>

            <div class="value">

                <?php

                echo htmlspecialchars(
                    $event["venue"]
                );

                ?>

            </div>

        </div>


        <div class="detail">

            <div class="label">
                Postponement Status
            </div>

            <div class="value">

                <?php

                if (
                    $postponement_status === "requested"
                ) {

                    echo "🟡 Waiting for Committee Verification";

                } elseif (
                    $postponement_status === "verified"
                ) {

                    echo "🟢 Committee Verified – Waiting for Core Admin";

                } elseif (
                    $postponement_status === "approved"
                ) {

                    echo "🟢 Approved by Core Admin";

                } else {

                    echo htmlspecialchars(
                        ucfirst(
                            $postponement_status
                        )
                    );

                }

                ?>

            </div>

        </div>


    </div>

</div>


<?php if (
    !empty(
        $event["postponement_reason"]
    )
): ?>

<div class="event-card">

    <h2>
        Reason for Postponement
    </h2>

    <p>

        <?php

        echo nl2br(
            htmlspecialchars(
                $event["postponement_reason"]
            )
        );

        ?>

    </p>

</div>

<?php endif; ?>


<!-- VERIFICATION CARD -->

<div class="verify-card">

    <h2>
        Organizing Committee Verification
    </h2>


    <p class="description">

        Before sending the postponed event to Core Admin,
        the organizing committee must confirm that all
        changed arrangements have been checked.

    </p>


    <div class="notice">

        <strong>
            Important:
        </strong>

        Only submit after confirming every item below.
        Core Admin will perform the final review after
        committee verification.

    </div>


    <?php if (
        $postponement_status === "requested"
    ): ?>


    <form
        method="POST"
        action=""
    >


        <!-- VENUE -->

        <label class="check-item">

            <input
                type="checkbox"
                name="venue_verified"
                required
            >

            <span class="check-content">

                <strong>
                    🏛️ Venue Availability Verified
                </strong>

                <span>
                    The venue is available for the new
                    date and time and there is no scheduling clash.
                </span>

            </span>

        </label>


        <!-- STAGE -->

        <label class="check-item">

            <input
                type="checkbox"
                name="stage_verified"
                required
            >

            <span class="check-content">

                <strong>
                    🎭 Stage Availability Verified
                </strong>

                <span>
                    The stage and required time slot have
                    been checked for the new schedule.
                </span>

            </span>

        </label>


        <!-- TECHNICAL -->

        <label class="check-item">

            <input
                type="checkbox"
                name="technical_verified"
                required
            >

            <span class="check-content">

                <strong>
                    🎤 Mic & Technical Requirements Verified
                </strong>

                <span>
                    Mic, sound system, electricity and other
                    technical requirements have been rechecked.
                </span>

            </span>

        </label>


        <!-- BROCHURE -->

        <label class="check-item">

            <input
                type="checkbox"
                name="brochure_verified"
                required
            >

            <span class="check-content">

                <strong>
                    🖼️ Brochure Details Verified
                </strong>

                <span>
                    The brochure will contain the updated
                    date, time, venue and other required details.
                </span>

            </span>

        </label>


        <!-- COMMITTEE -->

        <label class="check-item">

            <input
                type="checkbox"
                name="committee_verified"
                required
            >

            <span class="check-content">

                <strong>
                    👥 Organizing Committee Confirmation
                </strong>

                <span>
                    The organizing committee confirms that
                    the revised event arrangements are correct.
                </span>

            </span>

        </label>


        <br>


        <button
            type="submit"
            class="btn submit-btn"
        >
            ✓ Verify & Submit to Core Admin
        </button>


        <a
            href="approval_status.php"
            class="btn back-btn"
        >
            Cancel
        </a>


    </form>


    <?php elseif (
        $postponement_status === "verified"
    ): ?>


        <div class="status-box">

            <strong>
                🟢 Committee Verification Completed
            </strong>

            <p>
                The revised event details have been verified
                by the organizing committee.
            </p>

            <p>
                The event is now waiting for Core Admin
                final review and approval.
            </p>

        </div>


        <a
            href="approval_status.php"
            class="btn submit-btn"
        >
            ← Back to Approval Status
        </a>


    <?php elseif (
        $postponement_status === "approved"
    ): ?>


        <div class="status-box">

            <strong>
                🟢 Postponement Approved
            </strong>

            <p>
                Core Admin has approved the revised event
                schedule.
            </p>

        </div>


        <a
            href="approval_status.php"
            class="btn submit-btn"
        >
            ← Back to Approval Status
        </a>


    <?php else: ?>


        <div class="status-box">

            <strong>
                ℹ️ No Active Postponement Verification
            </strong>

            <p>
                This event is not currently waiting for
                postponement verification.
            </p>

        </div>


        <a
            href="approval_status.php"
            class="btn submit-btn"
        >
            ← Back to Approval Status
        </a>


    <?php endif; ?>


</div>


</div>

</body>

</html>


<?php

$conn->close();

?>