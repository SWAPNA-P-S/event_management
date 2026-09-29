<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.html");
    exit();
}

require_once "../db.php";

$event_id = isset($_GET["event_id"]) ? (int)$_GET["event_id"] : 0;

if ($event_id <= 0) {
    die("Invalid event ID.");
}


/* =========================================================
   HANDLE APPROVE / CORRECTION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    /* =====================================================
       APPROVE EVENT
    ===================================================== */

    if ($action === "approve") {

        /*
         * Fetch organizer request linked to this event.
         */

        $request_id = null;

        $request_stmt = $conn->prepare("
            SELECT organizer_request_id
            FROM events
            WHERE event_id = ?
        ");

        $request_stmt->bind_param("i", $event_id);
        $request_stmt->execute();

        $request_result = $request_stmt->get_result();

        if ($request_result->num_rows === 1) {

            $request_row = $request_result->fetch_assoc();

            if (!empty($request_row["organizer_request_id"])) {
                $request_id = (int)$request_row["organizer_request_id"];
            }
        }

        $request_stmt->close();


        /*
         * Use transaction so that:
         *
         * Event approval
         * +
         * Organizer team approval
         *
         * happen together.
         */

        $conn->begin_transaction();

        try {

            /* ---------------------------------------------
               APPROVE EVENT
            --------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE events
                SET request_status = 'approved',
                    correction_reason = NULL
                WHERE event_id = ?
            ");

            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $stmt->close();


            /* ---------------------------------------------
               APPROVE ORGANIZER REQUEST
               Only when this event is linked to a team.
            --------------------------------------------- */

            if ($request_id !== null) {

                $team_stmt = $conn->prepare("
                    UPDATE organizer_requests
                    SET status = 'approved',
                        reviewed_at = CURRENT_TIMESTAMP
                    WHERE request_id = ?
                ");

                $team_stmt->bind_param("i", $request_id);
                $team_stmt->execute();
                $team_stmt->close();
            }


            /* ---------------------------------------------
               STAGE MANAGEMENT
               pending → assigned
            --------------------------------------------- */

            $stage_stmt = $conn->prepare("
                UPDATE stage_bookings
                SET booking_status = 'assigned'
                WHERE event_id = ?
                  AND booking_status = 'pending'
            ");

            $stage_stmt->bind_param("i", $event_id);
            $stage_stmt->execute();
            $stage_stmt->close();


            $conn->commit();

            header(
                "Location: event_request_view.php?event_id="
                . $event_id
                . "&success=approved"
            );

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            header(
                "Location: event_request_view.php?event_id="
                . $event_id
                . "&error=approve_failed"
            );

            exit();
        }
    }


    /* =====================================================
       RETURN FOR CORRECTION
    ===================================================== */

    if ($action === "correction") {

        $selected_reasons = $_POST["correction_reasons"] ?? [];

        if (!is_array($selected_reasons)) {
            $selected_reasons = [];
        }

        $selected_reasons = array_map("trim", $selected_reasons);
        $selected_reasons = array_filter($selected_reasons);


        /* OTHER REASON */

        $other_reason = trim($_POST["other_reason"] ?? "");

        if (!empty($other_reason)) {

            /*
             * Remove the generic "Other" checkbox text
             * because the actual reason is stored below.
             */

            $selected_reasons = array_filter(
                $selected_reasons,
                function ($reason) {
                    return $reason !== "Other";
                }
            );

            $selected_reasons[] = "Other: " . $other_reason;
        }


        /* REMOVE DUPLICATES */

        $selected_reasons = array_unique($selected_reasons);


        if (empty($selected_reasons)) {

            header(
                "Location: event_request_view.php?event_id="
                . $event_id
                . "&error=reason_required"
            );

            exit();
        }


        /* SAVE AS TEXT */

        $correction_reason = implode(", ", $selected_reasons);


        /*
         * Find linked organizer request.
         */

        $request_id = null;

        $request_stmt = $conn->prepare("
            SELECT organizer_request_id
            FROM events
            WHERE event_id = ?
        ");

        $request_stmt->bind_param("i", $event_id);
        $request_stmt->execute();

        $request_result = $request_stmt->get_result();

        if ($request_result->num_rows === 1) {

            $request_row = $request_result->fetch_assoc();

            if (!empty($request_row["organizer_request_id"])) {
                $request_id = (int)$request_row["organizer_request_id"];
            }
        }

        $request_stmt->close();


        /*
         * Event + organizer request are updated together.
         */

        $conn->begin_transaction();

        try {

            /* ---------------------------------------------
               EVENT → CORRECTION REQUIRED
            --------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE events
                SET request_status = 'correction_required',
                    correction_reason = ?
                WHERE event_id = ?
            ");

            $stmt->bind_param(
                "si",
                $correction_reason,
                $event_id
            );

            $stmt->execute();
            $stmt->close();


            /* ---------------------------------------------
               ORGANIZER REQUEST → CORRECTION REQUIRED
               Only when linked.
            --------------------------------------------- */

            if ($request_id !== null) {

                $team_stmt = $conn->prepare("
                    UPDATE organizer_requests
                    SET status = 'correction_required',
                        reviewed_at = CURRENT_TIMESTAMP
                    WHERE request_id = ?
                ");

                $team_stmt->bind_param("i", $request_id);
                $team_stmt->execute();
                $team_stmt->close();
            }


            /* ---------------------------------------------
               STAGE
               assigned → pending
            --------------------------------------------- */

            $stage_stmt = $conn->prepare("
                UPDATE stage_bookings
                SET booking_status = 'pending'
                WHERE event_id = ?
                  AND booking_status = 'assigned'
            ");

            $stage_stmt->bind_param("i", $event_id);
            $stage_stmt->execute();
            $stage_stmt->close();


            $conn->commit();

            header(
                "Location: event_request_view.php?event_id="
                . $event_id
                . "&success=correction"
            );

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            header(
                "Location: event_request_view.php?event_id="
                . $event_id
                . "&error=correction_failed"
            );

            exit();
        }
    }
}


/* =========================================================
   FETCH EVENT DETAILS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        e.*,
        u.name AS organizer_name,
        u.email AS organizer_email,
        u.phone AS organizer_phone,
        u.department AS organizer_department
    FROM events e
    LEFT JOIN users u
        ON e.organizer_id = u.id
    WHERE e.event_id = ?
");

$stmt->bind_param("i", $event_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Event request not found.");
}

$event = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   FETCH ORGANIZER TEAM
========================================================= */

$organizer_request = null;
$team_members = [];


if (!empty($event["organizer_request_id"])) {

    $request_id = (int)$event["organizer_request_id"];


    /* TEAM REQUEST */

    $team_stmt = $conn->prepare("
        SELECT
            request_id,
            requested_by,
            team_name,
            reason,
            status,
            created_at,
            reviewed_at
        FROM organizer_requests
        WHERE request_id = ?
    ");

    $team_stmt->bind_param("i", $request_id);
    $team_stmt->execute();

    $team_result = $team_stmt->get_result();

    if ($team_result->num_rows > 0) {
        $organizer_request = $team_result->fetch_assoc();
    }

    $team_stmt->close();


    /* TEAM MEMBERS */

    $member_stmt = $conn->prepare("
        SELECT
            tm.team_member_id,
            tm.user_id,
            tm.member_role,
            tm.joined_at,
            u.name,
            u.email,
            u.phone,
            u.department,
            u.student_id
        FROM organizer_team_members tm
        INNER JOIN users u
            ON tm.user_id = u.id
        WHERE tm.request_id = ?
        ORDER BY tm.team_member_id ASC
    ");

    $member_stmt->bind_param("i", $request_id);
    $member_stmt->execute();

    $member_result = $member_stmt->get_result();

    while ($member_row = $member_result->fetch_assoc()) {
        $team_members[] = $member_row;
    }

    $member_stmt->close();
}


/* =========================================================
   FETCH STAGE BOOKING
========================================================= */

$stage_booking = null;

$stage_stmt = $conn->prepare("
    SELECT
        sb.*,
        s.stage_name,
        s.location AS stage_location
    FROM stage_bookings sb
    LEFT JOIN stages s
        ON sb.stage_id = s.stage_id
    WHERE sb.event_id = ?
    ORDER BY sb.booking_id DESC
    LIMIT 1
");

$stage_stmt->bind_param("i", $event_id);
$stage_stmt->execute();

$stage_result = $stage_stmt->get_result();

if ($stage_result->num_rows > 0) {
    $stage_booking = $stage_result->fetch_assoc();
}

$stage_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Event Request Review | EventHub</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f1f5f9;
    color: #1e293b;
}


/* SIDEBAR */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 240px;
    height: 100vh;

    background: #0f172a;

    padding: 25px 18px;

    color: white;
}

.logo {
    font-size: 22px;
    font-weight: bold;

    color: #60a5fa;

    margin-bottom: 35px;
}

.sidebar a {
    display: block;

    padding: 13px 12px;

    margin-bottom: 7px;

    border-radius: 8px;

    color: #cbd5e1;

    text-decoration: none;
}

.sidebar a:hover {
    background: #1e3a8a;
    color: white;
}


/* MAIN */

.main {
    margin-left: 240px;

    padding: 30px;
}


/* HEADER */

.header {
    background: white;

    padding: 22px 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.header h1 {
    margin-bottom: 7px;
}

.header p {
    color: #64748b;
}


/* MESSAGES */

.message {
    padding: 14px 18px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.warning {
    background: #fef3c7;
    color: #92400e;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}


/* CARD */

.card {
    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 22px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.card h2 {
    margin-bottom: 20px;

    color: #1e3a8a;
}


/* DETAILS */

.details {
    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 18px;
}

.detail {
    background: #f8fafc;

    padding: 15px;

    border-radius: 8px;
}

.detail label {
    display: block;

    font-size: 12px;

    color: #64748b;

    margin-bottom: 6px;

    font-weight: bold;

    text-transform: uppercase;
}

.detail span {
    font-size: 15px;

    color: #1e293b;
}

.full {
    grid-column: 1 / -1;
}


/* STATUS */

.status {
    display: inline-block;

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.correction {
    background: #fee2e2;
    color: #991b1b;
}

.assigned {
    background: #ffedd5;
    color: #c2410c;
}

.booked {
    background: #fee2e2;
    color: #991b1b;
}


/* TEAM */

.team-status {
    margin-bottom: 20px;
}

.team-member {
    background: #f8fafc;

    border: 1px solid #e2e8f0;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 10px;
}

.team-member strong {
    color: #1e3a8a;
}

.member-role {
    color: #64748b;

    font-size: 13px;

    margin-top: 5px;
}


/* CORRECTION SECTION */

.correction-section {

    margin-top: 25px;

    padding: 20px;

    background: #fff7ed;

    border: 1px solid #fed7aa;

    border-radius: 10px;
}

.correction-section h3 {

    color: #9a3412;

    margin-bottom: 15px;

    font-size: 17px;
}


/* DROPDOWN */

.dropdown {
    position: relative;
    width: 100%;
}

.dropdown-button {

    width: 100%;

    background: white;

    border: 1px solid #cbd5e1;

    padding: 13px;

    border-radius: 8px;

    text-align: left;

    cursor: pointer;

    font-size: 14px;

    color: #475569;
}

.dropdown-button:hover {
    border-color: #f59e0b;
}

.dropdown-content {

    display: none;

    position: absolute;

    width: 100%;

    background: white;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    margin-top: 5px;

    padding: 10px;

    z-index: 100;

    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.dropdown-content.show {
    display: block;
}

.checkbox-item {

    display: block;

    padding: 9px;

    cursor: pointer;

    border-radius: 5px;
}

.checkbox-item:hover {
    background: #f8fafc;
}

.checkbox-item input {
    margin-right: 9px;
}


/* OTHER TEXT */

.other-box {

    display: none;

    margin-top: 15px;
}

.other-box.show {
    display: block;
}

.other-box label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;

    color: #9a3412;
}

.other-box textarea {

    width: 100%;

    min-height: 90px;

    padding: 12px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    resize: vertical;

    font-size: 14px;
}

.other-box textarea:focus {

    outline: none;

    border-color: #f59e0b;
}


/* SELECTED COUNT */

.selected-count {

    margin-top: 10px;

    font-size: 13px;

    color: #64748b;
}


/* ACTIONS */

.actions {

    display: flex;

    gap: 15px;

    margin-top: 25px;

    flex-wrap: wrap;
}

.btn {

    border: none;

    padding: 12px 22px;

    border-radius: 8px;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}

.approve {

    background: #16a34a;

    color: white;
}

.approve:hover {
    background: #15803d;
}

.correction-btn {

    background: #f59e0b;

    color: white;
}

.correction-btn:hover {
    background: #d97706;
}

.back {

    background: #e2e8f0;

    color: #334155;

    text-decoration: none;

    display: inline-block;
}

.back:hover {
    background: #cbd5e1;
}


/* RESPONSIVE */

@media (max-width: 800px) {

    .sidebar {
        width: 190px;
    }

    .main {
        margin-left: 190px;
    }

    .details {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <div class="logo">
        EventHub Admin
    </div>

    <a href="admin_dashboard.php">
        🏠 Dashboard
    </a>

    <a href="admin_dashboard.php">
        📋 Event Requests
    </a>

    <a href="#">
        👥 Users
    </a>

    <a href="#">
        📊 Reports
    </a>

    <br>

    <a href="../login.html">
        🚪 Logout
    </a>

</div>


<!-- MAIN -->

<div class="main">


    <div class="header">

        <h1>
            Event Request Review
        </h1>

        <p>
            Review the event request submitted by the organizer.
        </p>

    </div>


    <!-- SUCCESS -->

    <?php if (
        isset($_GET["success"])
        && $_GET["success"] === "approved"
    ): ?>

        <div class="message success">

            ✅ Event request approved successfully.

            <?php if ($organizer_request): ?>

                <br>
                👥 Organizer team
                <strong>
                    <?php echo htmlspecialchars($organizer_request["team_name"]); ?>
                </strong>
                has also been approved.

            <?php endif; ?>

            <?php if ($stage_booking): ?>

                <br>
                🎭 Stage assignment is now
                <strong>Assigned</strong> 🟠.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- CORRECTION -->

    <?php if (
        isset($_GET["success"])
        && $_GET["success"] === "correction"
    ): ?>

        <div class="message warning">

            ⚠️ Event returned to organizer for correction.

            <?php if ($organizer_request): ?>

                <br>
                👥 The linked organizer team request has also been
                marked for correction.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- ERRORS -->

    <?php if (
        isset($_GET["error"])
        && $_GET["error"] === "reason_required"
    ): ?>

        <div class="message error">

            ❌ Please select at least one correction reason.

        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET["error"])
        && $_GET["error"] === "approve_failed"
    ): ?>

        <div class="message error">

            ❌ Approval could not be completed.
            Please try again.

        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET["error"])
        && $_GET["error"] === "correction_failed"
    ): ?>

        <div class="message error">

            ❌ The correction request could not be saved.
            Please try again.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         EVENT INFORMATION
    ====================================================== -->

    <div class="card">

        <h2>📋 Event Information</h2>

        <div class="details">

            <div class="detail">

                <label>Event Name</label>

                <span>
                    <?php echo htmlspecialchars($event["event_name"]); ?>
                </span>

            </div>


            <div class="detail">

                <label>Event Type</label>

                <span>
                    <?php echo htmlspecialchars($event["event_type"] ?? "-"); ?>
                </span>

            </div>


            <div class="detail">

                <label>Event Date</label>

                <span>
                    <?php echo htmlspecialchars($event["event_date"]); ?>
                </span>

            </div>


            <div class="detail">

                <label>Time</label>

                <span>

                    <?php echo date(
                        "h:i A",
                        strtotime($event["start_time"])
                    ); ?>

                    -

                    <?php echo date(
                        "h:i A",
                        strtotime($event["end_time"])
                    ); ?>

                </span>

            </div>


            <div class="detail">

                <label>Venue</label>

                <span>
                    <?php echo htmlspecialchars($event["venue"]); ?>
                </span>

            </div>


            <div class="detail">

                <label>Registration Fee</label>

                <span>
                    ₹<?php echo number_format(
                        (float)$event["fee"],
                        2
                    ); ?>
                </span>

            </div>


            <div class="detail full">

                <label>Description</label>

                <span>
                    <?php echo nl2br(
                        htmlspecialchars(
                            $event["description"] ?? "-"
                        )
                    ); ?>
                </span>

            </div>


            <div class="detail full">

                <label>Objective</label>

                <span>
                    <?php echo nl2br(
                        htmlspecialchars(
                            $event["objective"] ?? "-"
                        )
                    ); ?>
                </span>

            </div>


            <div class="detail">

                <label>Expected Participants</label>

                <span>
                    <?php echo htmlspecialchars(
                        $event["expected_participants"] ?? "-"
                    ); ?>
                </span>

            </div>


            <div class="detail">

                <label>Guest Details</label>

                <span>
                    <?php echo htmlspecialchars(
                        $event["guest_details"] ?? "-"
                    ); ?>
                </span>

            </div>


            <div class="detail full">

                <label>Requirements</label>

                <span>
                    <?php echo nl2br(
                        htmlspecialchars(
                            $event["requirements"] ?? "-"
                        )
                    ); ?>
                </span>

            </div>


            <div class="detail">

                <label>Request Status</label>

                <span>

                    <?php

                    if (
                        $event["request_status"] === "approved"
                    ) {

                        echo '<span class="status approved">
                                Approved
                              </span>';

                    } elseif (
                        $event["request_status"] === "correction_required"
                    ) {

                        echo '<span class="status correction">
                                Correction Required
                              </span>';

                    } else {

                        echo '<span class="status pending">
                                Pending
                              </span>';
                    }

                    ?>

                </span>

            </div>


            <?php if (
                $event["request_status"] === "correction_required"
                && !empty($event["correction_reason"])
            ): ?>

                <div class="detail full">

                    <label>Correction Reason</label>

                    <span>
                        <?php echo nl2br(
                            htmlspecialchars(
                                $event["correction_reason"]
                            )
                        ); ?>
                    </span>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         ORGANIZER TEAM
    ====================================================== -->

    <?php if ($organizer_request): ?>

        <div class="card">

            <h2>👥 Organizer Team</h2>


            <div class="details">

                <div class="detail">

                    <label>Team Name</label>

                    <span>
                        <?php echo htmlspecialchars(
                            $organizer_request["team_name"]
                        ); ?>
                    </span>

                </div>


                <div class="detail">

                    <label>Team Request Status</label>

                    <span>

                        <?php

                        if (
                            $organizer_request["status"] === "approved"
                        ) {

                            echo '<span class="status approved">
                                    Approved
                                  </span>';

                        } elseif (
                            $organizer_request["status"] === "correction_required"
                        ) {

                            echo '<span class="status correction">
                                    Correction Required
                                  </span>';

                        } else {

                            echo '<span class="status pending">
                                    Pending
                                  </span>';
                        }

                        ?>

                    </span>

                </div>


                <div class="detail full">

                    <label>Reason / Purpose</label>

                    <span>
                        <?php echo nl2br(
                            htmlspecialchars(
                                $organizer_request["reason"] ?? "-"
                            )
                        ); ?>
                    </span>

                </div>


                <div class="detail">

                    <label>Submitted On</label>

                    <span>
                        <?php echo htmlspecialchars(
                            $organizer_request["created_at"] ?? "-"
                        ); ?>
                    </span>

                </div>

            </div>


            <?php if (!empty($team_members)): ?>

                <h3 style="margin-top:25px;margin-bottom:15px;color:#1e3a8a;">
                    Team Members
                </h3>


                <?php foreach ($team_members as $member): ?>

                    <div class="team-member">

                        <strong>
                            <?php echo htmlspecialchars(
                                $member["name"]
                            ); ?>
                        </strong>

                        <div class="member-role">

                            Role:
                            <?php echo htmlspecialchars(
                                $member["member_role"] ?? "Member"
                            ); ?>

                            <br>

                            Student ID:
                            <?php echo htmlspecialchars(
                                $member["student_id"] ?? "-"
                            ); ?>

                            <br>

                            Department:
                            <?php echo htmlspecialchars(
                                $member["department"] ?? "-"
                            ); ?>

                            <br>

                            Email:
                            <?php echo htmlspecialchars(
                                $member["email"] ?? "-"
                            ); ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STAGE INFORMATION
    ====================================================== -->

    <div class="card">

        <h2>🎭 Stage Booking</h2>

        <?php if ($stage_booking): ?>

            <div class="details">

                <div class="detail">

                    <label>Stage</label>

                    <span>
                        <?php echo htmlspecialchars(
                            $stage_booking["stage_name"] ?? "-"
                        ); ?>
                    </span>

                </div>


                <div class="detail">

                    <label>Venue</label>

                    <span>
                        <?php echo htmlspecialchars(
                            $stage_booking["stage_location"]
                            ?? $event["venue"]
                            ?? "-"
                        ); ?>
                    </span>

                </div>


                <div class="detail">

                    <label>Date</label>

                    <span>
                        <?php echo htmlspecialchars(
                            $stage_booking["booking_date"] ?? "-"
                        ); ?>
                    </span>

                </div>


                <div class="detail">

                    <label>Time</label>

                    <span>

                        <?php echo date(
                            "h:i A",
                            strtotime(
                                $stage_booking["start_time"]
                            )
                        ); ?>

                        -

                        <?php echo date(
                            "h:i A",
                            strtotime(
                                $stage_booking["end_time"]
                            )
                        ); ?>

                    </span>

                </div>


                <div class="detail">

                    <label>Stage Status</label>

                    <span>

                        <?php

                        $booking_status =
                            $stage_booking["booking_status"]
                            ?? "pending";


                        if ($booking_status === "booked") {

                            echo '<span class="status booked">
                                    🔴 Booked
                                  </span>';

                        } elseif (
                            $booking_status === "assigned"
                        ) {

                            echo '<span class="status assigned">
                                    🟠 Assigned
                                  </span>';

                        } elseif (
                            $booking_status === "rejected"
                        ) {

                            echo '<span class="status correction">
                                    ❌ Rejected
                                  </span>';

                        } else {

                            echo '<span class="status pending">
                                    🟡 Pending
                                  </span>';
                        }

                        ?>

                    </span>

                </div>

            </div>

        <?php else: ?>

            <div class="message warning">

                ℹ️ No stage booking has been selected for this event.

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         ORGANIZER INFORMATION
    ====================================================== -->

    <div class="card">

        <h2>👨‍💼 Organizer Information</h2>

        <div class="details">

            <div class="detail">

                <label>Name</label>

                <span>
                    <?php echo htmlspecialchars(
                        $event["organizer_name"] ?? "Unknown"
                    ); ?>
                </span>

            </div>


            <div class="detail">

                <label>Email</label>

                <span>
                    <?php echo htmlspecialchars(
                        $event["organizer_email"] ?? "-"
                    ); ?>
                </span>

            </div>


            <div class="detail">

                <label>Phone</label>

                <span>
                    <?php echo htmlspecialchars(
                        $event["organizer_phone"] ?? "-"
                    ); ?>
                </span>

            </div>


            <div class="detail">

                <label>Department</label>

                <span>
                    <?php echo htmlspecialchars(
                        $event["organizer_department"] ?? "-"
                    ); ?>
                </span>

            </div>

        </div>


        <!-- =================================================
             ADMIN ACTIONS
        ================================================== -->

        <?php if (
            $event["request_status"] === "pending"
            || $event["request_status"] === "correction_required"
        ): ?>

            <?php if (
                $event["request_status"] === "pending"
            ): ?>

                <!-- APPROVE -->

                <div class="actions">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="approve"
                        >

                        <button
                            type="submit"
                            class="btn approve"
                            onclick="return confirm(
                                'Approve this event request?'
                            );"
                        >
                            ✅ Approve Event
                        </button>

                    </form>

                </div>

            <?php endif; ?>


            <!-- CORRECTION -->

            <form
                method="POST"
                id="correctionForm"
            >

                <input
                    type="hidden"
                    name="action"
                    value="correction"
                >


                <div class="correction-section">

                    <h3>
                        ⚠️ Reason for Correction
                    </h3>


                    <div class="dropdown">

                        <button
                            type="button"
                            class="dropdown-button"
                            onclick="toggleDropdown()"
                        >
                            Select correction reasons ▼
                        </button>


                        <div
                            class="dropdown-content"
                            id="reasonDropdown"
                        >

                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Event Description"
                                    onchange="updateSelection()"
                                >

                                Event Description

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Event Date / Time"
                                    onchange="updateSelection()"
                                >

                                Event Date / Time

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Venue Details"
                                    onchange="updateSelection()"
                                >

                                Venue Details

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Event Objective"
                                    onchange="updateSelection()"
                                >

                                Event Objective

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Expected Participants"
                                    onchange="updateSelection()"
                                >

                                Expected Participants

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Guest Details"
                                    onchange="updateSelection()"
                                >

                                Guest Details

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    name="correction_reasons[]"
                                    value="Requirements"
                                    onchange="updateSelection()"
                                >

                                Requirements

                            </label>


                            <label class="checkbox-item">

                                <input
                                    type="checkbox"
                                    id="otherCheckbox"
                                    name="correction_reasons[]"
                                    value="Other"
                                    onchange="toggleOther()"
                                >

                                Other

                            </label>

                        </div>

                    </div>


                    <div
                        class="selected-count"
                        id="selectedCount"
                    >
                        No reasons selected.
                    </div>


                    <div
                        class="other-box"
                        id="otherBox"
                    >

                        <label>
                            Other Reason
                        </label>

                        <textarea
                            name="other_reason"
                            id="otherReason"
                            placeholder="Please specify the correction reason..."
                        ></textarea>

                    </div>


                    <div class="actions">

                        <button
                            type="submit"
                            class="btn correction-btn"
                            onclick="return validateCorrection()"
                        >
                            ↩️ Return for Correction
                        </button>

                    </div>

                </div>

            </form>

        <?php endif; ?>


        <div class="actions">

            <a
                href="admin_dashboard.php"
                class="btn back"
            >
                ← Back to Dashboard
            </a>

        </div>

    </div>


</div>


<script>

/* =========================================================
   DROPDOWN
========================================================= */

function toggleDropdown() {

    const dropdown =
        document.getElementById("reasonDropdown");

    dropdown.classList.toggle("show");
}


/* =========================================================
   SELECTED COUNT
========================================================= */

function updateSelection() {

    const checkboxes =
        document.querySelectorAll(
            'input[name="correction_reasons[]"]'
        );

    let count = 0;

    checkboxes.forEach(function(checkbox) {

        if (checkbox.checked) {
            count++;
        }

    });


    const countText =
        document.getElementById("selectedCount");


    if (count === 0) {

        countText.innerText =
            "No reasons selected.";

    } else {

        countText.innerText =
            count + " reason(s) selected.";
    }
}


/* =========================================================
   OTHER
========================================================= */

function toggleOther() {

    const checkbox =
        document.getElementById("otherCheckbox");

    const otherBox =
        document.getElementById("otherBox");

    const otherReason =
        document.getElementById("otherReason");


    if (checkbox.checked) {

        otherBox.classList.add("show");

        otherReason.required = true;

    } else {

        otherBox.classList.remove("show");

        otherReason.required = false;

        otherReason.value = "";
    }


    updateSelection();
}


/* =========================================================
   VALIDATE CORRECTION
========================================================= */

function validateCorrection() {

    const checkboxes =
        document.querySelectorAll(
            'input[name="correction_reasons[]"]'
        );

    let selected = false;


    checkboxes.forEach(function(checkbox) {

        if (checkbox.checked) {
            selected = true;
        }

    });


    if (!selected) {

        alert(
            "Please select at least one correction reason."
        );

        return false;
    }


    const otherCheckbox =
        document.getElementById("otherCheckbox");


    if (otherCheckbox.checked) {

        const otherReason =
            document
            .getElementById("otherReason")
            .value
            .trim();


        if (otherReason === "") {

            alert(
                "Please specify the Other reason."
            );

            return false;
        }
    }


    return confirm(
        "Return this event to the organizer for correction?"
    );
}


/* =========================================================
   CLOSE DROPDOWN OUTSIDE
========================================================= */

document.addEventListener(
    "click",
    function(event) {

        const dropdown =
            document.querySelector(".dropdown");

        if (
            dropdown
            && !dropdown.contains(event.target)
        ) {

            document
                .getElementById("reasonDropdown")
                .classList.remove("show");
        }

    }
);

</script>

</body>

</html>