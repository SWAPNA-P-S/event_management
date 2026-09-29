<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "core_admin") {
    header("Location: ../login.html");
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatDateValue($date)
{
    if (empty($date)) {
        return "-";
    }

    $time = strtotime($date);

    return $time ? date("d M Y", $time) : h($date);
}

function formatTimeValue($time)
{
    if (empty($time)) {
        return "-";
    }

    $timestamp = strtotime($time);

    return $timestamp ? date("h:i A", $timestamp) : h($time);
}

$message = "";
$message_type = "";


/* =========================
   APPROVE / REJECT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cancellation_id = isset($_POST["cancellation_id"])
        ? (int)$_POST["cancellation_id"]
        : 0;

    $action = $_POST["action"] ?? "";

    $admin_comment = trim($_POST["admin_comment"] ?? "");

    if ($cancellation_id <= 0) {

        $message = "Invalid cancellation request.";
        $message_type = "error";

    } else {

        /* Get request + event */

        $stmt = $conn->prepare("
            SELECT
                cr.cancellation_id,
                cr.event_id,
                cr.organizer_id,
                cr.reason,
                cr.status,

                e.event_name,
                e.event_date,
                e.start_time,
                e.end_time,
                e.venue,
                e.status AS event_status

            FROM event_cancellation_requests cr

            INNER JOIN events e
                ON e.event_id = cr.event_id

            WHERE cr.cancellation_id = ?

            LIMIT 1
        ");

        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $request = $result->fetch_assoc();

        $stmt->close();


        if (!$request) {

            $message = "Cancellation request not found.";
            $message_type = "error";

        } elseif ($request["status"] !== "pending") {

            $message = "This cancellation request has already been processed.";
            $message_type = "error";

        } elseif ($action === "approve") {

            /*
             * APPROVE CANCELLATION
             *
             * Request → approved
             * Event → cancelled
             */

            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare("
                    UPDATE event_cancellation_requests
                    SET
                        status = 'approved',
                        admin_comment = ?,
                        reviewed_at = NOW()
                    WHERE cancellation_id = ?
                ");

                $stmt->bind_param(
                    "si",
                    $admin_comment,
                    $cancellation_id
                );

                $stmt->execute();
                $stmt->close();


                $stmt = $conn->prepare("
                    UPDATE events
                    SET
                        status = 'cancelled'
                    WHERE event_id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $request["event_id"]
                );

                $stmt->execute();
                $stmt->close();


                $conn->commit();

                $message = "Event cancellation approved. The event is now marked as cancelled.";
                $message_type = "success";

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Unable to approve cancellation.";
                $message_type = "error";
            }


        } elseif ($action === "reject") {

            /*
             * REJECT CANCELLATION
             *
             * Event remains unchanged.
             */

            $stmt = $conn->prepare("
                UPDATE event_cancellation_requests
                SET
                    status = 'rejected',
                    admin_comment = ?,
                    reviewed_at = NOW()
                WHERE cancellation_id = ?
            ");

            $stmt->bind_param(
                "si",
                $admin_comment,
                $cancellation_id
            );


            if ($stmt->execute()) {

                $message = "Cancellation request rejected. The event remains active.";
                $message_type = "success";

            } else {

                $message = "Unable to reject cancellation request.";
                $message_type = "error";
            }

            $stmt->close();


        } else {

            $message = "Invalid action.";
            $message_type = "error";
        }
    }
}


/* =========================
   PENDING REQUESTS
========================= */

$requests = [];

$sql = "
    SELECT
        cr.cancellation_id,
        cr.event_id,
        cr.organizer_id,
        cr.reason,
        cr.status,
        cr.admin_comment,
        cr.requested_at,

        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.event_type,
        e.status AS event_status,
        e.publication_status,

        u.name AS organizer_name,
        u.email AS organizer_email

    FROM event_cancellation_requests cr

    INNER JOIN events e
        ON e.event_id = cr.event_id

    LEFT JOIN users u
        ON u.id = cr.organizer_id

    WHERE cr.status = 'pending'

    ORDER BY cr.requested_at ASC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

$total_pending = count($requests);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Cancellation Review | Core Admin</title>

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

    background: #F4F7FB;

    color: #172033;
}


/* HEADER */

