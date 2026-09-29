<?php

session_start();

require_once "../db.php";

/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.html");
    exit();
}

$admin_id = $_SESSION["user_id"];

$message = "";
$message_type = "";

/* =========================================================
   REQUIRED CHECKLIST ITEMS
========================================================= */

$required_items = [
    "College Logo / Emblem",
    "College Name / Address",
    "Mandatory Institutional Text",
    "Event Name",
    "Event Date",
    "Event Time",
    "Venue",
    "Guest Details",
    "College Contact Number"
];


/* =========================================================
   BROCHURE LIST
   Shows:
   1. HOD pending brochures
   2. HOD resubmitted brochures
   3. HOD-approved brochures waiting for Core Admin
   4. Core Admin resubmitted brochures
========================================================= */

if (
    !isset($_GET["brochure_id"]) ||
    !is_numeric($_GET["brochure_id"])
) {

    $brochures = $conn->query("
        SELECT
            eb.brochure_id,
            eb.event_id,
            eb.brochure_file,
            eb.upload_date,
            eb.status,
            eb.hod_status,
            eb.correction_reason,

            e.event_name,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.event_type,

            u.name AS organizer_name,
            u.email AS organizer_email

        FROM event_brochures eb

        INNER JOIN events e
            ON eb.event_id = e.event_id

        LEFT JOIN users u
            ON e.organizer_id = u.id

        WHERE
        (
            /* HOD is waiting for review */
            (
                eb.status IN ('pending', 'resubmitted')
                AND
                (
                    eb.hod_status IS NULL
                    OR eb.hod_status = 'pending'
                    OR eb.hod_status = 'correction_required'
                )
            )

            OR

            /* HOD approved - Core Admin is waiting */
            (
                eb.hod_status = 'approved'
                AND eb.status IN ('pending', 'resubmitted')
            )
        )

        ORDER BY eb.brochure_id DESC
    ");

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Brochure Review | EventHub Admin</title>

        <style>

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: Arial, sans-serif;
            }

            body {
                background: #f3efff;
                color: #1e293b;
                padding: 30px;
            }

            .container {
                max-width: 1150px;
                margin: auto;
            }

            .header {
                background: #6f42c1;
                color: white;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 25px;
            }

            .header h1 {
                margin-bottom: 6px;
            }

            .header p {
                opacity: 0.9;
            }

            .card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 20px;
                box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            }

            .card h2 {
                color: #5b2da3;
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th {
                background: #f8f6ff;
                color: #475569;
                text-align: left;
                padding: 14px 12px;
                border-bottom: 1px solid #e2e8f0;
                font-size: 13px;
            }

            td {
                padding: 14px 12px;
                border-bottom: 1px solid #e2e8f0;
                font-size: 14px;
            }

            tr:hover {
                background: #fafafa;
            }

            .status {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
            }

            .pending {
                background: #fff3cd;
                color: #856404;
            }

            .resubmitted {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .approved {
                background: #d4edda;
                color: #155724;
            }

            .correction {
                background: #ffe0b2;
                color: #8a4b00;
            }

            .hod-status {
                background: #dcfce7;
                color: #166534;
            }

            .core-status {
                background: #fef3c7;
                color: #92400e;
            }

            .view-btn {
                display: inline-block;
                background: #6f42c1;
                color: white;
                text-decoration: none;
                padding: 8px 14px;
                border-radius: 7px;
                font-size: 13px;
                font-weight: bold;
            }

            .view-btn:hover {
                background: #5b2da3;
            }

            .back-btn {
                display: inline-block;
                margin-bottom: 20px;
                background: #475569;
                color: white;
                text-decoration: none;
                padding: 9px 15px;
                border-radius: 7px;
                font-size: 13px;
            }

            .empty {
                padding: 45px;
                text-align: center;
                color: #64748b;
            }

            .event-name {
                font-weight: bold;
                color: #1e293b;
            }

            @media (max-width: 800px) {

                body {
                    padding: 15px;
                }

                .card {
                    overflow-x: auto;
                }

                table {
                    min-width: 900px;
                }
            }

        </style>

    </head>

    <body>

        <div class="container">

            <div class="header">

                <h1>📄 Brochure Review</h1>

                <p>
                    Review brochures submitted by event organizers.
                </p>

            </div>

            <a
                href="admin_dashboard.php"
                class="back-btn"
            >
                ← Back to Admin Dashboard
            </a>

            <div class="card">

                <h2>Brochures Awaiting Review</h2>

                <?php if ($brochures && $brochures->num_rows > 0): ?>

                    <table>

                        <thead>

                            <tr>

                                <th>Event</th>

                                <th>Organizer</th>

                                <th>Event Date</th>

                                <th>Venue</th>

                                <th>Uploaded</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php while ($row = $brochures->fetch_assoc()): ?>

                                <tr>

                                    <td>

                                        <div class="event-name">

                                            <?= htmlspecialchars(
                                                $row["event_name"]
                                            ) ?>

                                        </div>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $row["organizer_name"] ?? "N/A"
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $row["event_date"]
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $row["venue"]
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $row["upload_date"]
                                        ) ?>

                                    </td>

                                    <td>

                                        <?php if (
                                            $row["hod_status"] === "approved"
                                            && $row["status"] === "resubmitted"
                                        ): ?>

                                            <span class="status resubmitted">
                                                🔄 Resubmitted — Core Admin Review
                                            </span>

                                        <?php elseif (
                                            $row["hod_status"] === "approved"
                                        ): ?>

                                            <span class="status core-status">
                                                🟡 Core Admin Review
                                            </span>

                                        <?php elseif (
                                            $row["status"] === "resubmitted"
                                        ): ?>

                                            <span class="status resubmitted">
                                                🔄 Resubmitted — HOD Review
                                            </span>

                                        <?php elseif (
                                            $row["status"] === "correction_required"
                                        ): ?>

                                            <span class="status correction">
                                                🟠 Correction Required
                                            </span>

                                        <?php else: ?>

                                            <span class="status pending">
                                                🟡 HOD Review
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <a
                                            href="brochure_review.php?brochure_id=<?= (int)$row["brochure_id"] ?>"
                                            class="view-btn"
                                        >
                                            Review
                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="empty">

                        <h3>
                            ✅ No brochures awaiting review
                        </h3>

                        <p style="margin-top:10px;">
                            There are currently no pending brochure submissions.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </body>

    </html>

    <?php

    exit();
}


