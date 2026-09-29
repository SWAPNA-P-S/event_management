<?php

session_start();

require_once "../db.php";


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $result = $conn->query("SHOW TABLES LIKE '$table'");

    return $result && $result->num_rows > 0;
}


function columnExists($conn, $table, $column)
{
    if (!tableExists($conn, $table)) {
        return false;
    }

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $result = $conn->query("
        SHOW COLUMNS FROM `$table`
        LIKE '$column'
    ");

    return $result && $result->num_rows > 0;
}


function firstExistingColumn($conn, $table, $columns)
{
    foreach ($columns as $column) {

        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }

    return null;
}


function formatDateValue($date)
{
    if (empty($date)) {
        return "Not specified";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date("d F Y", $timestamp);
}


function formatTimeValue($time)
{
    if (empty($time)) {
        return "Not specified";
    }

    $timestamp = strtotime($time);

    if (!$timestamp) {
        return $time;
    }

    return date("h:i A", $timestamp);
}


function calculateDuration($start, $end)
{
    if (empty($start) || empty($end)) {
        return "Not specified";
    }

    $startTime = strtotime($start);
    $endTime = strtotime($end);

    if (!$startTime || !$endTime) {
        return "Not specified";
    }

    $seconds = $endTime - $startTime;

    if ($seconds < 0) {
        $seconds += 24 * 60 * 60;
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($hours > 0 && $minutes > 0) {
        return $hours . " hour(s) " . $minutes . " minute(s)";
    }

    if ($hours > 0) {
        return $hours . " hour(s)";
    }

    return $minutes . " minute(s)";
}


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];


/* =========================================================
   EVENT ID
========================================================= */

$event_id = isset($_GET["event_id"])
    ? (int)$_GET["event_id"]
    : 0;


if ($event_id <= 0) {

    echo "
    <div style='
        font-family:Arial;
        padding:40px;
        text-align:center;
    '>

        <h2>Invalid Event</h2>

        <p>
            No event was selected for report generation.
        </p>

        <a href='report.php'>
            ← Back to Reports
        </a>

    </div>
    ";

    exit();
}


/* =========================================================
   CHECK APPROVED ORGANIZER TEAM
========================================================= */

$approved_team_ids = [];


if (
    tableExists($conn, "organizer_team_members")
    &&
    tableExists($conn, "organizer_requests")
) {

    $stmt = $conn->prepare("
        SELECT DISTINCT
            otm.request_id

        FROM organizer_team_members otm

        INNER JOIN organizer_requests orq
            ON orq.request_id = otm.request_id

        WHERE otm.user_id = ?

        AND orq.status = 'approved'
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $user_id
        );

        $stmt->execute();

        $result = $stmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $approved_team_ids[] =
                (int)$row["request_id"];
        }


        $stmt->close();
    }
}


/* =========================================================
   CHECK EVENT ACCESS
========================================================= */

$event = null;


/*
   First try team-linked event.
*/

if (!empty($approved_team_ids)) {

    $placeholders = implode(
        ",",
        array_fill(
            0,
            count($approved_team_ids),
            "?"
        )
    );


    $types =
        "i" .
        str_repeat(
            "i",
            count($approved_team_ids)
        );


    $sql = "
        SELECT
            e.*

        FROM events e

        WHERE e.event_id = ?

        AND e.organizer_request_id
        IN ($placeholders)

        LIMIT 1
    ";


    $stmt = $conn->prepare($sql);


    if ($stmt) {

        $params = array_merge(
            [$event_id],
            $approved_team_ids
        );


        $stmt->bind_param(
            $types,
            ...$params
        );


        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $event =
                $result->fetch_assoc();
        }


        $stmt->close();
    }
}


/*
   Fallback:
   event organizer_id belongs to logged-in user.
*/

if (!$event) {

    $stmt = $conn->prepare("
        SELECT *
        FROM events

        WHERE event_id = ?

        AND organizer_id = ?

        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $event_id,
            $user_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $event =
                $result->fetch_assoc();
        }


        $stmt->close();
    }
}


/*
   If event is not accessible.
*/

if (!$event) {

    echo "
    <div style='
        font-family:Arial;
        padding:40px;
        text-align:center;
    '>

        <h2>Access Denied</h2>

        <p>
            This event is not linked to your approved
            organizing team.
        </p>

        <a href='report.php'>
            ← Back to Reports
        </a>

    </div>
    ";

    exit();
}


/* =========================================================
   ORGANIZER DETAILS
========================================================= */

$organizerName = "";
$organizerDepartment = "";
$organizerEmail = "";
$organizerPhone = "";


