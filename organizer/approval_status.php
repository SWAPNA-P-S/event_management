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
   ORGANIZER ACCESS CHECK
   - Normal organizer role
   - OR approved organizer team member
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

    $access_check->bind_param(
        "i",
        $organizer_id
    );

    $access_check->execute();

    $access_result =
        $access_check->get_result();


    if ($access_result->num_rows > 0) {

        $has_organizer_access = true;

        $_SESSION["is_organizer"] = true;

    }

    $access_check->close();

}


/* ==========================================
   NO ORGANIZER ACCESS
========================================== */

if (!$has_organizer_access) {

    header("Location: ../student_dashboard.php");
    exit();

}


/* ==========================================
   FETCH EVENTS
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
            description,
            objective,
            event_date,
            original_event_date,
            postponed_from_date,
            postponement_reason,
            postponed_at,
            start_time,
            end_time,
            event_type,
            fee,
            expected_participants,
            guest_details,
            requirements,
            venue,
            request_status,
            status,
            correction_reason
         FROM events
         WHERE organizer_id = ?
         ORDER BY event_id DESC"
    );

    $stmt->bind_param(
        "i",
        $organizer_id
    );

} else {

    $stmt = $conn->prepare(
        "SELECT DISTINCT
            e.event_id,
            e.event_name,
            e.description,
            e.objective,
            e.event_date,
            e.original_event_date,
            e.postponed_from_date,
            e.postponement_reason,
            e.postponed_at,
            e.start_time,
            e.end_time,
            e.event_type,
            e.fee,
            e.expected_participants,
            e.guest_details,
            e.requirements,
            e.venue,
            e.request_status,
            e.status,
            e.correction_reason
         FROM events e
         INNER JOIN organizer_team_members tm
             ON e.organizer_request_id = tm.request_id
         INNER JOIN organizer_requests r
             ON tm.request_id = r.request_id
         WHERE tm.user_id = ?
         AND r.status = 'approved'
         ORDER BY e.event_id DESC"
    );

    $stmt->bind_param(
        "i",
        $organizer_id
    );

}


$stmt->execute();

$result = $stmt->get_result();

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
    Approval Status | Event Hub
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
    max-width: 1100px;
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

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.event-header h2 {
    margin: 0;
    color: #ffffff;
}


/* STATUS */