/* =========================================================
   ACTUAL BROCHURE REVIEW
========================================================= */

$brochure_id = (int)$_GET["brochure_id"];


/* =========================================================
   HOD APPROVE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["approve_brochure"])
) {

    $checked_items =
        isset($_POST["checked_items"])
        ? $_POST["checked_items"]
        : [];

    $missing_items = [];

    foreach ($required_items as $item) {

        if (!in_array($item, $checked_items)) {

            $missing_items[] = $item;

        }

    }

    if (!empty($missing_items)) {

        $message =
            "Please verify all required checklist items before approving.";

        $message_type = "error";

    } else {

        /*
         * Only HOD approval happens here.
         * Core Admin approval happens separately.
         */

        $stmt = $conn->prepare("
            UPDATE event_brochures
            SET
                hod_status = 'approved',
                hod_approved_by = ?,
                hod_approved_at = NOW(),
                status = 'pending',
                correction_reason = NULL
            WHERE brochure_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $admin_id,
            $brochure_id
        );

        if ($stmt->execute()) {

            $message =
                "Brochure approved by HOD and forwarded to Core Admin.";

            $message_type = "success";

        } else {

            $message =
                "Failed to update brochure approval.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   HOD RETURN FOR CORRECTION
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["return_correction"])
) {

    $incorrect_items =
        isset($_POST["incorrect_items"])
        ? $_POST["incorrect_items"]
        : [];

    $correction_details =
        trim($_POST["correction_details"] ?? "");

    if (
        empty($incorrect_items)
        &&
        $correction_details === ""
    ) {

        $message =
            "Please select at least one correction item or enter correction details.";

        $message_type = "error";

    } else {

        $correction_text = "";

        if (!empty($incorrect_items)) {

            $correction_text .= "Incorrect Items:\n";

            foreach ($incorrect_items as $item) {

                $correction_text .= "- " . $item . "\n";
            }
        }

        if ($correction_details !== "") {

            $correction_text .=
                "\nAdditional Details:\n";

            $correction_text .= $correction_details;
        }

        $stmt = $conn->prepare("
            UPDATE event_brochures
            SET
                hod_status = 'correction_required',
                status = 'correction_required',
                correction_reason = ?
            WHERE brochure_id = ?
        ");

        $stmt->bind_param(
            "si",
            $correction_text,
            $brochure_id
        );

        if ($stmt->execute()) {

            $message =
                "Brochure returned to organizer for correction.";

            $message_type = "success";

        } else {

            $message =
                "Failed to return brochure for correction.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   CORE ADMIN APPROVE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["core_approve_brochure"])
) {

    /*
     * Core Admin can approve only after HOD approval.
     */

    $check = $conn->prepare("
        SELECT hod_status, status
        FROM event_brochures
        WHERE brochure_id = ?
    ");

    $check->bind_param(
        "i",
        $brochure_id
    );

    $check->execute();

    $check_result = $check->get_result();

    if ($check_result->num_rows === 1) {

        $current = $check_result->fetch_assoc();

        if ($current["hod_status"] !== "approved") {

            $message =
                "HOD approval is required before Core Admin approval.";

            $message_type = "error";

        } else {

            /*
             * Final Core Admin approval.
             */

            $stmt = $conn->prepare("
                UPDATE event_brochures
                SET
                    status = 'approved',
                    correction_reason = NULL
                WHERE brochure_id = ?
            ");

            $stmt->bind_param(
                "i",
                $brochure_id
            );

            if ($stmt->execute()) {

                $message =
                    "Brochure approved successfully by Core Admin / PIMC.";

                $message_type = "success";

            } else {

                $message =
                    "Failed to approve brochure.";

                $message_type = "error";
            }

            $stmt->close();
        }

    } else {

        $message =
            "Brochure not found.";

        $message_type = "error";
    }

    $check->close();
}


/* =========================================================
   CORE ADMIN RETURN FOR CORRECTION
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["core_return_correction"])
) {

    $core_correction_details =
        trim($_POST["core_correction_details"] ?? "");

    $core_incorrect_items =
        isset($_POST["core_incorrect_items"])
        ? $_POST["core_incorrect_items"]
        : [];

    if (
        empty($core_incorrect_items)
        &&
        $core_correction_details === ""
    ) {

        $message =
            "Please select at least one correction item or enter correction details.";

        $message_type = "error";

    } else {

        $correction_text = "";

        if (!empty($core_incorrect_items)) {

            $correction_text .=
                "Core Admin Correction Items:\n";

            foreach ($core_incorrect_items as $item) {

                $correction_text .=
                    "- " . $item . "\n";
            }
        }

        if ($core_correction_details !== "") {

            $correction_text .=
                "\nCore Admin Correction Details:\n";

            $correction_text .=
                $core_correction_details;
        }

        /*
         * IMPORTANT:
         * HOD remains approved.
         * Only Core Admin status becomes correction_required.
         */

        $stmt = $conn->prepare("
            UPDATE event_brochures
            SET
                status = 'correction_required',
                correction_reason = ?
            WHERE brochure_id = ?
              AND hod_status = 'approved'
        ");

        $stmt->bind_param(
            "si",
            $correction_text,
            $brochure_id
        );

        if ($stmt->execute()) {

            $message =
                "Brochure returned to organizer by Core Admin for correction.";

            $message_type = "success";

        } else {

            $message =
                "Failed to return brochure for correction.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   FETCH BROCHURE DETAILS
========================================================= */

$stmt = $conn->prepare("
    SELECT

        eb.brochure_id,
        eb.event_id,
        eb.brochure_file,
        eb.upload_date,
        eb.status,
        eb.hod_status,
        eb.hod_approved_by,
        eb.hod_approved_at,
        eb.correction_reason,

        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.event_type,

        u.name AS organizer_name,
        u.email AS organizer_email,

        hu.name AS hod_name,
        hu.department AS hod_department

    FROM event_brochures eb

    INNER JOIN events e
        ON eb.event_id = e.event_id

    LEFT JOIN users u
        ON e.organizer_id = u.id

    LEFT JOIN users hu
        ON eb.hod_approved_by = hu.id

    WHERE eb.brochure_id = ?
");

$stmt->bind_param(
    "i",
    $brochure_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    die("Brochure not found.");
}

$brochure = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CURRENT STATUS
========================================================= */

$hod_status =
    $brochure["hod_status"] ?? "pending";

$core_status =
    $brochure["status"] ?? "pending";


/*
 * Determine which review stage is currently active.
 */

$hod_review_pending =
    (
        $hod_status !== "approved"
        &&
        $core_status !== "approved"
    );

$core_review_pending =
    (
        $hod_status === "approved"
        &&
        $core_status !== "approved"
        &&
        $core_status !== "correction_required"
    );

$core_correction_required =
    (
        $hod_status === "approved"
        &&
        $core_status === "correction_required"
    );

$core_approved =
    (
        $hod_status === "approved"
        &&
        $core_status === "approved"
    );

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
        Brochure Review - HOD / Core Admin
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f3efff;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .header {
            background: #6f42c1;
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 5px 0;
        }

        .back-btn {
            display: inline-block;
            background: #475569;
            color: white;
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .card h2 {
            color: #5b2da3;
            margin-top: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .info-box {
            background: #f8f6ff;
            padding: 12px;
            border-radius: 8px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        .status {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: bold;
            margin: 5px 5px 5px 0;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .approved {
            background: #d4edda;
            color: #155724;
        }

        .correction {
            background: #ffe0b2;
            color: #8a4b00;
        }

        .resubmitted {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .hod-box {
            background: #e8f5e9;
            border-left: 5px solid #28a745;
            padding: 15px;
            border-radius: 8px;
        }

        .core-box {
            background: #fff8e1;
            border-left: 5px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
        }

        .correction-box {
            background: #fff3e0;
            border-left: 5px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
            white-space: pre-line;
        }

        .check-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .check-item input {
            width: 18px;
            height: 18px;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 8px;
            resize: vertical;
        }

        .btn {
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            margin-top: 10px;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-correction {
            background: #dc3545;
            color: white;
        }

        .btn-view {
            background: #6f42c1;
            color: white;
            text-decoration: none;
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: bold;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>

<div class="container">


    <a
        href="brochure_review.php"
        class="back-btn"
    >
        ← Back to Brochure List
    </a>


    <div class="header">

        <h1>
            HOD / Department Admin
        </h1>

        <p>
            Brochure Review
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div
            class="message <?= htmlspecialchars($message_type) ?>"
        >

            <?= nl2br(
                htmlspecialchars($message)
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         EVENT DETAILS
    ====================================================== -->

    <div class="card">

        <h2>
            Event Details
        </h2>

        <div class="info-grid">

            <div class="info-box">

                <strong>
                    Event Name
                </strong>

                <?= htmlspecialchars(
                    $brochure["event_name"]
                ) ?>

            </div>


            <div class="info-box">

                <strong>
                    Event Type
                </strong>

                <?= htmlspecialchars(
                    $brochure["event_type"]
                ) ?>

            </div>


            <div class="info-box">

                <strong>
                    Date
                </strong>

                <?= htmlspecialchars(
                    $brochure["event_date"]
                ) ?>

            </div>


            <div class="info-box">

                <strong>
                    Time
                </strong>

                <?= htmlspecialchars(
                    $brochure["start_time"]
                ) ?>

                -

                <?= htmlspecialchars(
                    $brochure["end_time"]
                ) ?>

            </div>


            <div class="info-box">

                <strong>
                    Venue
                </strong>

                <?= htmlspecialchars(
                    $brochure["venue"]
                ) ?>

            </div>


            <div class="info-box">

                <strong>
                    Organizer
                </strong>

                <?= htmlspecialchars(
                    $brochure["organizer_name"]
                    ?? "N/A"
                ) ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         BROCHURE
    ====================================================== -->

    <div class="card">

        <h2>
            Uploaded Brochure
        </h2>

        <p>

            <strong>
                File:
            </strong>

            <?= htmlspecialchars(
                $brochure["brochure_file"]
            ) ?>

        </p>

        <p style="margin-top:10px;">

            <strong>
                Uploaded:
            </strong>

            <?= htmlspecialchars(
                $brochure["upload_date"]
            ) ?>

        </p>

        <br>

        <a
            href="../uploads/<?= rawurlencode(
                $brochure["brochure_file"]
            ) ?>"
            target="_blank"
            class="btn-view"
        >
            View Brochure
        </a>

    </div>


    <!-- =====================================================
         APPROVAL STATUS
    ====================================================== -->

    <div class="card">

        <h2>
            Approval Status
        </h2>


        <!-- HOD APPROVED -->

        <?php if ($hod_status === "approved"): ?>

            <div class="hod-box">

                <span class="status approved">
                    🟢 Approved by HOD
                </span>

                <p style="margin-top:12px;">

                    <strong>
                        Approved by:
                    </strong>

                    <?= htmlspecialchars(
                        $brochure["hod_name"]
                        ?? "Department HOD"
                    ) ?>

                </p>

                <?php if (
                    !empty($brochure["hod_department"])
                ): ?>

                    <p>

                        <strong>
                            Department:
                        </strong>

                        <?= htmlspecialchars(
                            $brochure["hod_department"]
                        ) ?>

                    </p>

                <?php endif; ?>


                <?php if (
                    !empty($brochure["hod_approved_at"])
                ): ?>

                    <p>

                        <strong>
                            Approved At:
                        </strong>

                        <?= htmlspecialchars(
                            $brochure["hod_approved_at"]
                        ) ?>

                    </p>

                <?php endif; ?>

            </div>


        <!-- HOD CORRECTION -->

        <?php elseif (
            $hod_status === "correction_required"
        ): ?>

            <div class="correction-box">

                <span class="status correction">
                    🟠 HOD Correction Required
                </span>

                <p style="margin-top:12px;">

                    The brochure has been returned
                    to the organizer for correction.

                </p>

                <?php if (
                    !empty($brochure["correction_reason"])
                ): ?>

                    <p style="margin-top:12px;">

                        <strong>
                            Correction Details:
                        </strong>

                    </p>

                    <p>

                        <?= nl2br(
                            htmlspecialchars(
                                $brochure["correction_reason"]
                            )
                        ) ?>

                    </p>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <?php if (
                $core_status === "resubmitted"
            ): ?>

                <span class="status resubmitted">
                    🔄 Resubmitted — Pending HOD Review
                </span>

            <?php else: ?>

                <span class="status pending">
                    🟡 Pending HOD Review
                </span>

            <?php endif; ?>

        <?php endif; ?>


        <!-- CORE ADMIN PENDING -->

        <?php if ($core_review_pending): ?>

            <div
                class="core-box"
                style="margin-top:15px;"
            >

                <span class="status pending">
                    🟡 Core Admin: Pending Review
                </span>

                <p style="margin-top:12px;">

                    This brochure has been approved by
                    the Department HOD and is now
                    forwarded to Core Admin / PIMC
                    for final review.

                </p>

            </div>

        <?php endif; ?>


        <!-- CORE ADMIN CORRECTION -->

        <?php if ($core_correction_required): ?>

            <div
                class="correction-box"
                style="margin-top:15px;"
            >

                <span class="status correction">
                    🟠 Core Admin Correction Required
                </span>

                <p style="margin-top:12px;">

                    The brochure was returned by
                    Core Admin / PIMC for correction.

                </p>

                <?php if (
                    !empty($brochure["correction_reason"])
                ): ?>

                    <p style="margin-top:12px;">

                        <strong>
                            Correction Details:
                        </strong>

                    </p>

                    <p>

                        <?= nl2br(
                            htmlspecialchars(
                                $brochure["correction_reason"]
                            )
                        ) ?>

                    </p>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- CORE ADMIN APPROVED -->

        <?php if ($core_approved): ?>

            <div
                class="hod-box"
                style="margin-top:15px;"
            >

                <span class="status approved">
                    🟢 Core Admin Approved
                </span>

                <p style="margin-top:12px;">

                    Core Admin / PIMC has completed
                    the final brochure approval.

                </p>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         HOD REVIEW FORM
    ====================================================== -->

    <?php if ($hod_status !== "approved"): ?>

        <div class="card">

            <h2>
                HOD Checklist
            </h2>

            <p>
                Verify every item before forwarding
                the brochure to Core Admin.
            </p>

            <form method="POST">

                <?php foreach (
                    $required_items as $item
                ): ?>

                    <div class="check-item">

                        <input
                            type="checkbox"
                            name="checked_items[]"
                            value="<?= htmlspecialchars($item) ?>"
                            id="<?= md5($item) ?>"
                        >

                        <label
                            for="<?= md5($item) ?>"
                        >
                            <?= htmlspecialchars($item) ?>
                        </label>

                    </div>

                <?php endforeach; ?>


                <button
                    type="submit"
                    name="approve_brochure"
                    class="btn btn-approve"
                    onclick="return confirm('Approve this brochure as HOD and forward it to Core Admin?');"
                >

                    ✓ Approve as HOD → Send to Core Admin

                </button>

            </form>

        </div>


        <!-- HOD RETURN -->

        <div class="card">

            <h2>
                Return for Correction
            </h2>

            <p>
                Select the incorrect items and mention
                additional details.
            </p>

            <form method="POST">

                <?php foreach (
                    $required_items as $item
                ): ?>

                    <div class="check-item">

                        <input
                            type="checkbox"
                            name="incorrect_items[]"
                            value="<?= htmlspecialchars($item) ?>"
                            id="incorrect_<?= md5($item) ?>"
                        >

                        <label
                            for="incorrect_<?= md5($item) ?>"
                        >
                            <?= htmlspecialchars($item) ?>
                        </label>

                    </div>

                <?php endforeach; ?>


                <br>

                <label>

                    <strong>
                        Correction Details
                    </strong>

                </label>

                <br><br>

                <textarea
                    name="correction_details"
                    placeholder="Mention what needs to be corrected..."
                ></textarea>


                <button
                    type="submit"
                    name="return_correction"
                    class="btn btn-correction"
                    onclick="return confirm('Return this brochure to the organizer for correction?');"
                >

                    ↩ Return for Correction

                </button>

            </form>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         CORE ADMIN REVIEW
    ====================================================== -->

    <?php if (
        $hod_status === "approved"
        &&
        $core_status !== "approved"
        &&
        $core_status !== "correction_required"
    ): ?>

        <div class="card">

            <h2>
                Core Admin / PIMC Final Review
            </h2>

            <p>

                HOD has approved this brochure.
                Please perform the final Core Admin /
                PIMC scrutiny before approving it.

            </p>


            <!-- CORE APPROVE -->

            <form
                method="POST"
                style="margin-top:20px;"
            >

                <button
                    type="submit"
                    name="core_approve_brochure"
                    class="btn btn-approve"
                    onclick="return confirm('Approve this brochure as Core Admin / PIMC?');"
                >

                    ✓ Approve Brochure — Core Admin / PIMC

                </button>

            </form>

        </div>


        <!-- CORE CORRECTION -->

        <div class="card">

            <h2>
                Core Admin Return for Correction
            </h2>

            <p>

                If the brochure does not satisfy the
                final scrutiny requirements, select the
                incorrect items and provide details.

            </p>


            <form method="POST">

                <?php foreach (
                    $required_items as $item
                ): ?>

                    <div class="check-item">

                        <input
                            type="checkbox"
                            name="core_incorrect_items[]"
                            value="<?= htmlspecialchars($item) ?>"
                            id="core_<?= md5($item) ?>"
                        >

                        <label
                            for="core_<?= md5($item) ?>"
                        >
                            <?= htmlspecialchars($item) ?>
                        </label>

                    </div>

                <?php endforeach; ?>


                <br>

                <label>

                    <strong>
                        Core Admin Correction Details
                    </strong>

                </label>

                <br><br>

                <textarea
                    name="core_correction_details"
                    placeholder="Mention what needs to be corrected..."
                ></textarea>


                <button
                    type="submit"
                    name="core_return_correction"
                    class="btn btn-correction"
                    onclick="return confirm('Return this brochure to the organizer for correction?');"
                >

                    ↩ Return to Organizer for Correction

                </button>

            </form>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         CORE CORRECTION MESSAGE
    ====================================================== -->

    <?php if ($core_correction_required): ?>

        <div class="card">

            <h2>
                Waiting for Organizer Resubmission
            </h2>

            <div class="core-box">

                <span class="status correction">
                    🟠 Correction Required
                </span>

                <p style="margin-top:12px;">

                    Organizer must correct the brochure
                    and resubmit the same brochure record.

                </p>

                <p style="margin-top:10px;">

                    After resubmission, the brochure will
                    return to Core Admin / PIMC for review.

                </p>

            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         FINAL APPROVED MESSAGE
    ====================================================== -->

    <?php if ($core_approved): ?>

        <div class="card">

            <h2>
                Final Approval
            </h2>

            <div class="hod-box">

                <span class="status approved">
                    🟢 Final Brochure Approved
                </span>

                <p style="margin-top:12px;">

                    HOD approval and Core Admin / PIMC
                    final approval are complete.

                </p>

                <p style="margin-top:10px;">

                    The brochure can now proceed to the
                    official publication / event launch stage.

                </p>

            </div>

        </div>

    <?php endif; ?>


</div>

</body>

</html>