if (!empty($event["organizer_id"])) {

    $organizer_id =
        (int)$event["organizer_id"];


    $stmt = $conn->prepare("
        SELECT
            name,
            department,
            email,
            phone

        FROM users

        WHERE id = ?

        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $organizer_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $organizer =
                $result->fetch_assoc();


            $organizerName =
                $organizer["name"] ?? "";


            $organizerDepartment =
                $organizer["department"] ?? "";


            $organizerEmail =
                $organizer["email"] ?? "";


            $organizerPhone =
                $organizer["phone"] ?? "";
        }


        $stmt->close();
    }
}


/*
   Fallback to organizer request owner.
*/

if (
    $organizerName === ""
    &&
    !empty($event["organizer_request_id"])
) {

    $request_id =
        (int)$event["organizer_request_id"];


    $stmt = $conn->prepare("
        SELECT
            u.name,
            u.department,
            u.email,
            u.phone

        FROM organizer_requests orq

        INNER JOIN users u
            ON u.id = orq.requested_by

        WHERE orq.request_id = ?

        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $request_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $organizer =
                $result->fetch_assoc();


            $organizerName =
                $organizer["name"] ?? "";


            $organizerDepartment =
                $organizer["department"] ?? "";


            $organizerEmail =
                $organizer["email"] ?? "";


            $organizerPhone =
                $organizer["phone"] ?? "";
        }


        $stmt->close();
    }
}


/* =========================================================
   TEAM NAME
========================================================= */

$teamName = "";


if (!empty($event["organizer_request_id"])) {

    $request_id =
        (int)$event["organizer_request_id"];


    if (tableExists($conn, "organizer_requests")) {

        $stmt = $conn->prepare("
            SELECT team_name

            FROM organizer_requests

            WHERE request_id = ?

            LIMIT 1
        ");


        if ($stmt) {

            $stmt->bind_param(
                "i",
                $request_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            if ($result->num_rows === 1) {

                $row =
                    $result->fetch_assoc();


                $teamName =
                    $row["team_name"] ?? "";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   EVENT DETAILS
========================================================= */

$eventName =
    $event["event_name"] ?? "";


$eventNature =
    $event["event_type"]
    ?? $event["nature"]
    ?? "";


$eventDate =
    $event["event_date"] ?? "";


$startTime =
    $event["start_time"] ?? "";


$endTime =
    $event["end_time"] ?? "";


$venue =
    $event["venue"] ?? "";


$objective =
    $event["objective"] ?? "";


$description =
    $event["description"] ?? "";


$requirements =
    $event["requirements"] ?? "";


$expectedParticipants =
    $event["expected_participants"]
    ?? "";


$guestDetails =
    $event["guest_details"] ?? "";


/* =========================================================
   REGISTRATION COUNT
========================================================= */

$registeredCount = 0;


if (tableExists($conn, "registrations")) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total

        FROM registrations

        WHERE event_id = ?
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $event_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($row = $result->fetch_assoc()) {

            $registeredCount =
                (int)$row["total"];
        }


        $stmt->close();
    }
}


/* =========================================================
   ATTENDANCE COUNT
========================================================= */

$attendanceCount = 0;


if (
    tableExists($conn, "attendance")
    &&
    columnExists(
        $conn,
        "attendance",
        "event_id"
    )
) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total

        FROM attendance

        WHERE event_id = ?
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $event_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($row = $result->fetch_assoc()) {

            $attendanceCount =
                (int)$row["total"];
        }


        $stmt->close();
    }
}


/* =========================================================
   STAGE INFORMATION
========================================================= */

$stageName = "";
$stageLocation = "";
$stageBookingDate = "";
$stageStartTime = "";
$stageEndTime = "";
$stageStatus = "";


if (
    tableExists($conn, "stage_bookings")
    &&
    tableExists($conn, "stages")
) {

    $stmt = $conn->prepare("
        SELECT
            sb.booking_date,
            sb.start_time,
            sb.end_time,
            sb.booking_status,

            s.stage_name,
            s.location

        FROM stage_bookings sb

        LEFT JOIN stages s
            ON s.stage_id = sb.stage_id

        WHERE sb.event_id = ?

        ORDER BY sb.booking_id DESC

        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $event_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $stage =
                $result->fetch_assoc();


            $stageName =
                $stage["stage_name"] ?? "";


            $stageLocation =
                $stage["location"] ?? "";


            $stageBookingDate =
                $stage["booking_date"] ?? "";


            $stageStartTime =
                $stage["start_time"] ?? "";


            $stageEndTime =
                $stage["end_time"] ?? "";


            $stageStatus =
                $stage["booking_status"] ?? "";
        }


        $stmt->close();
    }
}


/* =========================================================
   STAGE REQUIREMENTS
========================================================= */

$stageRequirements = [];


if (!empty($requirements)) {

    $parts =
        preg_split(
            "/[,;]+/",
            $requirements
        );


    foreach ($parts as $part) {

        $part =
            trim($part);


        if ($part !== "") {

            $stageRequirements[] =
                $part;
        }
    }
}


/* =========================================================
   STAGE HANDOVER
========================================================= */

$handoverStatus =
    "Not available";


if (
    tableExists($conn, "stage_handover")
    &&
    tableExists($conn, "stage_bookings")
) {

    $stmt = $conn->prepare("
        SELECT
            sh.handed_over,
            sh.handover_time

        FROM stage_handover sh

        INNER JOIN stage_bookings sb
            ON sb.booking_id = sh.booking_id

        WHERE sb.event_id = ?

        ORDER BY sh.handover_id DESC

        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $event_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $handover =
                $result->fetch_assoc();


            if (
                (int)$handover["handed_over"]
                === 1
            ) {

                $handoverStatus =
                    "Stage handed over";

            } else {

                $handoverStatus =
                    "Preparation in progress";
            }
        }


        $stmt->close();
    }
}