.header {

    background: #0B1F3A;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.header-left h1 {

    margin: 0;

    font-size: 22px;
}

.header-left p {

    margin: 5px 0 0;

    color: #DCE4EF;

    font-size: 13px;
}

.back-btn {

    text-decoration: none;

    color: white;

    border:
        1px solid
        rgba(255,255,255,0.3);

    padding: 9px 15px;

    border-radius: 8px;

    font-size: 13px;
}


/* CONTAINER */

.container {

    width: 94%;

    max-width: 1250px;

    margin: 30px auto;
}


/* ALERT */

.alert {

    padding: 14px 16px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 14px;
}

.alert.success {

    background: #E8F7EE;

    color: #176B3A;

    border:
        1px solid
        #B9E5C8;
}

.alert.error {

    background: #FDECEC;

    color: #A52828;

    border:
        1px solid
        #F2B8B8;
}


/* SUMMARY */

.summary {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 22px;
}

.summary h2 {

    margin: 0 0 6px;

    font-size: 19px;
}

.summary p {

    margin: 0;

    color: #667085;

    font-size: 14px;
}

.count {

    display: inline-block;

    margin-top: 14px;

    background: #FFF4D6;

    color: #8A6100;

    padding: 8px 13px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}


/* EMPTY */

.empty {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 12px;

    padding: 55px 20px;

    text-align: center;
}

.empty-icon {

    font-size: 42px;

    margin-bottom: 12px;
}

.empty h3 {

    margin: 0 0 7px;
}

.empty p {

    margin: 0;

    color: #667085;

    font-size: 14px;
}


/* GRID */

.grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(380px, 1fr)
        );

    gap: 20px;
}


/* CARD */

.card {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 13px;

    overflow: hidden;
}


/* CARD HEADER */

.card-top {

    background: #F8FAFD;

    border-bottom:
        1px solid
        #DCE4EF;

    padding: 18px;
}

.card-top h3 {

    margin: 0 0 7px;

    color: #0B1F3A;

    font-size: 18px;
}

.event-id {

    font-size: 12px;

    color: #667085;
}

.status {

    display: inline-block;

    margin-top: 10px;

    padding: 6px 10px;

    border-radius: 15px;

    background: #FDECEC;

    color: #A52828;

    font-size: 11px;

    font-weight: bold;
}


/* DETAILS */

.details {

    padding: 18px;
}

.section-title {

    margin-top: 4px;

    margin-bottom: 12px;

    padding-bottom: 8px;

    border-bottom:
        1px solid
        #E8EDF4;

    color: #0B1F3A;

    font-size: 14px;

    font-weight: bold;
}

.detail-row {

    display: flex;

    gap: 12px;

    margin-bottom: 12px;

    font-size: 13px;
}

.detail-label {

    width: 125px;

    flex-shrink: 0;

    color: #667085;

    font-weight: 600;
}

.detail-value {

    color: #172033;

    line-height: 1.5;
}


/* REASON */

.reason-box {

    background: #FFF8E8;

    border:
        1px solid
        #F0D89A;

    border-radius: 9px;

    padding: 13px;

    font-size: 13px;

    line-height: 1.5;

    color: #5F4A00;
}


/* FORM */

.review-title {

    margin-top: 20px;

    margin-bottom: 9px;

    color: #0B1F3A;

    font-size: 14px;

    font-weight: bold;
}

textarea {

    width: 100%;

    min-height: 80px;

    resize: vertical;

    border:
        1px solid
        #DCE4EF;

    border-radius: 8px;

    padding: 10px;

    font-family: inherit;

    font-size: 13px;

    outline: none;
}

textarea:focus {

    border-color: #1E4E8C;
}


/* BUTTONS */

.actions {

    display: flex;

    gap: 10px;

    margin-top: 12px;
}

button {

    border: none;

    cursor: pointer;

    padding: 10px 15px;

    border-radius: 8px;

    font-size: 13px;

    font-weight: bold;
}

.approve-btn {

    background: #1E4E8C;

    color: white;
}

.approve-btn:hover {

    background: #163C6D;
}

.reject-btn {

    background: #FDECEC;

    color: #A52828;

    border:
        1px solid
        #E5B4B4;
}

