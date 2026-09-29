<?php

session_start();
require_once "../db.php";

/*
|--------------------------------------------------------------------------
| DEPARTMENT ADMIN / HOD REPORT REVIEW
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.html");
    exit();
}

$admin_id = (int)$_SESSION["user_id"];

$report_id = (int)(
    $_GET["report_id"]
    ?? $_POST["report_id"]
    ?? 0
);

if ($report_id <= 0) {
    die("Invalid report ID.");
}

$message = "";
$message_type = "";


/*
|--------------------------------------------------------------------------
| GET CURRENT ADMIN DEPARTMENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT department
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $admin_id);
$stmt->execute();

$admin_result = $stmt->get_result();

if ($admin_result->num_rows !== 1) {
    die("Admin details not found.");
}

$admin_data = $admin_result->fetch_assoc();

$admin_department = trim(
    $admin_data["department"] ?? ""
);

$stmt->close();


/*
|--------------------------------------------------------------------------
| APPROVE / CORRECTION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | APPROVE BY DEPARTMENT ADMIN
    |--------------------------------------------------------------------------
    */

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

            $message =
                "Please complete all checklist items before approving.";

            $message_type = "error";

        } else {

            /*
            |--------------------------------------------------------------------------
            | MAKE SURE REPORT BELONGS TO THIS DEPARTMENT
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT
                    er.report_id,
                    er.status,
                    u.department
                FROM event_reports er
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

            $check_result = $stmt->get_result();

            if ($check_result->num_rows !== 1) {

                $message = "Report not found.";
                $message_type = "error";

            } else {

                $check_data =
                    $check_result->fetch_assoc();

                $report_department =
                    trim(
                        $check_data["department"] ?? ""
                    );

                $current_status =
                    $check_data["status"] ?? "";


                if (
                    $admin_department !== "" &&
                    $report_department !== "" &&
                    strcasecmp(
                        $admin_department,
                        $report_department
                    ) !== 0
                ) {

                    $message =
                        "You can review reports only from your department.";

                    $message_type = "error";

                } elseif (
                    $current_status === "approved"
                ) {

                    $message =
                        "This report has already received final approval.";

                    $message_type = "error";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | DEPARTMENT APPROVAL
                    |--------------------------------------------------------------------------
                    |
                    | DO NOT set final 'approved' here.
                    | Core Admin / PIMC will give final approval later.
                    |
                    */

                    $stmt_update = $conn->prepare("
                        UPDATE event_reports
                        SET
                            status = 'admin_approved',
                            correction_reason = NULL,
                            reviewed_at = NOW()
                        WHERE report_id = ?
                    ");

                    $stmt_update->bind_param(
                        "i",
                        $report_id
                    );


                    if ($stmt_update->execute()) {

                        $message =
                            "Report approved by Department Admin / HOD and forwarded for Core Admin review.";

                        $message_type = "success";

                    } else {

                        $message =
                            "Failed to approve report.";

                        $message_type = "error";

                    }

                    $stmt_update->close();

                }

            }

            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RETURN FOR CORRECTION
    |--------------------------------------------------------------------------
    */

    elseif ($action === "correction") {

        $items = [

            "programme_details"
                => "Programme Details",

            "detailed_report"
                => "Detailed Report",

            "resource_person"
                => "Resource Person",

            "date_time_duration"
                => "Date / Time / Duration",

            "beneficiary_count"
                => "Number of Beneficiaries",

            "venue"
                => "Venue / Platform",

            "feedback_analysis"
                => "Feedback Analysis",

            "organizing_department"
                => "Organizing Department / Cell",

            "coordinator_details"
                => "Coordinator Details",

            "associating_agency"
                => "Associating Agency",

            "fund_details"
                => "Fund Details",

            "outcome"
                => "Outcome / Benefit",

            "photo_gallery"
                => "Photo Gallery",

            "attendance_register"
                => "Attendance Register",

            "publicity_material"
                => "Publicity Material",

            "sample_certificate"
                => "Sample Certificate",

            "invitation"
                => "Invitation / Correspondence",

            "signatures"
                => "Coordinator + Principal Signatures"

        ];


        $unchecked = [];


        foreach ($items as $key => $label) {

            if (!isset($_POST[$key])) {

                $unchecked[] = $label;

            }

        }


        $admin_comment = trim(
            $_POST["admin_comment"] ?? ""
        );


        $reason = "";


        if (!empty($unchecked)) {

            $reason .=
                "Please correct/provide the following:\n";

            foreach ($unchecked as $item) {

                $reason .=
                    "- " . $item . "\n";

            }

        }


        if ($admin_comment !== "") {

            $reason .=
                "\nAdmin Comment:\n"
                . $admin_comment;

        }


        if ($reason === "") {

            $reason =
                "Please review and improve the submitted report.";

        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DEPARTMENT OWNERSHIP
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                er.report_id,
                er.status,
                u.department
            FROM event_reports er
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

        $check_result =
            $stmt->get_result();


        if ($check_result->num_rows !== 1) {

            $message = "Report not found.";
            $message_type = "error";

        } else {

            $check_data =
                $check_result->fetch_assoc();

            $report_department =
                trim(
                    $check_data["department"] ?? ""
                );


            if (
                $admin_department !== "" &&
                $report_department !== "" &&
                strcasecmp(
                    $admin_department,
                    $report_department
                ) !== 0
            ) {

                $message =
                    "You can review reports only from your department.";

                $message_type = "error";

            } elseif (
                ($check_data["status"] ?? "") === "approved"
            ) {

                $message =
                    "This report already has final approval.";

                $message_type = "error";

            } else {

                $stmt_update = $conn->prepare("
                    UPDATE event_reports
                    SET
                        status = 'correction_required',
                        correction_reason = ?,
                        reviewed_at = NOW()
                    WHERE report_id = ?
                ");

                $stmt_update->bind_param(
                    "si",
                    $reason,
                    $report_id
                );


                if ($stmt_update->execute()) {

                    $message =
                        "Report returned to organizer for correction.";

                    $message_type = "success";

                } else {

                    $message =
                        "Failed to return report.";

                    $message_type = "error";

                }

                $stmt_update->close();

            }

        }

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| FETCH REPORT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        er.*,

        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,

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


if ($result->num_rows !== 1) {

    die("Report not found.");

}

$report = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| DEPARTMENT ACCESS CHECK
|--------------------------------------------------------------------------
*/

$report_department =
    trim(
        $report["organizer_department"] ?? ""
    );


if (
    $admin_department !== "" &&
    $report_department !== "" &&
    strcasecmp(
        $admin_department,
        $report_department
    ) !== 0
) {

    die(
        "Access denied. This report belongs to another department."
    );

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
    Review Event Report - Event Hub
</title>


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


.container {

    max-width: 1100px;

    margin: 35px auto;

    padding: 20px;

}


.back {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    color: #1E4E8C;

    text-decoration: none;

    font-weight: 600;

    margin-bottom: 22px;

}


.back:hover {

    color: #0B1F3A;

}


h1 {

    color: #0B1F3A;

    font-size: 30px;

    margin-bottom: 25px;

}


.card {

    background: #FFFFFF;

    padding: 26px;

    border-radius: 14px;

    margin-bottom: 22px;

    border: 1px solid #DCE4EF;

    box-shadow:
        0 5px 18px
        rgba(11, 31, 58, 0.06);

}


.card h2 {

    color: #0B1F3A;

    margin-top: 0;

    margin-bottom: 18px;

    font-size: 20px;

}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 14px;

}


.info-box {

    background: #F4F7FB;

    border: 1px solid #E1E8F0;

    padding: 15px;

    border-radius: 9px;

    line-height: 1.6;

}


.info-box strong {

    color: #0B1F3A;

}


.message {

    padding: 14px 16px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 14px;

}


.success {

    background: #E8F7EE;

    color: #16703B;

    border: 1px solid #CBEBD7;

}


.error {

    background: #FDECEC;

    color: #A32121;

    border: 1px solid #F4CACA;

}


.status {

    display: inline-flex;

    padding: 7px 13px;

    border-radius: 20px;

    font-weight: 600;

    font-size: 13px;

}


.pending {

    background: #FFF4D6;

    color: #8A5A00;

}


.admin-approved {

    background: #EAF2FB;

    color: #1E4E8C;

}


.approved {

    background: #E8F7EE;

    color: #16703B;

}


.correction-required {

    background: #FDECEC;

    color: #A32121;

}


.reason {

    background: #FFF8ED;

    padding: 16px;

    border-left: 4px solid #E68A00;

    border-radius: 7px;

    white-space: pre-line;

    line-height: 1.6;

}


.report-text {

    background: #F4F7FB;

    border: 1px solid #E1E8F0;

    padding: 18px;

    border-radius: 9px;

    line-height: 1.7;

    white-space: normal;

}


.checklist {

    margin-top: 20px;

}


.checklist label {

    display: block;

    padding: 13px 14px;

    margin-bottom: 8px;

    background: #F4F7FB;

    border: 1px solid #E1E8F0;

    border-radius: 8px;

    cursor: pointer;

    transition: 0.15s ease;

}


.checklist label:hover {

    background: #EAF2FB;

}


.checklist input {

    margin-right: 10px;

}


textarea {

    width: 100%;

    min-height: 120px;

    padding: 13px;

    border: 1px solid #CDD7E3;

    border-radius: 8px;

    margin-top: 10px;

    resize: vertical;

    font-family: Arial, sans-serif;

    font-size: 14px;

}


textarea:focus {

    outline: none;

    border-color: #1E4E8C;

}


.buttons {

    display: flex;

    gap: 12px;

    margin-top: 22px;

}


button {

    padding: 12px 22px;

    border: none;

    border-radius: 8px;

    cursor: pointer;

    font-size: 14px;

    font-weight: 600;

}


.approve {

    background: #1E4E8C;

    color: white;

}


.approve:hover {

    background: #0B1F3A;

}


.correction {

    background: #D97706;

    color: white;

}


.correction:hover {

    background: #B45309;

}


.department-badge {

    display: inline-block;

    margin-top: 8px;

    padding: 6px 10px;

    background: #EAF2FB;

    color: #1E4E8C;

    border-radius: 6px;

    font-size: 12px;

    font-weight: 600;

}


.info-note {

    background: #EAF2FB;

    border-left: 4px solid #1E4E8C;

    padding: 14px 16px;

    border-radius: 7px;

    margin-bottom: 20px;

    color: #24476F;

    line-height: 1.6;

}


@media (max-width: 700px) {

    .container {

        margin: 20px auto;

        padding: 15px;

    }


    .info-grid {

        grid-template-columns: 1fr;

    }


    .buttons {

        flex-direction: column;

    }


    button {

        width: 100%;

    }

}

</style>

</head>


<body>


<div class="container">


<a
    href="reports.php"
    class="back"
>
    ← Back to Reports
</a>


<h1>
    Review Event Report
</h1>


<?php if ($message !== ""): ?>

<div class="message <?php echo htmlspecialchars($message_type); ?>">

    <?php
    echo htmlspecialchars($message);
    ?>

</div>

<?php endif; ?>


<!-- EVENT INFORMATION -->

<div class="card">

    <h2>
        Event Information
    </h2>


    <div class="info-grid">


        <div class="info-box">

            <strong>
                Event Name
            </strong>

            <br>

            <?php
            echo htmlspecialchars(
                $report["event_name"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Event Date
            </strong>

            <br>

            <?php

            echo !empty($report["event_date"])
                ? date(
                    "d M Y",
                    strtotime($report["event_date"])
                )
                : "Not available";

            ?>

        </div>


        <div class="info-box">

            <strong>
                Time
            </strong>

            <br>

            <?php

            if (
                !empty($report["start_time"]) &&
                !empty($report["end_time"])
            ) {

                echo date(
                    "h:i A",
                    strtotime($report["start_time"])
                );

                echo " - ";

                echo date(
                    "h:i A",
                    strtotime($report["end_time"])
                );

            } else {

                echo "Not available";

            }

            ?>

        </div>


        <div class="info-box">

            <strong>
                Venue
            </strong>

            <br>

            <?php
            echo htmlspecialchars(
                $report["venue"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Organizer
            </strong>

            <br>

            <?php
            echo htmlspecialchars(
                $report["organizer_name"]
            );
            ?>

        </div>


        <div class="info-box">

            <strong>
                Organizer Department
            </strong>

            <br>

            <?php
            echo htmlspecialchars(
                $report["organizer_department"]
            );
            ?>

            <div class="department-badge">

                Department Admin Review

            </div>

        </div>


        <div class="info-box">

            <strong>
                Email
            </strong>

            <br>

            <?php
            echo htmlspecialchars(
                $report["organizer_email"]
            );
            ?>

        </div>


    </div>

</div>


<!-- STATUS -->

<div class="card">

    <h2>
        Report Status
    </h2>


    <?php if ($report["status"] === "pending"): ?>

        <span class="status pending">
            Pending Department Review
        </span>


    <?php elseif ($report["status"] === "admin_approved"): ?>

        <span class="status admin-approved">
            Department Approved — Waiting for Core Admin
        </span>


    <?php elseif ($report["status"] === "approved"): ?>

        <span class="status approved">
            Final Approved
        </span>


    <?php elseif ($report["status"] === "correction_required"): ?>

        <span class="status correction-required">
            Correction Required
        </span>

    <?php endif; ?>

</div>


<!-- PREVIOUS CORRECTION -->

<?php if (!empty($report["correction_reason"])): ?>

<div class="card">

    <h2>
        Previous Correction Request
    </h2>


    <div class="reason">

        <?php
        echo htmlspecialchars(
            $report["correction_reason"]
        );
        ?>

    </div>

</div>

<?php endif; ?>


<!-- DETAILED REPORT -->

<div class="card">

    <h2>
        Detailed Report
    </h2>


    <div class="report-text">

        <?php

        echo nl2br(
            htmlspecialchars(
                $report["detailed_report"]
                ?? "No detailed report submitted."
            )
        );

        ?>

    </div>

</div>


<?php

/*
|--------------------------------------------------------------------------
| SHOW REVIEW FORM ONLY WHEN DEPARTMENT REVIEW IS NEEDED
|--------------------------------------------------------------------------
*/

$can_review =
    in_array(
        $report["status"],
        [
            "pending",
            "correction_required"
        ],
        true
    );

?>


<?php if ($can_review): ?>

<div class="card">

    <h2>
        Department Review Checklist
    </h2>


    <div class="info-note">

        Verify the submitted report against the official
        college event report requirements. All required
        checklist items must be verified before Department
        Admin / HOD approval.

    </div>


    <form
        method="POST"
        id="reviewForm"
    >


        <input
            type="hidden"
            name="report_id"
            value="<?php echo $report_id; ?>"
        >


        <div class="checklist">


            <label>

                <input
                    type="checkbox"
                    name="programme_details"
                >

                Programme Details in Nutshell

            </label>


            <label>

                <input
                    type="checkbox"
                    name="detailed_report"
                >

                Detailed Report

            </label>


            <label>

                <input
                    type="checkbox"
                    name="resource_person"
                >

                Resource Person Details

            </label>


            <label>

                <input
                    type="checkbox"
                    name="date_time_duration"
                >

                Date / Time / Duration

            </label>


            <label>

                <input
                    type="checkbox"
                    name="beneficiary_count"
                >

                Number of Beneficiaries

            </label>


            <label>

                <input
                    type="checkbox"
                    name="venue"
                >

                Venue / Platform

            </label>


            <label>

                <input
                    type="checkbox"
                    name="feedback_analysis"
                >

                Feedback Analysis

            </label>


            <label>

                <input
                    type="checkbox"
                    name="organizing_department"
                >

                Organizing Department / Cell

            </label>


            <label>

                <input
                    type="checkbox"
                    name="coordinator_details"
                >

                Coordinator Details

            </label>


            <label>

                <input
                    type="checkbox"
                    name="associating_agency"
                >

                Associating Agency

            </label>


            <label>

                <input
                    type="checkbox"
                    name="fund_details"
                >

                Fund Details

            </label>


            <label>

                <input
                    type="checkbox"
                    name="outcome"
                >

                Outcome / Benefit

            </label>


            <label>

                <input
                    type="checkbox"
                    name="photo_gallery"
                >

                Photo Gallery

            </label>


            <label>

                <input
                    type="checkbox"
                    name="attendance_register"
                >

                Signed Attendance Register

            </label>


            <label>

                <input
                    type="checkbox"
                    name="publicity_material"
                >

                Publicity Material

            </label>


            <label>

                <input
                    type="checkbox"
                    name="sample_certificate"
                >

                Sample Certificate

            </label>


            <label>

                <input
                    type="checkbox"
                    name="invitation"
                >

                Invitation / Correspondence

            </label>


            <label>

                <input
                    type="checkbox"
                    name="signatures"
                >

                Coordinator + Principal Signatures

            </label>


        </div>


        <h3>
            HOD / Department Admin Comment
        </h3>


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
                onclick="
                    return confirm(
                        'Approve this report at Department / HOD level and forward it to Core Admin?'
                    );
                "
            >
                Approve & Forward to Core Admin
            </button>


            <button
                type="submit"
                name="action"
                value="correction"
                class="correction"
                onclick="
                    return confirm(
                        'Return this report to the organizer for correction?'
                    );
                "
            >
                Return for Correction
            </button>


        </div>


    </form>

</div>

<?php endif; ?>


<?php if ($report["status"] === "admin_approved"): ?>

<div class="card">

    <div class="info-note">

        🟢 This report has been approved by the
        Department Admin / HOD.

        <br><br>

        It is now waiting for final review by
        Core Admin / PIMC.

    </div>

</div>

<?php endif; ?>


<?php if ($report["status"] === "approved"): ?>

<div class="card">

    <div class="info-note">

        🟢 This report has received final approval.
        No further Department-level changes are required.

    </div>

</div>

<?php endif; ?>


</div>


</body>

</html>


<?php

$conn->close();

?>