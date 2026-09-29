<?php

session_start();

require_once "../db.php";


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

$organizer_request_ids = [];


/*
 * Get all approved organizer teams
 * for the current user.
 */

$team_check = $conn->prepare(
    "SELECT tm.request_id
     FROM organizer_team_members tm
     INNER JOIN organizer_requests r
         ON tm.request_id = r.request_id
     WHERE tm.user_id = ?
       AND r.status = 'approved'
     ORDER BY tm.request_id ASC"
);

$team_check->bind_param(
    "i",
    $organizer_id
);

$team_check->execute();

$team_result =
    $team_check->get_result();


while ($team_row = $team_result->fetch_assoc()) {

    $organizer_request_ids[] =
        (int)$team_row["request_id"];

}

$team_check->close();


if (!empty($organizer_request_ids)) {

    $has_organizer_access = true;

}


/*
 * Existing global organizer role can also
 * access the page.
 */

if (
    isset($_SESSION["role"])
    &&
    $_SESSION["role"] === "organizer"
) {

    $has_organizer_access = true;

}


if (!$has_organizer_access) {

    header("Location: ../student_dashboard.php");
    exit();

}


/* ==========================================
   EVENT ID
========================================== */

$event_id =
    isset($_GET["event_id"])
        ? (int)$_GET["event_id"]
        : 0;


if ($event_id <= 0) {

    header("Location: organizer_dashboard.php");
    exit();

}


/* ==========================================
   CHECK EVENT OWNERSHIP
========================================== */

$event = null;


/*
 * If approved team IDs exist, event must belong
 * to one of those teams.
 */

if (!empty($organizer_request_ids)) {

    $placeholders =
        implode(
            ",",
            array_fill(
                0,
                count($organizer_request_ids),
                "?"
            )
        );

    $types =
        str_repeat(
            "i",
            count($organizer_request_ids)
        );


    $sql =
        "SELECT
            e.event_id,
            e.event_name,
            e.event_type,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.status,
            e.request_status,
            e.publication_status,
            e.organizer_id,
            e.organizer_request_id

         FROM events e

         WHERE e.event_id = ?

         AND (
             e.organizer_id = ?
             OR e.organizer_request_id IN ($placeholders)
         )

         LIMIT 1";


    $stmt =
        $conn->prepare($sql);


    /*
     * Build bind parameters dynamically.
     */

    $bind_types =
        "ii" . $types;


    $bind_values = [
        $event_id,
        $organizer_id
    ];


    foreach (
        $organizer_request_ids
        as $request_id
    ) {

        $bind_values[] =
            $request_id;

    }


    $params = [];

    $params[] =
        $bind_types;

    foreach (
        $bind_values
        as $key => $value
    ) {

        $params[] =
            &$bind_values[$key];

    }


    call_user_func_array(
        [$stmt, "bind_param"],
        $params
    );


    $stmt->execute();

    $event_result =
        $stmt->get_result();


    if ($event_result->num_rows > 0) {

        $event =
            $event_result->fetch_assoc();

    }


    $stmt->close();

}


/*
 * Fallback for organizer role.
 */

if ($event === null) {

    $stmt = $conn->prepare(
        "SELECT
            event_id,
            event_name,
            event_type,
            event_date,
            start_time,
            end_time,
            venue,
            status,
            request_status,
            publication_status,
            organizer_id,
            organizer_request_id

         FROM events

         WHERE event_id = ?
           AND organizer_id = ?

         LIMIT 1"
    );

    $stmt->bind_param(
        "ii",
        $event_id,
        $organizer_id
    );

    $stmt->execute();

    $event_result =
        $stmt->get_result();

    if ($event_result->num_rows > 0) {

        $event =
            $event_result->fetch_assoc();

    }

    $stmt->close();

}


if ($event === null) {

    $conn->close();

    die(
        "You are not authorized to request cancellation for this event."
    );

}


