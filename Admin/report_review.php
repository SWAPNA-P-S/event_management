<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.html");
    exit();
}

$report_id = intval($_GET["report_id"] ?? $_POST["report_id"] ?? 0);

if ($report_id <= 0) {
    die("Invalid report ID.");
}

$message = "";
$message_type = "";

/* =========================
   APPROVE / CORRECTION
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "approve") {

        $required_items = [
            "programme_details",
            "detailed_report",
            "resource_person",
            "date_time_duration",
            "beneficiary_count",
            "venue",
            "feedback_analysis",
            "organizing_department",
            "coordinator_details",
            "associating_agency",
            "fund_details",
            "outcome",
            "photo_gallery",
            "attendance_register",
            "publicity_material",
            "sample_certificate",
            "invitation",
            "signatures"
        ];

        $all_checked = true;

        foreach ($required_items as $item) {
            if (!isset($_POST[$item])) {
                $all_checked = false;
                break;
            }
        }

        if (!$all_checked) {

            $message = "Please complete all checklist items before approving.";
            $message_type = "error";

        } else {

            $stmt = $conn->prepare("
                UPDATE event_reports
                SET status = 'approved',
                    correction_reason = NULL,
                    reviewed_at = NOW()
                WHERE report_id = ?
            ");

            $stmt->bind_param("i", $report_id);

            if ($stmt->execute()) {
                $message = "Event report approved successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to approve report.";
                $message_type = "error";
            }

            $stmt->close();
        }

    } elseif ($action === "correction") {

        $items = [
            "programme_details" => "Programme Details",
            "detailed_report" => "Detailed Report",
            "resource_person" => "Resource Person",
            "date_time_duration" => "Date / Time / Duration",
            "beneficiary_count" => "Number of Beneficiaries",
            "venue" => "Venue / Platform",
            "feedback_analysis" => "Feedback Analysis",
            "organizing_department" => "Organizing Department / Cell",
            "coordinator_details" => "Coordinator Details",
            "associating_agency" => "Associating Agency",
            "fund_details" => "Fund Details",
            "outcome" => "Outcome / Benefit",
            "photo_gallery" => "Photo Gallery",
            "attendance_register" => "Attendance Register",
            "publicity_material" => "Publicity Material",
            "sample_certificate" => "Sample Certificate",
            "invitation" => "Invitation / Correspondence",
            "signatures" => "Coordinator + Principal Signatures"
        ];

        $unchecked = [];

        foreach ($items as $key => $label) {
            if (!isset($_POST[$key])) {
                $unchecked[] = $label;
            }
        }

        $admin_comment = trim($_POST["admin_comment"] ?? "");

        $reason = "";

        if (!empty($unchecked)) {
            $reason .= "Please correct/provide the following:\n";

            foreach ($unchecked as $item) {
                $reason .= "- " . $item . "\n";
            }
        }

        if ($admin_comment !== "") {
            $reason .= "\nAdmin Comment:\n" . $admin_comment;
        }

        if ($reason === "") {
            $reason = "Please review and improve the submitted report.";
        }

        $stmt = $conn->prepare("
            UPDATE event_reports
            SET status = 'correction_required',
                correction_reason = ?,
                reviewed_at = NOW()
            WHERE report_id = ?
        ");

        $stmt->bind_param("si", $reason, $report_id);

        if ($stmt->execute()) {
            $message = "Report returned to organizer for correction.";
            $message_type = "success";
        } else {
            $message = "Failed to return report.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================
   FETCH REPORT
========================= */

$stmt = $conn->prepare("
    SELECT
        er.*,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        u.name AS organizer_name,
        u.email AS organizer_email
    FROM event_reports er
    INNER JOIN events e
        ON er.event_id = e.event_id
    INNER JOIN users u
        ON er.organizer_id = u.id
    WHERE er.report_id = ?
");

$stmt->bind_param("i", $report_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Report not found.");
}

$report = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Review Event Report</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6fb;
}

.container {
    max-width: 1100px;
    margin: 35px auto;
    padding: 20px;
}

.back {
    color: #2563eb;
    text-decoration: none;
    display: inline-block;
    margin-bottom: 20px;
}