/* =========================================================
   EXPENSES
   ROBUST VERSION
========================================================= */

$totalExpense = 0;

$expenseRows = [];


if (tableExists($conn, "expenses")) {

    /*
       Find the real column names
       from the current database.
    */

    $expenseEventColumn =
        firstExistingColumn(
            $conn,
            "expenses",
            [
                "event_id"
            ]
        );


    $dateColumn =
        firstExistingColumn(
            $conn,
            "expenses",
            [
                "expense_date",
                "date",
                "created_at"
            ]
        );


    $categoryColumn =
        firstExistingColumn(
            $conn,
            "expenses",
            [
                "category",
                "expense_category",
                "expense_type",
                "type"
            ]
        );


    $descriptionColumn =
        firstExistingColumn(
            $conn,
            "expenses",
            [
                "description",
                "expense_description",
                "details",
                "particulars"
            ]
        );


    $amountColumn =
        firstExistingColumn(
            $conn,
            "expenses",
            [
                "amount",
                "expense_amount",
                "cost"
            ]
        );


    $idColumn =
        firstExistingColumn(
            $conn,
            "expenses",
            [
                "expense_id",
                "id"
            ]
        );


    /*
       Continue only when event_id,
       description and amount exist.
    */

    if (
        $expenseEventColumn
        &&
        $descriptionColumn
        &&
        $amountColumn
    ) {

        $selectParts = [];


        /*
           Date
        */

        if ($dateColumn) {

            $selectParts[] =
                "`$dateColumn` AS expense_date";

        } else {

            $selectParts[] =
                "NULL AS expense_date";
        }


        /*
           Category
        */

        if ($categoryColumn) {

            $selectParts[] =
                "`$categoryColumn` AS category";

        } else {

            $selectParts[] =
                "'General' AS category";
        }


        /*
           Description
        */

        $selectParts[] =
            "`$descriptionColumn` AS description";


        /*
           Amount
        */

        $selectParts[] =
            "`$amountColumn` AS amount";


        /*
           Ordering
        */

        if ($dateColumn) {

            $orderBy =
                "`$dateColumn` ASC";

        } elseif ($idColumn) {

            $orderBy =
                "`$idColumn` ASC";

        } else {

            $orderBy =
                "1";
        }


        $expenseSql = "
            SELECT

                " .
                implode(
                    ",",
                    $selectParts
                )
                . "

            FROM expenses

            WHERE `$expenseEventColumn` = ?

            ORDER BY $orderBy
        ";


        $stmt =
            $conn->prepare(
                $expenseSql
            );


        if ($stmt) {

            $stmt->bind_param(
                "i",
                $event_id
            );

            $stmt->execute();

            $expenseResult =
                $stmt->get_result();


            while (
                $expense =
                $expenseResult->fetch_assoc()
            ) {

                $expenseRows[] =
                    $expense;


                $totalExpense +=
                    (float)$expense["amount"];
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   FEEDBACK
========================================================= */

$feedbackCount = 0;


if (
    tableExists($conn, "feedback")
    &&
    columnExists(
        $conn,
        "feedback",
        "event_id"
    )
) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total

        FROM feedback

        WHERE event_id = ?
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $event_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($row = $result->fetch_assoc()) {

            $feedbackCount =
                (int)$row["total"];
        }


        $stmt->close();
    }
}


/* =========================================================
   BROCHURE
========================================================= */

$brochureFile = "";


if (
    tableExists($conn, "event_brochures")
    &&
    columnExists(
        $conn,
        "event_brochures",
        "event_id"
    )
) {

    $stmt = $conn->prepare("
        SELECT brochure_file

        FROM event_brochures

        WHERE event_id = ?

        ORDER BY brochure_id DESC

        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $event_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $brochure =
                $result->fetch_assoc();


            $brochureFile =
                $brochure["brochure_file"]
                ?? "";
        }


        $stmt->close();
    }
}


/* =========================================================
   DURATION
========================================================= */

$duration =
    calculateDuration(
        $startTime,
        $endTime
    );


/* =========================================================
   BENEFICIARIES
========================================================= */

if ($registeredCount > 0) {

    $beneficiaries =
        $registeredCount;

} elseif (
    $expectedParticipants !== ""
) {

    $beneficiaries =
        $expectedParticipants;

} else {

    $beneficiaries =
        "Not available";
}


/* =========================================================
   REPORT TEXT
========================================================= */

$defaultDetailedReport = "";


/*
   If event_reports has a detailed report,
   load it safely.
*/

if (
    tableExists($conn, "event_reports")
    &&
    columnExists(
        $conn,
        "event_reports",
        "event_id"
    )
) {

    $detailColumn =
        firstExistingColumn(
            $conn,
            "event_reports",
            [
                "detailed_report",
                "detail_report",
                "report_content"
            ]
        );


    $organizerColumn =
        firstExistingColumn(
            $conn,
            "event_reports",
            [
                "organizer_id",
                "organiser_id",
                "created_by"
            ]
        );


    if (
        $detailColumn
        &&
        $organizerColumn
    ) {

        $stmt = $conn->prepare("
            SELECT `$detailColumn`
            AS detailed_report

            FROM event_reports

            WHERE event_id = ?

            AND `$organizerColumn` = ?

            ORDER BY 1 DESC

            LIMIT 1
        ");


        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $event_id,
                $user_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            if ($row = $result->fetch_assoc()) {

                $defaultDetailedReport =
                    $row["detailed_report"]
                    ?? "";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   SUBMIT REPORT
========================================================= */

$successMessage = "";

$errorMessage = "";


if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $submittedDetailedReport =
        trim(
            $_POST["detailed_report"]
            ?? ""
        );


    if (
        $submittedDetailedReport === ""
    ) {

        $errorMessage =
            "Please enter the detailed report before submitting.";

    } elseif (
        !tableExists(
            $conn,
            "event_reports"
        )
    ) {

        $errorMessage =
            "The event_reports table is not available.";

    } else {

        $eventReportEventColumn =
            firstExistingColumn(
                $conn,
                "event_reports",
                [
                    "event_id"
                ]
            );


        $eventReportOrganizerColumn =
            firstExistingColumn(
                $conn,
                "event_reports",
                [
                    "organizer_id",
                    "organiser_id",
                    "created_by"
                ]
            );


        $detailColumn =
            firstExistingColumn(
                $conn,
                "event_reports",
                [
                    "detailed_report",
                    "detail_report",
                    "report_content"
                ]
            );


        $statusColumn =
            firstExistingColumn(
                $conn,
                "event_reports",
                [
                    "status"
                ]
            );


        if (
            !$eventReportEventColumn
            ||
            !$eventReportOrganizerColumn
            ||
            !$detailColumn
        ) {

            $errorMessage =
                "Required columns are missing from event_reports.";

        } else {

            /*
               Check existing report.
            */

            $stmt = $conn->prepare("
                SELECT *

                FROM event_reports

                WHERE `$eventReportEventColumn` = ?

                AND `$eventReportOrganizerColumn` = ?

                LIMIT 1
            ");


            if ($stmt) {

                $stmt->bind_param(
                    "ii",
                    $event_id,
                    $user_id
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();


                if ($result->num_rows === 1) {

                    $existing =
                        $result->fetch_assoc();


                    $reportId =
                        (int)(
                            $existing["report_id"]
                            ?? 0
                        );


                    $existingStatus =
                        $existing["status"]
                        ?? "";


                    if (
                        $existingStatus
                        === "approved"
                    ) {

                        $errorMessage =
                            "This report has already been approved.";

                    } elseif ($reportId > 0) {

                        /*
                           UPDATE
                        */

                        $setParts = [];

                        $values = [];

                        $types = "";


                        $setParts[] =
                            "`$detailColumn` = ?";

                        $values[] =
                            $submittedDetailedReport;

                        $types .= "s";


                        if ($statusColumn) {

                            $setParts[] =
                                "`$statusColumn` = 'pending'";
                        }


                        if (
                            columnExists(
                                $conn,
                                "event_reports",
                                "correction_reason"
                            )
                        ) {

                            $setParts[] =
                                "`correction_reason` = NULL";
                        }


                        if (
                            columnExists(
                                $conn,
                                "event_reports",
                                "reviewed_at"
                            )
                        ) {

                            $setParts[] =
                                "`reviewed_at` = NULL";
                        }


                        $values[] =
                            $reportId;

                        $types .= "i";


                        $updateSql = "
                            UPDATE event_reports

                            SET
                                " .
                                implode(
                                    ",",
                                    $setParts
                                ) .

                            "

                            WHERE report_id = ?
                        ";


                        $updateStmt =
                            $conn->prepare(
                                $updateSql
                            );


                        if ($updateStmt) {

                            $updateStmt->bind_param(
                                $types,
                                ...$values
                            );


                            if (
                                $updateStmt->execute()
                            ) {

                                $successMessage =
                                    "Report submitted successfully for review.";

                                $defaultDetailedReport =
                                    $submittedDetailedReport;

                            } else {

                                $errorMessage =
                                    "Failed to update the report.";
                            }


                            $updateStmt->close();
                        }
                    }

                } else {

                    /*
                       INSERT NEW REPORT
                    */

                    $columns = [
                        "`$eventReportEventColumn`",
                        "`$eventReportOrganizerColumn`",
                        "`$detailColumn`"
                    ];


                    $placeholders = [
                        "?",
                        "?",
                        "?"
                    ];


                    $values = [
                        $event_id,
                        $user_id,
                        $submittedDetailedReport
                    ];


                    $types = "iis";


                    if ($statusColumn) {

                        $columns[] =
                            "`$statusColumn`";

                        $placeholders[] =
                            "'pending'";
                    }


                    if (
                        columnExists(
                            $conn,
                            "event_reports",
                            "created_at"
                        )
                    ) {

                        $columns[] =
                            "`created_at`";

                        $placeholders[] =
                            "NOW()";
                    }


                    $insertSql = "
                        INSERT INTO event_reports
                        (
                            " .
                            implode(
                                ",",
                                $columns
                            ) .
                        "
                        )

                        VALUES
                        (
                            " .
                            implode(
                                ",",
                                $placeholders
                            ) .
                        "
                        )
                    ";


                    $insertStmt =
                        $conn->prepare(
                            $insertSql
                        );


                    if ($insertStmt) {

                        $insertStmt->bind_param(
                            $types,
                            ...$values
                        );


                        if (
                            $insertStmt->execute()
                        ) {

                            $successMessage =
                                "Final event report submitted successfully.";

                            $defaultDetailedReport =
                                $submittedDetailedReport;

                        } else {

                            $errorMessage =
                                "Failed to submit the report.";
                        }


                        $insertStmt->close();
                    }
                }


                $stmt->close();
            }
        }
    }
}


/* =========================================================
   OUTCOME
========================================================= */

$outcome =
    $description !== ""
    ? $description
    : "No separate outcome has been entered.";


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
    Final Event Report
</title>


<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #172033;

    line-height: 1.6;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    background: #0b1f3a;

    color: white;

    padding: 16px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    flex-wrap: wrap;
}


.topbar-title {

    font-size: 20px;

    font-weight: 700;
}


.topbar-links {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.topbar-links a {

    color: white;

    text-decoration: none;

    background:
        rgba(255,255,255,0.12);

    padding: 9px 14px;

    border-radius: 8px;

    font-size: 14px;
}


.topbar-links a:hover {

    background:
        rgba(255,255,255,0.20);
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width: 1200px;

    margin: 30px auto;

    padding: 0 20px;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 14px 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-weight: 600;
}


.success {

    background: #dcfce7;

    color: #166534;

    border:
        1px solid #bbf7d0;
}


.error {

    background: #fee2e2;

    color: #991b1b;

    border:
        1px solid #fecaca;
}


/* =========================================================
   REPORT PAPER
========================================================= */

.report-paper {

    background: white;

    border:
        1px solid #dce4ef;

    border-radius: 12px;

    box-shadow:
        0 8px 30px
        rgba(11,31,58,0.08);

    overflow: hidden;
}


/* =========================================================
   COLLEGE HEADER
========================================================= */

.college-header {

    padding: 35px;

    text-align: center;

    border-bottom:
        4px solid #0b1f3a;
}


.college-header h1 {

    margin: 0;

    color: #0b1f3a;

    font-size: 30px;
}


.college-header h2 {

    margin: 8px 0;

    color: #1e4e8c;

    font-size: 22px;
}


.college-header p {

    margin: 4px 0;

    color: #475569;

    font-size: 14px;
}


/* =========================================================
   REPORT TITLE
========================================================= */

.report-title {

    padding: 25px 35px;

    background: #eaf2fb;

    text-align: center;
}


.report-title h2 {

    margin: 0;

    color: #0b1f3a;

    font-size: 24px;
}


/* =========================================================
   SECTION
========================================================= */

.section {

    padding: 30px 35px;

    border-bottom:
        1px solid #e2e8f0;
}


.section:last-child {

    border-bottom: none;
}


.section-title {

    color: #0b1f3a;

    font-size: 21px;

    margin:
        0 0 18px;

    padding-bottom: 10px;

    border-bottom:
        2px solid #eaf2fb;
}


/* =========================================================
   INFORMATION TABLE
========================================================= */

.info-table {

    width: 100%;

    border-collapse: collapse;
}


.info-table th,
.info-table td {

    border:
        1px solid #dce4ef;

    padding:
        11px 13px;

    text-align: left;

    vertical-align: top;
}


.info-table th {

    background: #f1f5f9;

    width: 28%;

    color: #0b1f3a;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}


.stat {

    background: #f4f7fb;

    border:
        1px solid #dce4ef;

    border-radius: 10px;

    padding: 18px;

    text-align: center;
}


.stat-number {

    display: block;

    font-size: 27px;

    font-weight: 700;

    color: #0b1f3a;
}


.stat-label {

    color: #64748b;

    font-size: 13px;
}


/* =========================================================
   TEXT BOX
========================================================= */

.report-text {

    background: #f8fafc;

    border:
        1px solid #dce4ef;

    border-radius: 10px;

    padding: 18px;

    white-space: pre-wrap;

    min-height: 80px;
}


/* =========================================================
   EXPENSE TABLE
========================================================= */

.expense-table {

    width: 100%;

    border-collapse: collapse;
}


.expense-table th,
.expense-table td {

    border:
        1px solid #dce4ef;

    padding: 10px;
}


.expense-table th {

    background: #0b1f3a;

    color: white;
}


.total-row td {

    font-weight: 700;

    background: #eaf2fb;
}


/* =========================================================
   FORM
========================================================= */

.form-box {

    background: #f8fafc;

    border:
        1px solid #dce4ef;

    border-radius: 10px;

    padding: 20px;
}


.form-box label {

    display: block;

    font-weight: 700;

    color: #0b1f3a;

    margin-bottom: 8px;
}


.form-box textarea {

    width: 100%;

    min-height: 220px;

    resize: vertical;

    padding: 13px;

    border:
        1px solid #cbd5e1;

    border-radius: 8px;

    font-family:
        Arial,
        sans-serif;

    font-size: 15px;
}


.form-box textarea:focus {

    outline: none;

    border-color: #1e4e8c;

    box-shadow:
        0 0 0 3px #eaf2fb;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {

    display: flex;

    gap: 12px;

    flex-wrap: wrap;

    margin-top: 20px;
}


.btn {

    display: inline-block;

    padding: 12px 20px;

    border-radius: 9px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-size: 15px;

    font-weight: 700;
}


.btn-primary {

    background: #0b1f3a;

    color: white;
}


.btn-primary:hover {

    background: #1e4e8c;
}


.btn-secondary {

    background: #eaf2fb;

    color: #0b1f3a;

    border:
        1px solid #c9ddf1;
}


.btn-secondary:hover {

    background: #dceafa;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: 700;
}


.status-pending {

    background: #fef3c7;

    color: #92400e;
}


.status-approved {

    background: #dcfce7;

    color: #166534;
}


.status-correction {

    background: #fee2e2;

    color: #991b1b;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    body {

        background: white;
    }


    .topbar,
    .no-print {

        display: none !important;
    }


    .container {

        max-width: none;

        margin: 0;

        padding: 0;
    }


    .report-paper {

        border: none;

        box-shadow: none;
    }


    .section {

        page-break-inside: avoid;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 750px) {

    .container {

        padding: 0 10px;
    }


    .section {

        padding: 22px 18px;
    }


    .college-header {

        padding:
            25px 18px;
    }


    .stats {

        grid-template-columns: 1fr;
    }


    .info-table th {

        width: 40%;
    }


    .topbar {

        padding: 15px;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     TOP BAR
====================================================== -->

<div class="topbar no-print">

    <div class="topbar-title">

        Event Report Generator

    </div>


    <div class="topbar-links">

        <a href="report.php">
            ← Reports
        </a>


        <a href="organizer_dashboard.php">
            Dashboard
        </a>


        <a
            href="#"
            onclick="window.print(); return false;"
        >
            Print / Save PDF
        </a>

    </div>

</div>


<div class="container">


<?php if ($successMessage !== ""): ?>

    <div class="message success no-print">

        <?= h($successMessage) ?>

    </div>

<?php endif; ?>


<?php if ($errorMessage !== ""): ?>

    <div class="message error no-print">

        <?= h($errorMessage) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     REPORT
====================================================== -->

<div class="report-paper">


<!-- =====================================================
     COLLEGE HEADER
====================================================== -->

<div class="college-header">

    <h1>
        SSV COLLEGE
    </h1>


    <h2>
        VALAYANCHIRANGARA, PERUMBAVOOR,
        KERALA - 683556
    </h2>


    <p>
        Affiliated to Mahatma Gandhi University
    </p>


    <p>
        Re-accredited by NAAC with A++ Grade
        (CGPA 3.59)
    </p>

</div>


<!-- =====================================================
     TITLE
====================================================== -->

<div class="report-title">

    <h2>
        FINAL EVENT REPORT
    </h2>

</div>


<!-- =====================================================
     1 PROGRAMME DETAILS
====================================================== -->

<div class="section">

    <h3 class="section-title">

        1. Programme Details

    </h3>


    <table class="info-table">

        <tr>

            <th>
                Name of Event
            </th>

            <td>
                <?= h(
                    $eventName
                    ?: "Not specified"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Nature of Event
            </th>

            <td>
                <?= h(
                    $eventNature
                    ?: "Not specified"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Date
            </th>

            <td>
                <?= h(
                    formatDateValue(
                        $eventDate
                    )
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Time
            </th>

            <td>

                <?= h(
                    formatTimeValue(
                        $startTime
                    )
                ) ?>

                -

                <?= h(
                    formatTimeValue(
                        $endTime
                    )
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Duration
            </th>

            <td>
                <?= h($duration) ?>
            </td>

        </tr>


        <tr>

            <th>
                Venue / Platform
            </th>

            <td>
                <?= h(
                    $venue
                    ?: "Not specified"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Organizing Department / Cell
            </th>

            <td>
                <?= h(
                    $organizerDepartment
                    ?: "Not specified"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Coordinator
            </th>

            <td>
                <?= h(
                    $organizerName
                    ?: "Not specified"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Coordinator Contact
            </th>

            <td>

                <?= h(
                    $organizerPhone
                ) ?>


                <?php if ($organizerEmail !== ""): ?>

                    <br>

                    <?= h(
                        $organizerEmail
                    ) ?>

                <?php endif; ?>

            </td>

        </tr>


        <tr>

            <th>
                Organizing Team
            </th>

            <td>
                <?= h(
                    $teamName
                    ?: "Not specified"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Number of Beneficiaries
            </th>

            <td>
                <?= h(
                    $beneficiaries
                ) ?>
            </td>

        </tr>

    </table>

</div>


<!-- =====================================================
     2 OBJECTIVES
====================================================== -->

<div class="section">

    <h3 class="section-title">

        2. Objectives

    </h3>


    <div class="report-text">

        <?= h(
            $objective
            ?: "Objectives have not been entered."
        ) ?>

    </div>

</div>


<!-- =====================================================
     3 RESOURCE PERSON
====================================================== -->

<div class="section">

    <h3 class="section-title">

        3. Resource Person / Guest Details

    </h3>


    <div class="report-text">

        <?= h(
            $guestDetails
            ?: "No resource person / guest details recorded."
        ) ?>

    </div>

</div>


<!-- =====================================================
     4 PARTICIPANTS
====================================================== -->

<div class="section">

    <h3 class="section-title">

        4. Participant & Attendance Summary

    </h3>


    <div class="stats">


        <div class="stat">

            <span class="stat-number">

                <?= h(
                    $registeredCount
                ) ?>

            </span>


            <span class="stat-label">

                Registered Participants

            </span>

        </div>


        <div class="stat">

            <span class="stat-number">

                <?= h(
                    $attendanceCount
                ) ?>

            </span>


            <span class="stat-label">

                Attendance Recorded

            </span>

        </div>


        <div class="stat">

            <span class="stat-number">

                <?= h(
                    $feedbackCount
                ) ?>

            </span>


            <span class="stat-label">

                Feedback Responses

            </span>

        </div>


    </div>


    <p>

        Participant and attendance information
        is retrieved automatically from the
        event management system.

    </p>

</div>


<!-- =====================================================
     5 DETAILED REPORT
====================================================== -->

<div class="section">

    <h3 class="section-title">

        5. Detailed Event Report

    </h3>


    <form
        method="POST"
        class="form-box no-print"
    >

        <label for="detailed_report">

            Detailed Event Report

        </label>


        <textarea
            name="detailed_report"
            id="detailed_report"
            placeholder="Enter how the event was conducted, major activities, participation, important observations and outcomes..."
        ><?= h(
            $defaultDetailedReport
        ) ?></textarea>


        <div class="actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Submit Final Report
            </button>


            <button
                type="button"
                class="btn btn-secondary"
                onclick="window.print();"
            >
                Print / Save PDF
            </button>

        </div>

    </form>


    <?php if (
        $defaultDetailedReport !== ""
    ): ?>

        <div
            class="report-text"
            style="margin-top:20px;"
        >

            <?= nl2br(
                h(
                    $defaultDetailedReport
                )
            ) ?>

        </div>

    <?php endif; ?>

</div>


<!-- =====================================================
     6 OUTCOME
====================================================== -->

<div class="section">

    <h3 class="section-title">

        6. Outcome / Benefit

    </h3>


    <div class="report-text">

        <?= h(
            $outcome
        ) ?>

    </div>

</div>


<!-- =====================================================
     7 STAGE
====================================================== -->

<div class="section">

    <h3 class="section-title">

        7. Stage & Infrastructure

    </h3>


    <table class="info-table">

        <tr>

            <th>
                Stage
            </th>

            <td>

                <?= h(
                    $stageName
                    ?: "No stage booking recorded"
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Stage Location
            </th>

            <td>

                <?= h(
                    $stageLocation
                    ?: "Not specified"
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Booking Date
            </th>

            <td>

                <?= h(
                    formatDateValue(
                        $stageBookingDate
                    )
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Stage Time
            </th>

            <td>

                <?= h(
                    formatTimeValue(
                        $stageStartTime
                    )
                ) ?>

                -

                <?= h(
                    formatTimeValue(
                        $stageEndTime
                    )
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Booking Status
            </th>

            <td>

                <?= h(
                    $stageStatus
                    ?: "Not available"
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Stage Handover
            </th>

            <td>

                <?= h(
                    $handoverStatus
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Required Equipment
            </th>

            <td>

                <?php if (
                    !empty(
                        $stageRequirements
                    )
                ): ?>

                    <?= h(
                        implode(
                            ", ",
                            $stageRequirements
                        )
                    ) ?>

                <?php else: ?>

                    No specific requirements recorded.

                <?php endif; ?>

            </td>

        </tr>

    </table>

</div>


<!-- =====================================================
     8 EXPENSES
====================================================== -->

<div class="section">

    <h3 class="section-title">

        8. Fund / Expense Summary

    </h3>


    <?php if (
        !empty($expenseRows)
    ): ?>


        <table class="expense-table">

            <thead>

                <tr>

                    <th>
                        Date
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Amount
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php foreach (
                $expenseRows
                as $expense
            ): ?>

                <tr>

                    <td>

                        <?= h(
                            formatDateValue(
                                $expense[
                                    "expense_date"
                                ]
                            )
                        ) ?>

                    </td>


                    <td>

                        <?= h(
                            $expense[
                                "category"
                            ]
                        ) ?>

                    </td>


                    <td>

                        <?= h(
                            $expense[
                                "description"
                            ]
                        ) ?>

                    </td>


                    <td>

                        ₹<?= number_format(
                            (float)
                            $expense[
                                "amount"
                            ],
                            2
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>


                <tr class="total-row">

                    <td colspan="3">

                        Total Expenditure

                    </td>


                    <td>

                        ₹<?= number_format(
                            $totalExpense,
                            2
                        ) ?>

                    </td>

                </tr>


            </tbody>

        </table>


    <?php else: ?>


        <div class="report-text">

            No expenses have been recorded
            for this event.

        </div>


    <?php endif; ?>

</div>


<!-- =====================================================
     9 FEEDBACK
====================================================== -->

<div class="section">

    <h3 class="section-title">

        9. Feedback Analysis

    </h3>


    <div class="report-text">

        <?php if (
            $feedbackCount > 0
        ): ?>

            <?= h(
                $feedbackCount
            ) ?>

            feedback response(s) have been
            recorded for this event.


            <br><br>


            Detailed feedback analysis can be
            added after the feedback data is
            finalized.

        <?php else: ?>

            No feedback responses have been
            recorded yet.

        <?php endif; ?>

    </div>

</div>


<!-- =====================================================
     10 PUBLICITY
====================================================== -->

<div class="section">

    <h3 class="section-title">

        10. Publicity Material

    </h3>


    <?php if (
        $brochureFile !== ""
    ): ?>

        <div class="report-text">

            Approved brochure/publicity material
            is available.


            <br><br>


            <a
                href="../<?= h(
                    ltrim(
                        $brochureFile,
                        "/"
                    )
                ) ?>"
                target="_blank"
                class="btn btn-secondary no-print"
            >
                View Brochure
            </a>

        </div>


    <?php else: ?>

        <div class="report-text">

            No brochure file is currently
            linked to this event.

        </div>

    <?php endif; ?>

</div>


<!-- =====================================================
     11 EVENT REQUIREMENTS
====================================================== -->

<div class="section">

    <h3 class="section-title">

        11. Event Requirements

    </h3>


    <div class="report-text">

        <?= h(
            $requirements
            ?: "No additional requirements recorded."
        ) ?>

    </div>

</div>


<!-- =====================================================
     12 SIGNATURES
====================================================== -->

<div class="section">

    <h3 class="section-title">

        12. Verification & Signatures

    </h3>


    <table class="info-table">


        <tr>

            <th>
                Student / Event Coordinator
            </th>

            <td style="height:80px;">

                <?= h(
                    $organizerName
                    ?: "____________________________"
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Department Coordinator / HOD
            </th>

            <td style="height:80px;">

                ____________________________

            </td>

        </tr>


        <tr>

            <th>
                Principal / PIMC
            </th>

            <td style="height:80px;">

                ____________________________

            </td>

        </tr>


    </table>

</div>


</div>


<!-- =====================================================
     BOTTOM BUTTONS
====================================================== -->

<div class="actions no-print">

    <a
        href="report.php"
        class="btn btn-secondary"
    >
        ← Back to Reports
    </a>


    <button
        type="button"
        class="btn btn-primary"
        onclick="window.print();"
    >
        Print / Save PDF
    </button>

</div>


</div>


</body>

</html>


<?php

$conn->close();

?>