.status {
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.status-pending {
    background: #78350f;
    color: #fed7aa;
}

.status-approved {
    background: #064e3b;
    color: #a7f3d0;
}

.status-correction {
    background: #7c2d12;
    color: #fdba74;
}

.status-postponed {
    background: #4c1d95;
    color: #ddd6fe;
}


/* DETAILS */

.details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.detail-box {
    background: #081525;
    padding: 14px;
    border-radius: 8px;
}

.detail-label {
    color: #94a3b8;
    font-size: 13px;
    margin-bottom: 5px;
}

.detail-value {
    color: white;
    font-size: 15px;
}


/* POSTPONEMENT INFO */

.postponed-box {
    background: #24163d;
    border: 1px solid #6d28d9;
    border-radius: 10px;
    padding: 18px;
    margin-top: 20px;
}

.postponed-box h3 {
    margin-top: 0;
    color: #c4b5fd;
}

.postponed-box p {
    color: #ddd6fe;
    line-height: 1.6;
    margin-bottom: 8px;
}


/* CORRECTION */

.correction-box {
    background: #3b1d12;
    border: 1px solid #7c2d12;
    border-radius: 10px;
    padding: 18px;
    margin-top: 20px;
}

.correction-box h3 {
    margin-top: 0;
    color: #fdba74;
}

.correction-box p {
    color: #fed7aa;
    line-height: 1.6;
}


/* BUTTON */

.btn {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    margin-top: 15px;
    margin-right: 8px;
}

.update-btn {
    background: #2563eb;
    color: white;
}

.update-btn:hover {
    background: #1d4ed8;
}

.planning-btn {
    background: #059669;
    color: white;
}

.planning-btn:hover {
    background: #047857;
}

.postpone-btn {
    background: #7c3aed;
    color: white;
}

.postpone-btn:hover {
    background: #6d28d9;
}


/* NO EVENTS */

.no-events {
    background: #0d1b2e;
    border: 1px solid #1e3a5f;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    color: #94a3b8;
}


/* RESPONSIVE */

@media (max-width: 700px) {

    .event-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .details {
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
    ← Back to Dashboard
</a>


<h1>
    📊 Approval Status
</h1>


<p class="subtitle">
    Track the approval status of your event requests.
</p>



<?php if ($result->num_rows > 0): ?>


    <?php while ($event = $result->fetch_assoc()): ?>


        <div class="event-card">


            <!-- EVENT HEADER -->

            <div class="event-header">

                <h2>

                    <?php

                    echo htmlspecialchars(
                        $event["event_name"]
                    );

                    ?>

                </h2>


                <?php

                $status =
                    $event["request_status"];


                /*
                 * POSTPONED EVENT
                 */

                if (
                    !empty(
                        $event["postponed_from_date"]
                    )
                ) {

                    echo '
                    <span class="status status-postponed">
                        ⏸ Postponed
                    </span>';

                }

                elseif ($status === "approved") {

                    echo '
                    <span class="status status-approved">
                        ✓ Approved
                    </span>';

                }

                elseif (
                    $status === "correction_required"
                    ||
                    $status === "correction"
                ) {

                    echo '
                    <span class="status status-correction">
                        ⚠ Correction Required
                    </span>';

                }

                else {

                    echo '
                    <span class="status status-pending">
                        ⏳ Pending
                    </span>';

                }

                ?>

            </div>



            <!-- EVENT DETAILS -->

            <div class="details">


                <div class="detail-box">

                    <div class="detail-label">
                        Event Date
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $event["event_date"]
                        );

                        ?>

                    </div>

                </div>



                <div class="detail-box">

                    <div class="detail-label">
                        Time
                    </div>

                    <div class="detail-value">

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



                <div class="detail-box">

                    <div class="detail-label">
                        Event Type
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $event["event_type"]
                        );

                        ?>

                    </div>

                </div>



                <div class="detail-box">

                    <div class="detail-label">
                        Registration Fee
                    </div>

                    <div class="detail-value">

                        <?php

                        if (
                            $event["event_type"]
                            === "Paid"
                        ) {

                            echo "₹" .
                                number_format(
                                    $event["fee"],
                                    2
                                );

                        } else {

                            echo "Free";

                        }

                        ?>

                    </div>

                </div>



                <div class="detail-box">

                    <div class="detail-label">
                        Expected Participants
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $event[
                                "expected_participants"
                            ]
                        );

                        ?>

                    </div>

                </div>



                <div class="detail-box">

                    <div class="detail-label">
                        Venue
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $event["venue"]
                        );

                        ?>

                    </div>

                </div>


            </div>



            <!-- POSTPONEMENT INFORMATION -->

            <?php

            if (
                !empty(
                    $event["postponed_from_date"]
                )
            ):

            ?>

                <div class="postponed-box">

                    <h3>
                        ⏸ Event Postponed
                    </h3>

                    <p>
                        This event has been moved from
                        the previously scheduled date.
                    </p>


                    <?php
                    if (
                        !empty(
                            $event["postponed_from_date"]
                        )
                    ):
                    ?>

                        <p>
                            <strong>
                                Previous Date:
                            </strong>

                            <?php

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

                            ?>
                        </p>

                    <?php endif; ?>


                    <?php
                    if (
                        !empty(
                            $event["postponement_reason"]
                        )
                    ):
                    ?>

                        <p>
                            <strong>
                                Reason:
                            </strong>

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $event[
                                        "postponement_reason"
                                    ]
                                )
                            );

                            ?>
                        </p>

                    <?php endif; ?>


                    <p>
                        The new event date and related
                        arrangements must be verified
                        before the event is finalized.
                    </p>

                </div>

            <?php endif; ?>



            <!-- CORRECTION SECTION -->

            <?php

            if (
                $status === "correction_required"
                ||
                $status === "correction"
            ):

            ?>

                <div class="correction-box">

                    <h3>
                        ⚠️ Correction Required
                    </h3>


                    <p>
                        Admin has requested changes
                        to this event request.
                    </p>


                    <?php

                    if (
                        !empty(
                            $event["correction_reason"]
                        )
                    ):

                    ?>

                        <strong>
                            Correction Reason:
                        </strong>


                        <p>

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $event[
                                        "correction_reason"
                                    ]
                                )
                            );

                            ?>

                        </p>

                    <?php endif; ?>


                    <a
                        href="event_request.php?event_id=<?php echo $event["event_id"]; ?>"
                        class="btn update-btn"
                    >
                        ✏️ Review / Update Request
                    </a>

                </div>

            <?php endif; ?>



            <!-- APPROVED EVENT -->

            <?php

            if (
                $status === "approved"
                &&
                empty(
                    $event["postponed_from_date"]
                )
            ):

            ?>

                <a
                    href="planning.php?event_id=<?php echo $event["event_id"]; ?>"
                    class="btn planning-btn"
                >
                    📋 Continue Planning →
                </a>


                <!-- POSTPONEMENT BUTTON -->

                <a
                    href="postpone_event.php?event_id=<?php echo $event["event_id"]; ?>"
                    class="btn postpone-btn"
                >
                    ⏸ Request Postponement
                </a>

            <?php endif; ?>


        </div>


    <?php endwhile; ?>


<?php else: ?>


    <div class="no-events">

        <h3>
            No Event Requests Found
        </h3>

        <p>
            You have not submitted any event
            requests yet.
        </p>

    </div>


<?php endif; ?>


</div>


</body>

</html>


<?php

$stmt->close();
$conn->close();

?>