/* ==========================================
   CHECK CURRENT EVENT STATUS
========================================== */

if (
    strtolower(
        $event["status"] ?? ""
    )
    === "cancelled"
) {

    $conn->close();

    die(
        "This event has already been cancelled."
    );

}


/* ==========================================
   CHECK EXISTING CANCELLATION REQUEST
========================================== */

$existing_request = null;


$stmt = $conn->prepare(
    "SELECT
        cancellation_id,
        reason,
        status,
        admin_comment,
        requested_at,
        reviewed_at

     FROM event_cancellation_requests

     WHERE event_id = ?
       AND organizer_id = ?

     ORDER BY cancellation_id DESC

     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $event_id,
    $organizer_id
);

$stmt->execute();

$request_result =
    $stmt->get_result();


if ($request_result->num_rows > 0) {

    $existing_request =
        $request_result->fetch_assoc();

}

$stmt->close();


/* ==========================================
   FORM SUBMISSION
========================================== */

$message = "";

$message_type = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {


    $reason =
        trim(
            $_POST["reason"] ?? ""
        );


    if ($reason === "") {

        $message =
            "Please enter a reason for cancelling the event.";

        $message_type =
            "error";

    }

    elseif (
        $existing_request !== null
        &&
        in_array(
            $existing_request["status"],
            [
                "pending",
                "approved"
            ],
            true
        )
    ) {

        $message =
            "A cancellation request already exists for this event.";

        $message_type =
            "error";

    }

    else {


        /*
         * New request.
         */

        $stmt = $conn->prepare(
            "INSERT INTO event_cancellation_requests
            (
                event_id,
                organizer_id,
                reason,
                status
            )
            VALUES
            (?, ?, ?, 'pending')"
        );


        $stmt->bind_param(
            "iis",
            $event_id,
            $organizer_id,
            $reason
        );


        if ($stmt->execute()) {

            $message =
                "Cancellation request submitted successfully. It is now waiting for Core Admin review.";

            $message_type =
                "success";


            /*
             * Refresh existing request.
             */

            $existing_request = [
                "cancellation_id" =>
                    $conn->insert_id,

                "reason" =>
                    $reason,

                "status" =>
                    "pending",

                "admin_comment" =>
                    null,

                "requested_at" =>
                    date("Y-m-d H:i:s"),

                "reviewed_at" =>
                    null
            ];

        }
        else {

            $message =
                "Unable to submit cancellation request: "
                . $stmt->error;

            $message_type =
                "error";

        }


        $stmt->close();

    }

}


/* ==========================================
   CLOSE DB
========================================== */

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
    Event Cancellation Request | Event Hub
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #F4F7FB;

    color: #172033;
}


.container {

    width: 92%;

    max-width: 850px;

    margin: 40px auto;
}


.back {

    color: #1E4E8C;

    text-decoration: none;

    font-weight: bold;

    display: inline-block;

    margin-bottom: 20px;
}


.back:hover {

    text-decoration: underline;
}


h1 {

    margin: 0 0 8px;

    color: #0B1F3A;
}


.subtitle {

    color: #61708A;

    margin-bottom: 25px;
}


.card {

    background: white;

    border:
        1px solid #DCE4EF;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 10px rgba(0,0,0,0.05);
}


/* ==========================================
   EVENT INFORMATION
========================================== */

.event-title {

    font-size: 22px;

    font-weight: bold;

    color: #0B1F3A;

    margin-bottom: 8px;
}


.event-id {

    color: #61708A;

    font-size: 13px;

    margin-bottom: 18px;
}


.event-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;
}


.event-info {

    background: #F8FAFD;

    border:
        1px solid #E1E8F0;

    padding: 13px;

    border-radius: 8px;
}


.event-info strong {

    display: block;

    color: #61708A;

    font-size: 12px;

    margin-bottom: 5px;
}


/* ==========================================
   STATUS
========================================== */

.status {

    display: inline-block;

    padding: 7px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-top: 12px;
}


