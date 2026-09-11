<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.html");
    exit();
}

if (!isset($_GET["request_id"]) || !is_numeric($_GET["request_id"])) {
    die("Invalid organizer request.");
}

$request_id = (int) $_GET["request_id"];

$message = "";
$message_type = "";

/* =========================================================
   HANDLE ADMIN ACTION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /* ---------- APPROVE TEAM + FIRST EVENT ---------- */

    if ($action === "approve") {

        $conn->begin_transaction();

        try {

            // Approve organizer request
            $stmt = $conn->prepare("
                UPDATE organizer_requests
                SET status = 'approved',
                    reviewed_at = NOW()
                WHERE request_id = ?
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            // Approve first event linked with this request
            $stmt = $conn->prepare("
                UPDATE events
                SET request_status = 'approved',
                    status = 'approved'
                WHERE organizer_request_id = ?
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message = "Organizer team and first event approved successfully!";
            $message_type = "success";

        } catch (Exception $e) {

            $conn->rollback();

            $message = "Approval failed. Please try again.";
            $message_type = "error";
        }
    }


    /* ---------- RETURN FOR CORRECTION ---------- */

    elseif ($action === "correction") {

        $reason = trim($_POST["correction_reason"] ?? "");

        if ($reason === "") {

            $message = "Please enter a correction reason.";
            $message_type = "error";

        } else {

            $conn->begin_transaction();

            try {

                // Return organizer request for correction
                $stmt = $conn->prepare("
                    UPDATE organizer_requests
                    SET status = 'correction_required',
                        reviewed_at = NOW()
                    WHERE request_id = ?
                ");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $stmt->close();


                // Return linked first event for correction
                $stmt = $conn->prepare("
                    UPDATE events
                    SET request_status = 'correction_required',
                        correction_reason = ?
                    WHERE organizer_request_id = ?
                ");
                $stmt->bind_param("si", $reason, $request_id);
                $stmt->execute();
                $stmt->close();


                $conn->commit();

                $message = "Request returned for correction successfully.";
                $message_type = "success";

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Could not return the request for correction.";
                $message_type = "error";
            }
        }
    }
}


/* =========================================================
   FETCH ORGANIZER REQUEST
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        r.request_id,
        r.requested_by,
        r.team_name,
        r.reason,
        r.status,
        r.created_at,
        r.reviewed_at,
        u.name AS leader_name,
        u.email AS leader_email,
        u.phone AS leader_phone,
        u.student_id AS leader_student_id
    FROM organizer_requests r
    LEFT JOIN users u ON r.requested_by = u.id
    WHERE r.request_id = ?
");

$stmt->bind_param("i", $request_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    die("Organizer request not found.");
}


/* =========================================================
   FETCH TEAM MEMBERS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        tm.team_member_id,
        tm.user_id,
        tm.member_role,
        tm.joined_at,
        u.name,
        u.email,
        u.phone,
        u.student_id
    FROM organizer_team_members tm
    LEFT JOIN users u ON tm.user_id = u.id
    WHERE tm.request_id = ?
    ORDER BY tm.team_member_id ASC
");

$stmt->bind_param("i", $request_id);
$stmt->execute();

$members = $stmt->get_result();

$stmt->close();


/* =========================================================
   FETCH FIRST EVENT
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
        objective,
        expected_participants,
        guest_details,
        requirements,
        request_status,
        correction_reason
    FROM events
    WHERE organizer_request_id = ?
    ORDER BY event_id ASC
    LIMIT 1
");

$stmt->bind_param("i", $request_id);
$stmt->execute();

$event = $stmt->get_result()->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Organizer Request Review</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .header {
            background: #111827;
            color: white;
            padding: 20px 35px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 30px auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }

        .card {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .card h2 {
            margin-top: 0;
            color: #111827;
            border-bottom: 1px solid #ddd;
            padding-bottom: 12px;
        }

        .info {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 12px;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .status {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .approved {
            background: #d4edda;
            color: #155724;
        }

        .correction_required {
            background: #f8d7da;
            color: #721c24;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f3f4f6;
        }

        .reason-box {
            background: #f9fafb;
            border-left: 4px solid #6366f1;
            padding: 15px;
            line-height: 1.6;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #bbb;
            border-radius: 8px;
            resize: vertical;
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin-top: 10px;
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .approve-btn {
            background: #16a34a;
            color: white;
        }

        .correction-btn {
            background: #dc2626;
            color: white;
        }

        .cancel-btn {
            background: #6b7280;
            color: white;
        }

        .correction-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fff7f7;
            border: 1px solid #fecaca;
            border-radius: 10px;
        }

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }

        .no-event {
            color: #777;
            font-style: italic;
        }

        @media (max-width: 700px) {

            .container {
                width: 94%;
            }

            .info {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            table {
                font-size: 13px;
            }

            th,
            td {
                padding: 8px;
            }
        }

    </style>

</head>


<body>


<div class="header">

    <h1>Organizer Request Review</h1>

</div>


<div class="container">


    <a href="admin_dashboard.php" class="back">
        ← Back to Admin Dashboard
    </a>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo ($message_type === "success") ? "success-message" : "error-message"; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ORGANIZER REQUEST
         ===================================================== -->

    <div class="card">

        <h2>👥 Organizer Team Request</h2>

        <div class="info">

            <div class="label">Request ID</div>
            <div><?php echo htmlspecialchars($request["request_id"]); ?></div>


            <div class="label">Team Name</div>
            <div><?php echo htmlspecialchars($request["team_name"]); ?></div>


            <div class="label">Leader</div>
            <div><?php echo htmlspecialchars($request["leader_name"]); ?></div>


            <div class="label">Leader Email</div>
            <div><?php echo htmlspecialchars($request["leader_email"]); ?></div>


            <div class="label">Leader Phone</div>
            <div><?php echo htmlspecialchars($request["leader_phone"]); ?></div>


            <div class="label">Student ID</div>
            <div><?php echo htmlspecialchars($request["leader_student_id"]); ?></div>


            <div class="label">Reason</div>

            <div class="reason-box">
                <?php echo nl2br(htmlspecialchars($request["reason"])); ?>
            </div>


            <div class="label">Submitted On</div>

            <div>
                <?php echo htmlspecialchars($request["created_at"]); ?>
            </div>


            <div class="label">Status</div>

            <div>

                <span class="status <?php echo htmlspecialchars($request["status"]); ?>">

                    <?php echo htmlspecialchars(str_replace("_", " ", $request["status"])); ?>

                </span>

            </div>

        </div>

    </div>



    <!-- =====================================================
         TEAM MEMBERS
         ===================================================== -->

    <div class="card">

        <h2>👨‍👩‍👧 Team Members</h2>


        <?php if ($members->num_rows > 0): ?>

            <table>

                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                </tr>


                <?php $count = 1; ?>

                <?php while ($member = $members->fetch_assoc()): ?>

                    <tr>

                        <td><?php echo $count++; ?></td>

                        <td>
                            <?php echo htmlspecialchars($member["name"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($member["student_id"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($member["email"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($member["phone"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($member["member_role"]); ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            </table>

        <?php else: ?>

            <p class="no-event">
                No team members found for this request.
            </p>

        <?php endif; ?>

    </div>



    <!-- =====================================================
         FIRST EVENT
         ===================================================== -->

    <div class="card">

        <h2>🎫 First Event Request</h2>


        <?php if ($event): ?>

            <div class="info">

                <div class="label">Event ID</div>
                <div>
                    <?php echo htmlspecialchars($event["event_id"]); ?>
                </div>


                <div class="label">Event Name</div>
                <div>
                    <?php echo htmlspecialchars($event["event_name"]); ?>
                </div>


                <div class="label">Description</div>
                <div>
                    <?php echo nl2br(htmlspecialchars($event["description"])); ?>
                </div>


                <div class="label">Event Date</div>
                <div>
                    <?php echo htmlspecialchars($event["event_date"]); ?>
                </div>


                <div class="label">Start Time</div>
                <div>
                    <?php echo htmlspecialchars($event["start_time"]); ?>
                </div>


                <div class="label">End Time</div>
                <div>
                    <?php echo htmlspecialchars($event["end_time"]); ?>
                </div>


                <div class="label">Venue</div>
                <div>
                    <?php echo htmlspecialchars($event["venue"]); ?>
                </div>


                <div class="label">Event Type</div>
                <div>
                    <?php echo htmlspecialchars($event["event_type"]); ?>
                </div>


                <div class="label">Fee</div>
                <div>
                    ₹<?php echo htmlspecialchars($event["fee"]); ?>
                </div>


                <div class="label">Objective</div>
                <div>
                    <?php echo nl2br(htmlspecialchars($event["objective"])); ?>
                </div>


                <div class="label">Expected Participants</div>
                <div>
                    <?php echo htmlspecialchars($event["expected_participants"]); ?>
                </div>


                <div class="label">Guest Details</div>
                <div>
                    <?php echo nl2br(htmlspecialchars($event["guest_details"])); ?>
                </div>


                <div class="label">Requirements</div>
                <div>
                    <?php echo nl2br(htmlspecialchars($event["requirements"])); ?>
                </div>


                <div class="label">Event Status</div>

                <div>

                    <span class="status <?php echo htmlspecialchars($event["request_status"]); ?>">

                        <?php
                        echo htmlspecialchars(
                            str_replace("_", " ", $event["request_status"])
                        );
                        ?>

                    </span>

                </div>


                <?php if (!empty($event["correction_reason"])): ?>

                    <div class="label">Previous Correction Reason</div>

                    <div class="reason-box">

                        <?php
                        echo nl2br(
                            htmlspecialchars($event["correction_reason"])
                        );
                        ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <p class="no-event">
                No first event is linked with this organizer request yet.
            </p>

        <?php endif; ?>

    </div>



    <!-- =====================================================
         ADMIN ACTIONS
         ===================================================== -->

    <?php if ($request["status"] === "pending"): ?>

        <div class="card">

            <h2>⚙️ Admin Action</h2>


            <div class="actions">

                <!-- APPROVE -->

                <form method="POST"
                      onsubmit="return confirm('Are you sure you want to approve this team and their first event?');">

                    <input type="hidden"
                           name="action"
                           value="approve">

                    <button type="submit"
                            class="approve-btn">

                        ✅ Approve Team & First Event

                    </button>

                </form>


                <!-- SHOW CORRECTION FORM -->

                <button type="button"
                        class="correction-btn"
                        onclick="showCorrectionForm()">

                    🔴 Return for Correction

                </button>

            </div>



            <!-- CORRECTION FORM -->

            <div id="correctionForm"
                 class="correction-form">

                <form method="POST"
                      onsubmit="return validateCorrection();">

                    <input type="hidden"
                           name="action"
                           value="correction">


                    <label>

                        <strong>
                            Correction Reason *
                        </strong>

                    </label>


                    <textarea
                        id="correctionReason"
                        name="correction_reason"
                        placeholder="Enter the exact reason for correction..."
                    ></textarea>


                    <div class="actions">

                        <button type="submit"
                                class="correction-btn">

                            Submit Correction

                        </button>


                        <button type="button"
                                class="cancel-btn"
                                onclick="hideCorrectionForm()">

                            Cancel

                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>


</div>


<script>

function showCorrectionForm() {

    document.getElementById("correctionForm").style.display = "block";

    document.getElementById("correctionReason").focus();

}


function hideCorrectionForm() {

    document.getElementById("correctionForm").style.display = "none";

}


function validateCorrection() {

    const reason =
        document.getElementById("correctionReason").value.trim();

    if (reason === "") {

        alert("Please enter a correction reason.");

        document.getElementById("correctionReason").focus();

        return false;
    }

    return true;

}

</script>


</body>

</html>