.reject-btn:hover {

    background: #F9DADA;
}


/* RESPONSIVE */

@media (max-width: 650px) {

    .header {

        padding: 16px;

        gap: 10px;
    }

    .header-left h1 {

        font-size: 18px;
    }

    .container {

        width: 92%;
    }

    .grid {

        grid-template-columns: 1fr;
    }

    .detail-row {

        flex-direction: column;

        gap: 3px;
    }

    .detail-label {

        width: auto;
    }

    .actions {

        flex-direction: column;
    }

    button {

        width: 100%;
    }
}

</style>

</head>


<body>


<header class="header">

    <div class="header-left">

        <h1>
            Cancellation Review
        </h1>

        <p>
            Core Admin / Event Control
        </p>

    </div>


    <a
        href="core_admin_dashboard.php"
        class="back-btn">

        ← Back to Dashboard

    </a>

</header>



<div class="container">


    <?php if ($message !== ""): ?>

        <div class="alert <?php echo h($message_type); ?>">

            <?php echo h($message); ?>

        </div>

    <?php endif; ?>



    <div class="summary">

        <h2>
            Event Cancellation Requests
        </h2>

        <p>
            Review cancellation requests submitted by event organizers.
        </p>

        <span class="count">

            <?php echo $total_pending; ?>

            Pending Requests

        </span>

    </div>



    <?php if (empty($requests)): ?>


        <div class="empty">

            <div class="empty-icon">
                ❌
            </div>

            <h3>
                No Cancellation Requests
            </h3>

            <p>
                There are currently no pending event cancellation requests.
            </p>

        </div>


    <?php else: ?>


        <div class="grid">


            <?php foreach ($requests as $request): ?>


                <div class="card">


                    <div class="card-top">


                        <h3>

                            <?php
                            echo h(
                                $request["event_name"]
                            );
                            ?>

                        </h3>


                        <div class="event-id">

                            Event ID:
                            <?php
                            echo h(
                                $request["event_id"]
                            );
                            ?>

                        </div>


                        <span class="status">

                            Cancellation Requested

                        </span>


                    </div>



                    <div class="details">


                        <div class="section-title">
                            Event Details
                        </div>


                        <div class="detail-row">

                            <div class="detail-label">
                                Event Type
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $request["event_type"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Event Date
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatDateValue(
                                    $request["event_date"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Event Time
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatTimeValue(
                                    $request["start_time"]
                                );
                                ?>

                                -

                                <?php
                                echo formatTimeValue(
                                    $request["end_time"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Venue
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $request["venue"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Organizer
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $request["organizer_name"]
                                    ?: "-"
                                );
                                ?>

                                <br>

                                <?php
                                echo h(
                                    $request["organizer_email"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <div class="detail-row">

                            <div class="detail-label">
                                Requested On
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatDateValue(
                                    $request["requested_at"]
                                );
                                ?>

                            </div>

                        </div>



                        <div class="section-title">
                            Cancellation Reason
                        </div>


                        <div class="reason-box">

                            <?php
                            echo nl2br(
                                h(
                                    $request["reason"]
                                )
                            );
                            ?>

                        </div>



                        <div class="review-title">
                            Core Admin Comment
                        </div>


                        <form
                            method="POST"
                            onsubmit="return validateReview(this);"
                        >


                            <input
                                type="hidden"
                                name="cancellation_id"
                                value="<?php
                                    echo (int)
                                        $request[
                                            "cancellation_id"
                                        ];
                                ?>"
                            >


                            <textarea
                                name="admin_comment"
                                placeholder="Optional comment..."
                            ></textarea>


                            <div class="actions">


                                <button
                                    type="submit"
                                    name="action"
                                    value="approve"
                                    class="approve-btn"
                                    onclick="return confirm('Approve this cancellation? The event will be marked as cancelled.');"
                                >

                                    ✓ Approve Cancellation

                                </button>


                                <button
                                    type="submit"
                                    name="action"
                                    value="reject"
                                    class="reject-btn"
                                    onclick="return confirm('Reject this cancellation request?');"
                                >

                                    ✕ Reject

                                </button>


                            </div>


                        </form>


                    </div>

                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>


<script>

function validateReview(form)
{
    return true;
}

</script>


</body>

</html>