h1 {
    color: #172554;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.card h2 {
    color: #1e3a8a;
    margin-top: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.info-box {
    background: #f8fafc;
    padding: 14px;
    border-radius: 8px;
}

.report-text {
    background: #f8fafc;
    padding: 18px;
    border-radius: 8px;
    line-height: 1.6;
    white-space: normal;
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

.warning {
    background: #fff7ed;
    border-left: 4px solid #f97316;
    padding: 15px;
    margin-bottom: 20px;
}

.checklist label {
    display: block;
    padding: 12px;
    margin-bottom: 8px;
    background: #f8fafc;
    border-radius: 7px;
    cursor: pointer;
}

.checklist input {
    margin-right: 10px;
}

textarea {
    width: 100%;
    box-sizing: border-box;
    min-height: 120px;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-top: 10px;
    resize: vertical;
}

.buttons {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

button {
    padding: 12px 22px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
}

.approve {
    background: #16a34a;
    color: white;
}

.correction {
    background: #f97316;
    color: white;
}

button:hover {
    opacity: 0.9;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 13px;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.correction-required {
    background: #fee2e2;
    color: #991b1b;
}

.reason {
    background: #fff7ed;
    padding: 15px;
    border-left: 4px solid #f97316;
    white-space: pre-line;
}

@media (max-width: 700px) {

    .info-grid {
        grid-template-columns: 1fr;
    }

    .buttons {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="container">

<a href="reports.php" class="back">← Back to Reports</a>

<h1>Review Event Report</h1>

<?php if ($message !== ""): ?>

<div class="message <?php echo $message_type; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>

<?php endif; ?>


<!-- EVENT INFORMATION -->

<div class="card">

<h2>Event Information</h2>

<div class="info-grid">

<div class="info-box">
<strong>Event Name</strong><br>
<?php echo htmlspecialchars($report["event_name"]); ?>
</div>

<div class="info-box">
<strong>Event Date</strong><br>
<?php echo date("d M Y", strtotime($report["event_date"])); ?>
</div>

<div class="info-box">
<strong>Time</strong><br>
<?php echo date("h:i A", strtotime($report["start_time"])); ?>
-
<?php echo date("h:i A", strtotime($report["end_time"])); ?>
</div>

<div class="info-box">
<strong>Venue</strong><br>
<?php echo htmlspecialchars($report["venue"]); ?>
</div>

<div class="info-box">
<strong>Organizer</strong><br>
<?php echo htmlspecialchars($report["organizer_name"]); ?>
</div>

<div class="info-box">
<strong>Email</strong><br>
<?php echo htmlspecialchars($report["organizer_email"]); ?>
</div>

</div>

</div>


<!-- STATUS -->

<div class="card">

<h2>Report Status</h2>

<?php if ($report["status"] === "pending"): ?>

<span class="status pending">Pending Review</span>

<?php elseif ($report["status"] === "approved"): ?>

<span class="status approved">Approved</span>

<?php elseif ($report["status"] === "correction_required"): ?>

<span class="status correction-required">
Correction Required
</span>

<?php endif; ?>

</div>


<!-- PREVIOUS CORRECTION -->

<?php if (!empty($report["correction_reason"])): ?>

<div class="card">

<h2>Previous Correction Request</h2>

<div class="reason">
<?php echo htmlspecialchars($report["correction_reason"]); ?>
</div>

</div>

<?php endif; ?>


<!-- DETAILED REPORT -->

<div class="card">

<h2>Detailed Report</h2>

<div class="report-text">

<?php
echo nl2br(htmlspecialchars($report["detailed_report"]));
?>

</div>

</div>


<!-- CHECKLIST -->

<div class="card">

<h2>Report Verification Checklist</h2>

<p>
Check each item after verifying the report.
All items must be checked before approval.
</p>

<form method="POST" id="reviewForm">

<input type="hidden"
       name="report_id"
       value="<?php echo $report_id; ?>">


<div class="checklist">

<label>
<input type="checkbox" name="programme_details">
Programme Details in Nutshell
</label>

<label>
<input type="checkbox" name="detailed_report">
Detailed Report
</label>

<label>
<input type="checkbox" name="resource_person">
Resource Person Details
</label>

<label>
<input type="checkbox" name="date_time_duration">
Date / Time / Duration
</label>

<label>
<input type="checkbox" name="beneficiary_count">
Number of Beneficiaries
</label>

<label>
<input type="checkbox" name="venue">
Venue / Platform
</label>

<label>
<input type="checkbox" name="feedback_analysis">
Feedback Analysis
</label>

<label>
<input type="checkbox" name="organizing_department">
Organizing Department / Cell
</label>

<label>
<input type="checkbox" name="coordinator_details">
Coordinator Details
</label>

<label>
<input type="checkbox" name="associating_agency">
Associating Agency
</label>

<label>
<input type="checkbox" name="fund_details">
Fund Details
</label>

<label>
<input type="checkbox" name="outcome">
Outcome / Benefit
</label>

<label>
<input type="checkbox" name="photo_gallery">
Photo Gallery
</label>

<label>
<input type="checkbox" name="attendance_register">
Signed Attendance Register
</label>

<label>
<input type="checkbox" name="publicity_material">
Publicity Material
</label>

<label>
<input type="checkbox" name="sample_certificate">
Sample Certificate
</label>

<label>
<input type="checkbox" name="invitation">
Invitation / Correspondence
</label>

<label>
<input type="checkbox" name="signatures">
Coordinator + Principal Signatures
</label>

</div>


<h3>Admin Comment</h3>

<textarea
    name="admin_comment"
    placeholder="Add any additional correction instructions..."
></textarea>


<div class="buttons">

<button
    type="submit"
    name="action"
    value="approve"
    class="approve"
    onclick="return confirm('Are you sure you want to approve this report?');"
>
    Approve Report
</button>

<button
    type="submit"
    name="action"
    value="correction"
    class="correction"
    onclick="return confirm('Return this report to the organizer for correction?');"
>
    Return for Correction
</button>

</div>

</form>

</div>

</div>

</body>

</html>

<?php
$conn->close();
?>