.status.pending {

    background: #FFF3CD;

    color: #856404;
}


.status.approved {

    background: #D4EDDA;

    color: #155724;
}


.status.rejected {

    background: #F8D7DA;

    color: #721C24;
}


.status.cancelled {

    background: #E5E7EB;

    color: #374151;
}


/* ==========================================
   MESSAGE
========================================== */

.success {

    background: #D4EDDA;

    color: #155724;

    border:
        1px solid #B7DFC1;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}


.error {

    background: #F8D7DA;

    color: #721C24;

    border:
        1px solid #F1BFC4;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}


/* ==========================================
   WARNING
========================================== */

.warning {

    background: #FFF7ED;

    border:
        1px solid #FED7AA;

    color: #9A3412;

    padding: 16px;

    border-radius: 8px;

    line-height: 1.6;

    margin-bottom: 20px;
}


/* ==========================================
   FORM
========================================== */

label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;

    color: #0B1F3A;
}


textarea {

    width: 100%;

    min-height: 140px;

    resize: vertical;

    padding: 13px;

    border:
        1px solid #CBD5E1;

    border-radius: 8px;

    font-family: Arial, sans-serif;

    font-size: 14px;

    color: #172033;

    background: white;
}


textarea:focus {

    outline: none;

    border-color: #1E4E8C;

    box-shadow:
        0 0 0 3px rgba(30,78,140,0.10);
}


.submit-btn {

    width: 100%;

    margin-top: 18px;

    padding: 14px;

    border: none;

    border-radius: 8px;

    background: #C62828;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;
}


.submit-btn:hover {

    background: #A61F1F;
}


/* ==========================================
   EXISTING REQUEST
========================================== */

.existing-reason {

    background: #F8FAFD;

    border:
        1px solid #DCE4EF;

    padding: 15px;

    border-radius: 8px;

    margin-top: 15px;

    line-height: 1.6;

    white-space: pre-wrap;
}


/* ==========================================
   RESPONSIVE
========================================== */

@media (max-width: 650px) {

    .container {

        width: 94%;

        margin: 25px auto;
    }


    .card {

        padding: 18px;
    }


    .event-grid {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="container">


<a
    href="organizer_dashboard.php"
    class="back"
>
    ← Back to Organizer Dashboard
</a>


<h1>
    ❌ Event Cancellation Request
</h1>


<p class="subtitle">
    Request cancellation of your event from Core Admin / PIMC.
</p>


<?php if ($message !== ""): ?>

<div class="<?php echo $message_type; ?>">

    <?php
    echo nl2br(
        htmlspecialchars($message)
    );
    ?>

</div>

<?php endif; ?>


<!-- ==========================================
     EVENT DETAILS
========================================== -->

<div class="card">

    <div class="event-title">

        <?php
        echo htmlspecialchars(
            $event["event_name"]
        );
        ?>

    </div>


    <div class="event-id">

        Event ID:
        <?php
        echo intval(
            $event["event_id"]
        );
        ?>

    </div>


    <div class="event-grid">


        <div class="event-info">

            <strong>
                Event Date
            </strong>

            <?php
            echo htmlspecialchars(
                $event["event_date"]
            );
            ?>

        </div>


        <div class="event-info">

            <strong>
                Time
            </strong>

            <?php

            echo htmlspecialchars(
                date(
                    "h:i A",
                    strtotime(
                        $event["start_time"]
                    )
                )
            );

            echo " - ";

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


        <div class="event-info">

            <strong>
                Venue
            </strong>

            <?php
            echo htmlspecialchars(
                $event["venue"]
                ?? "-"
            );
            ?>

        </div>


        <div class="event-info">

            <strong>
                Event Type
            </strong>

            <?php
            echo htmlspecialchars(
                $event["event_type"]
                ?? "-"
            );
            ?>

        </div>


    </div>


    <?php

    $current_status =
        strtolower(
            $event["status"]
            ?? ""
        );

    ?>


    <?php if (
        $current_status === "cancelled"
    ): ?>

        <span class="status cancelled">

            ❌ Event Cancelled

        </span>

    <?php elseif (
        $existing_request !== null
        &&
        $existing_request["status"]
        === "pending"
    ): ?>

        <span class="status pending">

            ⏳ Cancellation Request Pending

        </span>

    <?php elseif (
        $existing_request !== null
        &&
        $existing_request["status"]
        === "approved"
    ): ?>

        <span class="status approved">

            ✓ Cancellation Approved

        </span>

    <?php elseif (
        $existing_request !== null
        &&
        $existing_request["status"]
        === "rejected"
    ): ?>

        <span class="status rejected">

            ✕ Cancellation Request Rejected

        </span>

    <?php endif; ?>


</div>


<?php if (
    $existing_request !== null
    &&
    $existing_request["status"] === "pending"
): ?>


<div class="warning">

    <strong>
        ⏳ Cancellation request already submitted.
    </strong>

    <br><br>

    Your request is currently waiting for
    Core Admin / PIMC review.

    <br><br>

    You do not need to submit another request.

</div>


<div class="card">

    <strong>
        Your Cancellation Reason
    </strong>


    <div class="existing-reason">

        <?php
        echo htmlspecialchars(
            $existing_request["reason"]
        );
        ?>

    </div>

</div>


<?php elseif (
    $existing_request !== null
    &&
    $existing_request["status"] === "approved"
): ?>


<div class="warning">

    <strong>
        ✓ Cancellation approved.
    </strong>

    <br><br>

    Core Admin has approved the cancellation request
    for this event.

</div>


<div class="card">

    <strong>
        Cancellation Reason
    </strong>


    <div class="existing-reason">

        <?php
        echo htmlspecialchars(
            $existing_request["reason"]
        );
        ?>

    </div>


    <?php if (
        !empty(
            $existing_request["admin_comment"]
        )
    ): ?>

        <br>

        <strong>
            Core Admin Comment
        </strong>

        <div class="existing-reason">

            <?php
            echo htmlspecialchars(
                $existing_request["admin_comment"]
            );
            ?>

        </div>

    <?php endif; ?>


</div>


<?php elseif (
    $existing_request !== null
    &&
    $existing_request["status"] === "rejected"
): ?>


<div class="warning">

    <strong>
        ✕ Previous cancellation request was rejected.
    </strong>

    <br><br>

    You may submit a new request with an updated reason.

</div>


<?php if (
    !empty(
        $existing_request["admin_comment"]
    )
): ?>

<div class="card">

    <strong>
        Core Admin Comment
    </strong>


    <div class="existing-reason">

        <?php
        echo htmlspecialchars(
            $existing_request["admin_comment"]
        );
        ?>

    </div>

</div>

<?php endif; ?>


<div class="card">

<form method="POST">


<label for="reason">

    Reason for Cancellation

</label>


<textarea
    id="reason"
    name="reason"
    placeholder="Explain why this event needs to be cancelled..."
    required
></textarea>


<button
    type="submit"
    class="submit-btn"
>

    ❌ Submit Cancellation Request

</button>


</form>

</div>


<?php else: ?>


<div class="warning">

    <strong>
        Before submitting:
    </strong>

    <br><br>

    Please make sure that cancellation is genuinely
    required. Your request will be reviewed by
    Core Admin / PIMC.

    <br><br>

    The event will <strong>not</strong> be cancelled
    immediately after submitting this request.

</div>


<div class="card">

<form method="POST">


<label for="reason">

    Reason for Cancellation

</label>


<textarea
    id="reason"
    name="reason"
    placeholder="Explain why this event needs to be cancelled..."
    required
></textarea>


<button
    type="submit"
    class="submit-btn"
>

    ❌ Submit Cancellation Request

</button>


</form>

</div>


<?php endif; ?>


</div>


</body>

</html>