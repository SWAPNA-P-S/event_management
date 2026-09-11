<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "organizer") {
    header("Location: ../login.html");
    exit();
}

$organizer_id = $_SESSION["user_id"];
$message = "";
$message_type = "";

/* =========================
   SUBMIT / RESUBMIT REPORT
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = intval($_POST["event_id"] ?? 0);
    $detailed_report = trim($_POST["detailed_report"] ?? "");

    if ($event_id <= 0 || $detailed_report === "") {
        $message = "Please select an event and enter the detailed report.";
        $message_type = "error";
    } else {

        /* Check event belongs to this organizer and is completed */
        $stmt = $conn->prepare("
            SELECT event_id, event_name, event_date, end_time
            FROM events
            WHERE event_id = ?
              AND organizer_id = ?
              AND (
                    event_date < CURDATE()
                    OR
                    (event_date = CURDATE() AND end_time <= CURTIME())
                  )
        ");

        $stmt->bind_param("ii", $event_id, $organizer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {

            $message = "This event is not available for report submission.";
            $message_type = "error";

        } else {

            /* Check whether a report already exists */
            $check = $conn->prepare("
                SELECT report_id, status
                FROM event_reports
                WHERE event_id = ?
                  AND organizer_id = ?
                LIMIT 1
            ");

            $check->bind_param("ii", $event_id, $organizer_id);
            $check->execute();
            $existing = $check->get_result();

            if ($existing->num_rows === 1) {

                $report = $existing->fetch_assoc();
                $report_id = $report["report_id"];
                $status = $report["status"];

                if ($status === "correction_required") {

                    $update = $conn->prepare("
                        UPDATE event_reports
                        SET detailed_report = ?,
                            status = 'pending',
                            correction_reason = NULL,
                            submitted_at = NOW(),
                            reviewed_at = NULL
                        WHERE report_id = ?
                          AND organizer_id = ?
                    ");

                    $update->bind_param(
                        "sii",
                        $detailed_report,
                        $report_id,
                        $organizer_id
                    );

                    if ($update->execute()) {
                        $message = "Report corrected and resubmitted successfully.";
                        $message_type = "success";
                    } else {
                        $message = "Failed to resubmit report.";
                        $message_type = "error";
                    }

                    $update->close();

                } elseif ($status === "pending") {

                    $message = "This report is already submitted and waiting for admin review.";
                    $message_type = "info";

                } elseif ($status === "approved") {

                    $message = "This report has already been approved.";
                    $message_type = "info";

                } else {

                    $message = "This report already exists.";
                    $message_type = "info";
                }

            } else {

                /* First-time submission */
                $insert = $conn->prepare("
                    INSERT INTO event_reports
                    (
                        event_id,
                        organizer_id,
                        detailed_report,
                        status,
                        submitted_at
                    )
                    VALUES (?, ?, ?, 'pending', NOW())
                ");

                $insert->bind_param(
                    "iis",
                    $event_id,
                    $organizer_id,
                    $detailed_report
                );

                if ($insert->execute()) {
                    $message = "Event report submitted successfully.";
                    $message_type = "success";
                } else {
                    $message = "Failed to submit event report.";
                    $message_type = "error";
                }

                $insert->close();
            }

            $check->close();
        }

        $stmt->close();
    }
}


/* =========================
   GET COMPLETED EVENTS
========================= */

$stmt = $conn->prepare("
    SELECT 
        e.event_id,
        e.event_name,
        e.event_date,
        e.venue,
        er.report_id,
        er.status AS report_status,
        er.correction_reason
    FROM events e
    LEFT JOIN event_reports er
        ON e.event_id = er.event_id
        AND er.organizer_id = ?
    WHERE e.organizer_id = ?
      AND (
            e.event_date < CURDATE()
            OR
            (e.event_date = CURDATE() AND e.end_time <= CURTIME())
          )
    ORDER BY e.event_date DESC
");

$stmt->bind_param("ii", $organizer_id, $organizer_id);
$stmt->execute();
$events = $stmt->get_result();


/* =========================
   GET SUBMITTED REPORTS
========================= */

$reports_stmt = $conn->prepare("
    SELECT
        er.report_id,
        er.event_id,
        er.detailed_report,
        er.status,
        er.correction_reason,
        er.submitted_at,
        e.event_name,
        e.event_date
    FROM event_reports er
    INNER JOIN events e
        ON er.event_id = e.event_id
    WHERE er.organizer_id = ?
    ORDER BY er.submitted_at DESC
");

$reports_stmt->bind_param("i", $organizer_id);
$reports_stmt->execute();
$reports = $reports_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Event Reports - Organizer</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            color: #172554;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .card h2 {
            margin-top: 0;
            color: #1e3a8a;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        select,
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        textarea {
            min-height: 180px;
            resize: vertical;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .info {
            background: #dbeafe;
            color: #1e40af;
        }

        .report-item {
            border: 1px solid #ddd;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .report-item h3 {
            margin: 0 0 8px;
            color: #172554;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
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

        .reason {
            background: #fff7ed;
            border-left: 4px solid #f97316;
            padding: 12px;
            margin-top: 12px;
            color: #9a3412;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .empty {
            color: #777;
        }
    </style>
</head>

<body>

<div class="container">

    <a href="organizer_dashboard.php" class="back">← Back to Dashboard</a>

    <h1>Event Reports</h1>
    <p class="subtitle">
        Submit detailed reports for your completed events.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>


    <!-- =========================
         REPORT SUBMISSION
    ========================== -->

    <div class="card">

        <h2>Submit Event Report</h2>

        <?php if ($events->num_rows > 0): ?>

            <form method="POST">

                <label for="event_id">
                    Select Completed Event
                </label>

                <select name="event_id" id="event_id" required>

                    <option value="">
                        -- Select Event --
                    </option>

                    <?php while ($event = $events->fetch_assoc()): ?>

                        <option value="<?php echo $event["event_id"]; ?>">

                            <?php echo htmlspecialchars($event["event_name"]); ?>

                            -
                            <?php echo date("d M Y", strtotime($event["event_date"])); ?>

                            <?php if ($event["report_status"] === "pending"): ?>
                                (Report Pending)
                            <?php elseif ($event["report_status"] === "approved"): ?>
                                (Report Approved)
                            <?php elseif ($event["report_status"] === "correction_required"): ?>
                                (Correction Required)
                            <?php endif; ?>

                        </option>

                    <?php endwhile; ?>

                </select>


                <label for="detailed_report">
                    Detailed Report
                </label>

                <textarea
                    name="detailed_report"
                    id="detailed_report"
                    placeholder="Enter the detailed report of the event..."
                    required
                ></textarea>


                <button type="submit">
                    Submit Report
                </button>

            </form>

        <?php else: ?>

            <p class="empty">
                No completed events are available for report submission.
            </p>

        <?php endif; ?>

    </div>


    <!-- =========================
         SUBMITTED REPORTS
    ========================== -->

    <div class="card">

        <h2>My Submitted Reports</h2>

        <?php if ($reports->num_rows > 0): ?>

            <?php while ($report = $reports->fetch_assoc()): ?>

                <div class="report-item">

                    <h3>
                        <?php echo htmlspecialchars($report["event_name"]); ?>
                    </h3>

                    <p>
                        <strong>Event Date:</strong>
                        <?php echo date("d M Y", strtotime($report["event_date"])); ?>
                    </p>

                    <p>
                        <strong>Submitted:</strong>
                        <?php echo date("d M Y, h:i A", strtotime($report["submitted_at"])); ?>
                    </p>


                    <?php if ($report["status"] === "pending"): ?>

                        <span class="status pending">
                            Pending Review
                        </span>

                    <?php elseif ($report["status"] === "approved"): ?>

                        <span class="status approved">
                            Approved
                        </span>

                    <?php elseif ($report["status"] === "correction_required"): ?>

                        <span class="status correction">
                            Correction Required
                        </span>

                        <?php if (!empty($report["correction_reason"])): ?>

                            <div class="reason">

                                <strong>Admin Correction Request:</strong>

                                <br><br>

                                <?php
                                echo nl2br(
                                    htmlspecialchars($report["correction_reason"])
                                );
                                ?>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty">
                No reports submitted yet.
            </p>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
$reports_stmt->close();
$conn->close();
?>