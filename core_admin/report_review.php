<?php

session_start();

require_once "../db.php";


/* =========================================================
   CORE ADMIN ACCESS
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$role = $_SESSION["role"] ?? "";

if ($role !== "core_admin") {
    header("Location: ../student_dashboard.php");
    exit();
}


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   REPORT ID
========================================================= */

$report_id = isset($_GET["report_id"])
    ? intval($_GET["report_id"])
    : 0;

if ($report_id <= 0) {
    die("Invalid report ID.");
}


/* =========================================================
   REPORT ACTION
========================================================= */

$message = "";
$message_type = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    $posted_report_id = intval(
        $_POST["report_id"] ?? 0
    );

    if ($posted_report_id !== $report_id) {

        die("Invalid report request.");

    }


    /* =====================================================
       FETCH CURRENT REPORT STATUS
    ===================================================== */

    $status_stmt = $conn->prepare("
        SELECT
            report_id,
            event_id,
            organizer_id,
            status
        FROM event_reports
        WHERE report_id = ?
        LIMIT 1
    ");

    $status_stmt->bind_param(
        "i",
        $report_id
    );

    $status_stmt->execute();

    $status_result =
        $status_stmt->get_result();

    $current_report =
        $status_result->fetch_assoc();

    $status_stmt->close();


    if (!$current_report) {

        die("Report not found.");

    }


    $current_status =
        $current_report["status"];


    /* =====================================================
       FINAL APPROVAL
    ===================================================== */

    if ($action === "approve") {

        /*
         * Only Department Admin approved reports
         * can reach Core Admin.
         */

        if ($current_status !== "admin_approved") {

            $message =
                "This report is not waiting for Core Admin final review.";

            $message_type = "error";

        } else {

            $update_stmt = $conn->prepare("
                UPDATE event_reports
                SET
                    status = 'approved',
                    correction_reason = NULL,
                    reviewed_at = NOW()
                WHERE report_id = ?
                  AND status = 'admin_approved'
            ");

            $update_stmt->bind_param(
                "i",
                $report_id
            );

            if ($update_stmt->execute()) {

                $message =
                    "Report has been finally approved by Core Admin.";

                $message_type = "success";

            } else {

                $message =
                    "Unable to approve the report.";

                $message_type = "error";

            }

            $update_stmt->close();

        }

    }


    /* =====================================================
       RETURN FOR CORRECTION
    ===================================================== */

    elseif ($action === "correction") {

        $correction_reason =
            trim(
                $_POST["correction_reason"] ?? ""
            );


        if ($current_status !== "admin_approved") {

            $message =
                "Only Department Admin approved reports can be returned from Core Admin review.";

            $message_type = "error";

        }
        elseif ($correction_reason === "") {

            $message =
                "Please enter the correction reason.";

            $message_type = "error";

        }
        else {

            $update_stmt = $conn->prepare("
                UPDATE event_reports
                SET
                    status = 'correction_required',
                    correction_reason = ?,
                    reviewed_at = NOW()
                WHERE report_id = ?
                  AND status = 'admin_approved'
            ");

            $update_stmt->bind_param(
                "si",
                $correction_reason,
                $report_id
            );

            if ($update_stmt->execute()) {

                $message =
                    "Report returned to the organizer for correction.";

                $message_type = "success";

            } else {

                $message =
                    "Unable to return the report for correction.";

                $message_type = "error";

            }

            $update_stmt->close();

        }

    }

}


/* =========================================================
   FETCH REPORT
========================================================= */

$stmt = $conn->prepare("
    SELECT
        er.report_id,
        er.event_id,
        er.organizer_id,
        er.detailed_report,
        er.photo_gallery,
        er.attendance_register,
        er.publicity_material,
        er.sample_certificate,
        er.invitation_correspondence,
        er.resource_person_profile,
        er.feedback_analysis,
        er.coordinator_signature,
        er.principal_signature,
        er.status,
        er.correction_reason,
        er.submitted_at,
        er.reviewed_at,

        e.event_name,
        e.event_type,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.objective,
        e.expected_participants,
        e.guest_details,
        e.requirements,

        u.name AS organizer_name,
        u.email AS organizer_email,
        u.department AS organizer_department

    FROM event_reports er

    INNER JOIN events e
        ON er.event_id = e.event_id

    INNER JOIN users u
        ON er.organizer_id = u.id

    WHERE er.report_id = ?

    LIMIT 1
");

$stmt->bind_param(
    "i",
    $report_id
);

$stmt->execute();

$result = $stmt->get_result();

$report = $result->fetch_assoc();

$stmt->close();


if (!$report) {

    die("Report not found.");

}


/* =========================================================
   STATUS LABEL
========================================================= */

$status = $report["status"] ?? "pending";


if ($status === "admin_approved") {

    $status_label =
        "Department Approved – Waiting for Core Admin";

    $status_class = "admin-approved";

}
elseif ($status === "approved") {

    $status_label =
        "Final Approved";

    $status_class = "approved";

}
elseif ($status === "correction_required") {

    $status_label =
        "Correction Required";

    $status_class = "correction";

}
else {

    $status_label =
        ucfirst(
            str_replace(
                "_",
                " ",
                $status
            )
        );

    $status_class = "pending";

}


/* =========================================================
   FILE DISPLAY HELPER
========================================================= */

function fileLink($file, $label)
{

    if (empty($file)) {

        return '
            <span class="not-uploaded">
                Not uploaded
            </span>
        ';

    }

    $safe_file = h($file);

    $file_url =
        "../" . ltrim(
            $file,
            "/"
        );

    return '
        <a
            href="' . h($file_url) . '"
            target="_blank"
            class="file-link"
        >
            ' . h($label) . '
        </a>
    ';

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
    Core Admin Report Review
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


/* =========================================================
   HEADER
========================================================= */

.header {

    background: #0B1F3A;

    color: white;

    padding: 22px 30px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.12);
}


.header h1 {

    margin: 0;

    font-size: 25px;
}


.header p {

    margin: 7px 0 0;

    font-size: 14px;

    opacity: 0.9;
}


/* =========================================================
   LAYOUT
========================================================= */

.layout {

    display: flex;

    min-height:
        calc(100vh - 85px);
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 235px;

    background: white;

    border-right:
        1px solid #DCE4EF;

    padding: 22px 15px;
}


.sidebar h3 {

    margin-top: 0;

    margin-bottom: 18px;

    color: #0B1F3A;
}


.sidebar a {

    display: block;

    text-decoration: none;

    color: #172033;

    padding: 12px 13px;

    border-radius: 7px;

    margin-bottom: 7px;

    font-weight: bold;

    font-size: 14px;
}


.sidebar a:hover {

    background: #EAF2FB;

    color: #1E4E8C;
}


.sidebar .active {

    background: #1E4E8C;

    color: white;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    flex: 1;

    padding: 28px;

    overflow-x: hidden;
}


.container {

    max-width: 1150px;

    margin: auto;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 14px 17px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;

    line-height: 1.5;
}


.message.success {

    background: #E8F5E9;

    color: #1B5E20;

    border:
        1px solid #A5D6A7;
}


.message.error {

    background: #FDECEC;

    color: #B71C1C;

    border:
        1px solid #F5B5B5;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid #DCE4EF;

    border-radius: 10px;

    padding: 24px;

    margin-bottom: 22px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.05);
}


.card h2 {

    margin-top: 0;

    color: #0B1F3A;

    border-bottom:
        1px solid #DCE4EF;

    padding-bottom: 12px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.status.admin-approved {

    background: #EAF2FB;

    color: #1E4E8C;

    border:
        1px solid #B8D0EA;
}


.status.approved {

    background: #E8F5E9;

    color: #1B5E20;

    border:
        1px solid #A5D6A7;
}


.status.correction {

    background: #FFF3E0;

    color: #E65100;

    border:
        1px solid #FFCC80;
}


.status.pending {

    background: #FFF8E1;

    color: #856404;

    border:
        1px solid #FFE082;
}


/* =========================================================
   EVENT DETAILS
========================================================= */

.event-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 16px;

    margin-top: 18px;
}


.info-box {

    background: #F8FAFD;

    border:
        1px solid #DCE4EF;

    border-radius: 8px;

    padding: 13px;
}


.info-box strong {

    display: block;

    color: #61708A;

    font-size: 12px;

    margin-bottom: 5px;
}


/* =========================================================
   REPORT TEXT
========================================================= */

.report-text {

    background: #F8FAFD;

    border:
        1px solid #DCE4EF;

    border-radius: 8px;

    padding: 18px;

    line-height: 1.7;

    white-space: pre-wrap;

    min-height: 120px;
}


/* =========================================================
   FILE GRID
========================================================= */

.file-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

    margin-top: 15px;
}


.file-box {

    background: #F8FAFD;

    border:
        1px solid #DCE4EF;

    border-radius: 8px;

    padding: 14px;
}


.file-box strong {

    display: block;

    margin-bottom: 8px;

    color: #0B1F3A;
}


.file-link {

    display: inline-block;

    text-decoration: none;

    color: #1E4E8C;

    font-weight: bold;

    padding: 7px 10px;

    background: #EAF2FB;

    border-radius: 6px;
}


.file-link:hover {

    background: #D9E8F7;
}


.not-uploaded {

    color: #888;

    font-size: 13px;
}


/* =========================================================
   CORRECTION
========================================================= */

.correction-box {

    background: #FFF7ED;

    border:
        1px solid #FED7AA;

    border-left:
        5px solid #F97316;

    border-radius: 8px;

    padding: 15px;

    line-height: 1.6;
}


/* =========================================================
   REVIEW ACTION
========================================================= */

.action-card {

    border:
        2px solid #DCE4EF;

    background: #FBFCFE;
}


.action-card h2 {

    border: none;

    margin-bottom: 8px;
}


textarea {

    width: 100%;

    min-height: 120px;

    resize: vertical;

    padding: 12px;

    border:
        1px solid #C9D4E3;

    border-radius: 7px;

    font-family: Arial, sans-serif;

    font-size: 14px;

    outline: none;
}


textarea:focus {

    border-color: #1E4E8C;
}


.button-row {

    display: flex;

    gap: 12px;

    margin-top: 16px;

    flex-wrap: wrap;
}


button {

    border: none;

    border-radius: 7px;

    padding: 11px 18px;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}


.approve-btn {

    background: #1E4E8C;

    color: white;
}


.approve-btn:hover {

    background: #163B6C;
}


.correction-btn {

    background: #F97316;

    color: white;
}


.correction-btn:hover {

    background: #EA580C;
}


.back-btn {

    display: inline-block;

    text-decoration: none;

    background: #EAF2FB;

    color: #1E4E8C;

    padding: 10px 15px;

    border-radius: 7px;

    font-weight: bold;

    margin-bottom: 18px;
}


.back-btn:hover {

    background: #D9E8F7;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .layout {

        display: block;
    }

    .sidebar {

        width: 100%;

        border-right: none;

        border-bottom:
            1px solid #DCE4EF;
    }

    .event-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .file-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 600px) {

    .main {

        padding: 15px;
    }

    .event-grid {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <h1>
        Core Admin / PIMC — Final Report Review
    </h1>

    <p>
        Final verification and approval of Department / HOD approved event reports
    </p>

</div>


<div class="layout">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">

    <h3>
        Core Admin Menu
    </h3>


    <a
        href="core_admin_dashboard.php"
    >
        🏠 Dashboard
    </a>


    <a
        href="report_review.php?report_id=<?php
            echo intval($report_id);
        ?>"
        class="active"
    >
        📋 Report Review
    </a>


    <a
        href="core_admin_dashboard.php#event-review"
    >
        📋 Event Review
    </a>


    <a
        href="core_admin_dashboard.php#brochure-review"
    >
        📄 Brochure Review
    </a>


    <a
        href="core_admin_dashboard.php#booked-events"
    >
        🔴 Booked Events
    </a>


</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">

<div class="container">


<a
    href="core_admin_dashboard.php"
    class="back-btn"
>
    ← Back to Core Admin Dashboard
</a>


<?php if ($message !== ""): ?>

    <div
        class="message <?php
            echo h($message_type);
        ?>"
    >

        <?php
        echo h($message);
        ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     REPORT STATUS
========================================================= -->

<div class="card">

    <h2>
        Report Review Status
    </h2>

    <span
        class="status <?php
            echo h($status_class);
        ?>"
    >

        <?php
        echo h($status_label);
        ?>

    </span>


    <div class="event-grid">

        <div class="info-box">

            <strong>
                Report ID
            </strong>

            #<?php
            echo intval(
                $report["report_id"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Submitted At
            </strong>

            <?php
            echo h(
                $report["submitted_at"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Last Reviewed
            </strong>

            <?php
            echo h(
                $report["reviewed_at"]
                ?: "-"
            );
            ?>

        </div>

    </div>

</div>


<!-- =========================================================
     EVENT INFORMATION
========================================================= -->

<div class="card">

    <h2>
        Event Information
    </h2>


    <div class="event-grid">


        <div class="info-box">

            <strong>
                Event Name
            </strong>

            <?php
            echo h(
                $report["event_name"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Event ID
            </strong>

            <?php
            echo intval(
                $report["event_id"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Event Type
            </strong>

            <?php
            echo h(
                $report["event_type"]
                ?: "-"
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Event Date
            </strong>

            <?php
            echo h(
                $report["event_date"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Event Time
            </strong>

            <?php
            echo h(
                $report["start_time"]
            );
            ?>

            -

            <?php
            echo h(
                $report["end_time"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Venue
            </strong>

            <?php
            echo h(
                $report["venue"]
                ?: "-"
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Expected Participants
            </strong>

            <?php
            echo h(
                $report["expected_participants"]
                ?: "-"
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Organizer
            </strong>

            <?php
            echo h(
                $report["organizer_name"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Department
            </strong>

            <?php
            echo h(
                $report["organizer_department"]
                ?: "-"
            );
            ?>

        </div>


    </div>

</div>


<!-- =========================================================
     OBJECTIVES
========================================================= -->

<div class="card">

    <h2>
        Objectives
    </h2>


    <div class="report-text">

        <?php
        echo h(
            $report["objective"]
            ?: "No objective provided."
        );
        ?>

    </div>

</div>


<!-- =========================================================
     GUEST / RESOURCE PERSON
========================================================= -->

<div class="card">

    <h2>
        Resource Person / Guest Details
    </h2>


    <div class="report-text">

        <?php
        echo h(
            $report["guest_details"]
            ?: "No guest/resource person details provided."
        );
        ?>

    </div>

</div>


<!-- =========================================================
     DETAILED REPORT
========================================================= -->

<div class="card">

    <h2>
        Detailed Event Report
    </h2>


    <div class="report-text">

        <?php
        echo h(
            $report["detailed_report"]
            ?: "No detailed report submitted."
        );
        ?>

    </div>

</div>


<!-- =========================================================
     REPORT FILES
========================================================= -->

<div class="card">

    <h2>
        Supporting Documents
    </h2>


    <div class="file-grid">


        <div class="file-box">

            <strong>
                Photo Gallery
            </strong>

            <?php
            echo fileLink(
                $report["photo_gallery"],
                "Open Photo Gallery"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Attendance Register
            </strong>

            <?php
            echo fileLink(
                $report["attendance_register"],
                "Open Attendance Register"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Publicity Material
            </strong>

            <?php
            echo fileLink(
                $report["publicity_material"],
                "Open Publicity Material"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Sample Certificate
            </strong>

            <?php
            echo fileLink(
                $report["sample_certificate"],
                "Open Sample Certificate"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Invitation / Correspondence
            </strong>

            <?php
            echo fileLink(
                $report["invitation_correspondence"],
                "Open Invitation / Correspondence"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Resource Person Profile
            </strong>

            <?php
            echo fileLink(
                $report["resource_person_profile"],
                "Open Resource Person Profile"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Feedback Analysis
            </strong>

            <?php
            echo fileLink(
                $report["feedback_analysis"],
                "Open Feedback Analysis"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Coordinator Signature
            </strong>

            <?php
            echo fileLink(
                $report["coordinator_signature"],
                "Open Coordinator Signature"
            );
            ?>

        </div>


        <div class="file-box">

            <strong>
                Principal Signature
            </strong>

            <?php
            echo fileLink(
                $report["principal_signature"],
                "Open Principal Signature"
            );
            ?>

        </div>


    </div>

</div>


<!-- =========================================================
     PREVIOUS CORRECTION
========================================================= -->

<?php if (
    !empty(
        $report["correction_reason"]
    )
): ?>

<div class="card">

    <h2>
        Previous Correction / Review Note
    </h2>


    <div class="correction-box">

        <?php
        echo nl2br(
            h(
                $report["correction_reason"]
            )
        );
        ?>

    </div>

</div>

<?php endif; ?>


<!-- =========================================================
     CORE ADMIN ACTION
========================================================= -->

<?php if (
    $status === "admin_approved"
): ?>

<div class="card action-card">

    <h2>
        Core Admin Final Decision
    </h2>


    <p>

        The Department Admin / HOD has already approved this
        report. Core Admin / PIMC can now give the final
        approval or return the report to the organizer for
        correction.

    </p>


    <!-- ================================================
         FINAL APPROVE
    ================================================= -->

    <form
        method="POST"
        onsubmit="
            return confirm(
                'Are you sure you want to give FINAL approval to this report?'
            );
        "
    >

        <input
            type="hidden"
            name="report_id"
            value="<?php
                echo intval($report_id);
            ?>"
        >

        <input
            type="hidden"
            name="action"
            value="approve"
        >


        <div class="button-row">

            <button
                type="submit"
                class="approve-btn"
            >

                ✓ Give Final Approval

            </button>

        </div>

    </form>


    <br>


    <!-- ================================================
         CORRECTION
    ================================================= -->

    <form
        method="POST"
        onsubmit="
            return confirm(
                'Return this report to the organizer for correction?'
            );
        "
    >

        <input
            type="hidden"
            name="report_id"
            value="<?php
                echo intval($report_id);
            ?>"
        >

        <input
            type="hidden"
            name="action"
            value="correction"
        >


        <label>

            <strong>
                Correction Reason
            </strong>

        </label>


        <br><br>


        <textarea
            name="correction_reason"
            placeholder="Explain what needs to be corrected..."
            required
        ></textarea>


        <div class="button-row">

            <button
                type="submit"
                class="correction-btn"
            >

                ↩ Return for Correction

            </button>

        </div>

    </form>

</div>

<?php elseif (
    $status === "approved"
): ?>

<div class="card">

    <h2>
        ✓ Final Approval Completed
    </h2>


    <div class="message success">

        This event report has already received
        final approval from Core Admin / PIMC.

    </div>

</div>

<?php else: ?>

<div class="card">

    <h2>
        Report Not Available for Final Review
    </h2>


    <div class="message error">

        This report is currently:

        <strong>
            <?php
            echo h(
                $status_label
            );
            ?>
        </strong>

        <br><br>

        Core Admin final approval is available only after
        Department Admin / HOD approval.

    </div>

</div>

<?php endif; ?>


</div>

</div>

</div>


</body>

</html>