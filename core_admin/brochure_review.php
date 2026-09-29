<?php

session_start();

require_once "../db.php";


/* =========================================================
   CORE ADMIN LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "core_admin"
) {
    header("Location: ../login.html");
    exit;
}


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function formatDateValue($date)
{
    if (!$date) {
        return "-";
    }

    $time = strtotime($date);

    if (!$time) {
        return h($date);
    }

    return date("d M Y", $time);
}


/*
 * Creates the correct browser path for brochure files.
 *
 * Database may contain:
 *      filename.pdf
 * OR
 *      uploads/filename.pdf
 * OR
 *      ../uploads/filename.pdf
 *
 * We always convert it to:
 *      ../uploads/filename.pdf
 */
function brochureUrl($file)
{
    $file = trim((string)$file);

    if ($file === "") {
        return "";
    }

    $file = str_replace("\\", "/", $file);

    /*
     * Remove any existing uploads path.
     */
    $file = preg_replace(
        '#^(\.\./)+uploads/#i',
        "",
        $file
    );

    $file = preg_replace(
        '#^uploads/#i',
        "",
        $file
    );

    /*
     * Keep only the actual filename.
     * This also prevents accidental path traversal.
     */
    $file = basename($file);

    if ($file === "" || $file === "." || $file === "..") {
        return "";
    }

    return "../uploads/" . rawurlencode($file);
}


/* =========================================================
   CORE ADMIN CORRECTION CHECKLIST
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


$message = "";
$message_type = "";


/* =========================================================
   APPROVE / CORRECTION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $brochure_id = isset($_POST["brochure_id"])
        ? (int)$_POST["brochure_id"]
        : 0;

    $action = $_POST["action"] ?? "";


    if ($brochure_id <= 0) {

        $message = "Invalid brochure.";
        $message_type = "error";

    } else {


        /* =====================================================
           GET BROCHURE + EVENT
        ===================================================== */

        $stmt = $conn->prepare("

            SELECT

                eb.brochure_id,
                eb.event_id,
                eb.brochure_file,
                eb.status,
                eb.hod_status,
                eb.correction_reason,

                e.event_name,
                e.event_date,
                e.start_time,
                e.end_time,
                e.venue,
                e.publication_status

            FROM event_brochures eb

            INNER JOIN events e
                ON e.event_id = eb.event_id

            WHERE eb.brochure_id = ?

            LIMIT 1

        ");

        $stmt->bind_param(
            "i",
            $brochure_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $brochure = $result->fetch_assoc();

        $stmt->close();


        if (!$brochure) {

            $message = "Brochure not found.";
            $message_type = "error";

        }


        /* =====================================================
           CHECK HOD APPROVAL
        ===================================================== */

        elseif ($brochure["hod_status"] !== "approved") {

            $message =
                "This brochure has not been approved by the Department Admin yet.";

            $message_type = "error";

        }


        /* =====================================================
           CHECK CORE ADMIN REVIEW STATUS
        ===================================================== */

        elseif (
            $brochure["status"] !== "pending" &&
            $brochure["status"] !== "resubmitted"
        ) {

            $message =
                "This brochure is not waiting for Core Admin review.";

            $message_type = "error";

        }


        /* =====================================================
           CORE ADMIN APPROVE
        ===================================================== */

        elseif ($action === "approve") {


            /*
             * Core Admin final brochure approval.
             *
             * After approval:
             *      brochure = approved
             *      event = officially published
             */

            $conn->begin_transaction();


            try {


                /* ---------------------------------------------
                   APPROVE BROCHURE
                --------------------------------------------- */

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

                $stmt->execute();

                $stmt->close();


                /* ---------------------------------------------
                   PUBLISH EVENT
                --------------------------------------------- */

                $stmt = $conn->prepare("

                    UPDATE events

                    SET
                        publication_status = 'published'

                    WHERE event_id = ?

                ");

                $stmt->bind_param(
                    "i",
                    $brochure["event_id"]
                );

                $stmt->execute();

                $stmt->close();


                $conn->commit();


                $message =
                    "Brochure approved and event published successfully.";

                $message_type = "success";


            } catch (Exception $e) {

                $conn->rollback();

                $message =
                    "Approval failed. Please try again.";

                $message_type = "error";
            }

        }


        /* =====================================================
           CORE ADMIN RETURN FOR CORRECTION
        ===================================================== */

        elseif ($action === "correction") {


            /*
             * Checklist items selected by Core Admin.
             */

            $incorrect_items =
                isset($_POST["core_incorrect_items"])
                ? $_POST["core_incorrect_items"]
                : [];


            /*
             * Additional correction details.
             */

            $correction_details =
                trim(
                    $_POST["correction_reason"] ?? ""
                );


            /*
             * Keep only valid checklist values.
             */

            $valid_incorrect_items = [];

            if (is_array($incorrect_items)) {

                foreach ($incorrect_items as $item) {

                    if (
                        in_array(
                            $item,
                            $required_items,
                            true
                        )
                    ) {

                        $valid_incorrect_items[] = $item;
                    }
                }
            }


            /*
             * At least one checklist item OR
             * additional details must be provided.
             */

            if (
                empty($valid_incorrect_items) &&
                $correction_details === ""
            ) {

                $message =
                    "Please select at least one correction item or enter correction details.";

                $message_type = "error";

            } else {


                /* ---------------------------------------------
                   BUILD CORRECTION MESSAGE
                --------------------------------------------- */

                $correction_text = "";


                if (!empty($valid_incorrect_items)) {

                    $correction_text .=
                        "Core Admin Correction Items:\n";


                    foreach (
                        $valid_incorrect_items as $item
                    ) {

                        $correction_text .=
                            "- " . $item . "\n";
                    }
                }


                if ($correction_details !== "") {

                    $correction_text .=
                        "\nAdditional Correction Details:\n";

                    $correction_text .=
                        $correction_details;
                }


                /* ---------------------------------------------
                   SAVE CORRECTION
                --------------------------------------------- */

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
                        "Brochure returned to organizer for correction.";

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to return brochure for correction.";

                    $message_type = "error";
                }


                $stmt->close();
            }

        }


        /* =====================================================
           INVALID ACTION
        ===================================================== */

        else {

            $message =
                "Invalid action.";

            $message_type = "error";
        }
    }
}


/* =========================================================
   PENDING BROCHURES
========================================================= */

$brochures = [];


$sql = "

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
        e.publication_status

    FROM event_brochures eb

    INNER JOIN events e
        ON e.event_id = eb.event_id

    WHERE eb.hod_status = 'approved'

      AND eb.status IN (
          'pending',
          'resubmitted'
      )

    ORDER BY eb.upload_date DESC

";


$result = $conn->query($sql);


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $brochures[] = $row;
    }
}


$total_pending = count($brochures);

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
    Brochure Review | Core Admin
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


/* =========================================================
   HEADER
========================================================= */

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

    padding:
        9px 15px;

    border-radius: 8px;

    font-size: 13px;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    width: 94%;

    max-width: 1250px;

    margin: 30px auto;
}


/* =========================================================
   SUMMARY
========================================================= */

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

    margin:
        0 0 6px;

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

    background: #EAF2FB;

    color: #1E4E8C;

    padding:
        8px 13px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    padding:
        14px 16px;

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


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 12px;

    padding:
        50px 20px;

    text-align: center;
}


.empty-icon {

    font-size: 42px;

    margin-bottom: 12px;
}


.empty h3 {

    margin:
        0 0 7px;
}


.empty p {

    margin: 0;

    color: #667085;

    font-size: 14px;
}


/* =========================================================
   GRID
========================================================= */

.grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(360px, 1fr)
        );

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 13px;

    overflow: hidden;
}


.card-top {

    background: #F8FAFD;

    border-bottom:
        1px solid
        #DCE4EF;

    padding: 18px;
}


.card-top h3 {

    margin:
        0 0 7px;

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

    padding:
        6px 10px;

    border-radius: 15px;

    background: #FFF4D6;

    color: #8A6100;

    font-size: 11px;

    font-weight: bold;
}


/* =========================================================
   DETAILS
========================================================= */

.details {

    padding: 18px;
}


.detail-row {

    display: flex;

    gap: 12px;

    margin-bottom: 11px;

    font-size: 13px;
}


.detail-label {

    width: 105px;

    flex-shrink: 0;

    color: #667085;

    font-weight: 600;
}


.detail-value {

    color: #172033;
}


/* =========================================================
   BROCHURE LINK
========================================================= */

.brochure-link {

    display: inline-block;

    margin-top: 5px;

    color: #1E4E8C;

    font-weight: bold;

    text-decoration: none;

    border:
        1px solid
        #B9CBE2;

    padding:
        8px 12px;

    border-radius: 7px;

    background: #EAF2FB;
}


.brochure-link:hover {

    background: #DCEAF8;

    text-decoration: underline;
}


/* =========================================================
   DIVIDER
========================================================= */

.divider {

    height: 1px;

    background: #E8EDF4;

    margin:
        18px 0;
}


/* =========================================================
   ACTION TITLE
========================================================= */

.action-title {

    font-size: 13px;

    font-weight: bold;

    color: #0B1F3A;

    margin-bottom: 10px;
}


/* =========================================================
   CORE CHECKLIST
========================================================= */

.checklist-box {

    border:
        1px solid
        #DCE4EF;

    border-radius: 9px;

    overflow: hidden;

    margin-top: 10px;

    margin-bottom: 12px;

    background: #FAFCFF;
}


.checklist-title {

    background: #F4F7FB;

    padding:
        10px 12px;

    font-size: 13px;

    font-weight: bold;

    color: #0B1F3A;

    border-bottom:
        1px solid
        #DCE4EF;
}


.check-item {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        10px 12px;

    border-bottom:
        1px solid
        #E8EDF4;

    font-size: 13px;
}


.check-item:last-child {

    border-bottom: none;
}


.check-item input {

    width: 17px;

    height: 17px;

    cursor: pointer;
}


.check-item label {

    cursor: pointer;

    color: #172033;
}


/* =========================================================
   TEXTAREA
========================================================= */

textarea {

    width: 100%;

    min-height: 95px;

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

    box-shadow:
        0 0 0 2px
        rgba(30,78,140,0.08);
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 9px;

    margin-top: 12px;
}


button {

    border: none;

    cursor: pointer;

    padding:
        10px 15px;

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


.correction-btn {

    background: #F4F7FB;

    color: #A52828;

    border:
        1px solid
        #E5B4B4;
}


.correction-btn:hover {

    background: #FDECEC;
}


/* =========================================================
   NOTE
========================================================= */

.note {

    margin-top: 8px;

    color: #667085;

    font-size: 12px;

    line-height: 1.5;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {

    .header {

        padding: 16px;

        gap: 10px;

        flex-direction: column;

        align-items: flex-start;
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


    .actions {

        flex-direction: column;
    }


    button {

        width: 100%;
    }


    .detail-row {

        flex-direction: column;

        gap: 3px;
    }


    .detail-label {

        width: auto;
    }

}

</style>

</head>


<body>


<header class="header">


    <div class="header-left">

        <h1>
            Brochure Review
        </h1>

        <p>
            Core Admin / PIMC
        </p>

    </div>


    <a
        href="core_admin_dashboard.php"
        class="back-btn"
    >
        ← Back to Dashboard
    </a>


</header>



<div class="container">


    <?php if ($message !== ""): ?>

        <div
            class="alert <?php echo h($message_type); ?>"
        >

            <?php echo h($message); ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="summary">

        <h2>
            Brochure / Publicity Material Review
        </h2>

        <p>

            Review brochures that have already received
            Department Admin approval before official publication.

        </p>


        <span class="count">

            <?php echo $total_pending; ?>

            Pending Review

        </span>

    </div>



    <!-- =====================================================
         EMPTY
    ====================================================== -->

    <?php if (empty($brochures)): ?>


        <div class="empty">

            <div class="empty-icon">
                📄
            </div>

            <h3>
                No Brochures Pending
            </h3>

            <p>

                There are currently no HOD-approved brochures
                waiting for Core Admin review.

            </p>

        </div>


    <?php else: ?>


        <!-- =================================================
             BROCHURE GRID
        ================================================== -->

        <div class="grid">


            <?php foreach ($brochures as $brochure): ?>


                <div class="card">


                    <!-- =====================================
                         CARD HEADER
                    ====================================== -->

                    <div class="card-top">

                        <h3>

                            <?php
                            echo h(
                                $brochure["event_name"]
                            );
                            ?>

                        </h3>


                        <div class="event-id">

                            Event ID:

                            <?php
                            echo h(
                                $brochure["event_id"]
                            );
                            ?>

                        </div>


                        <span class="status">

                            HOD Approved —
                            Core Review Required

                        </span>

                    </div>



                    <div class="details">


                        <!-- =================================
                             EVENT TYPE
                        ================================== -->

                        <div class="detail-row">

                            <div class="detail-label">
                                Event Type
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $brochure["event_type"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <!-- =================================
                             DATE
                        ================================== -->

                        <div class="detail-row">

                            <div class="detail-label">
                                Date
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatDateValue(
                                    $brochure["event_date"]
                                );
                                ?>

                            </div>

                        </div>



                        <!-- =================================
                             TIME
                        ================================== -->

                        <div class="detail-row">

                            <div class="detail-label">
                                Time
                            </div>

                            <div class="detail-value">


                                <?php

                                echo $brochure["start_time"]

                                    ? h(
                                        date(
                                            "h:i A",
                                            strtotime(
                                                $brochure["start_time"]
                                            )
                                        )
                                    )

                                    : "-";

                                ?>


                                -

                                <?php

                                echo $brochure["end_time"]

                                    ? h(
                                        date(
                                            "h:i A",
                                            strtotime(
                                                $brochure["end_time"]
                                            )
                                        )
                                    )

                                    : "-";

                                ?>


                            </div>

                        </div>



                        <!-- =================================
                             VENUE
                        ================================== -->

                        <div class="detail-row">

                            <div class="detail-label">
                                Venue
                            </div>

                            <div class="detail-value">

                                <?php
                                echo h(
                                    $brochure["venue"]
                                    ?: "-"
                                );
                                ?>

                            </div>

                        </div>



                        <!-- =================================
                             UPLOADED
                        ================================== -->

                        <div class="detail-row">

                            <div class="detail-label">
                                Uploaded
                            </div>

                            <div class="detail-value">

                                <?php
                                echo formatDateValue(
                                    $brochure["upload_date"]
                                );
                                ?>

                            </div>

                        </div>



                        <!-- =================================
                             BROCHURE
                        ================================== -->

                        <div class="detail-row">

                            <div class="detail-label">
                                Brochure
                            </div>

                            <div class="detail-value">


                                <?php

                                $brochure_url =
                                    brochureUrl(
                                        $brochure["brochure_file"]
                                    );

                                ?>


                                <?php if ($brochure_url !== ""): ?>


                                    <a
                                        href="<?php echo h($brochure_url); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="brochure-link"
                                    >

                                        View / Open Brochure ↗

                                    </a>


                                <?php else: ?>


                                    No brochure file found.


                                <?php endif; ?>


                            </div>

                        </div>



                        <!-- =================================
                             PREVIOUS NOTE
                        ================================== -->

                        <?php if (
                            !empty(
                                $brochure["correction_reason"]
                            )
                        ): ?>


                            <div class="divider"></div>


                            <div class="detail-row">

                                <div class="detail-label">
                                    Previous Note
                                </div>

                                <div class="detail-value">

                                    <?php
                                    echo nl2br(
                                        h(
                                            $brochure[
                                                "correction_reason"
                                            ]
                                        )
                                    );
                                    ?>

                                </div>

                            </div>


                        <?php endif; ?>



                        <div class="divider"></div>



                        <!-- =================================
                             CORE ADMIN DECISION
                        ================================== -->

                        <div class="action-title">

                            Core Admin Decision

                        </div>



                        <!-- =================================
                             APPROVE & PUBLISH
                        ================================== -->

                        <form
                            method="POST"
                            style="margin-bottom: 18px;"
                        >


                            <input
                                type="hidden"
                                name="brochure_id"
                                value="<?php
                                    echo (int)$brochure[
                                        "brochure_id"
                                    ];
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            >


                            <button
                                type="submit"
                                class="approve-btn"
                                onclick="
                                    return confirm(
                                        'Approve this brochure and officially publish the event?'
                                    );
                                "
                            >

                                ✓ Approve & Publish

                            </button>


                        </form>



                        <!-- =================================
                             RETURN FOR CORRECTION
                        ================================== -->

                        <div class="divider"></div>


                        <div class="action-title">

                            Core Admin Return for Correction

                        </div>


                        <p class="note">

                            If any brochure requirement is incorrect,
                            select the relevant checklist item(s)
                            and mention additional details below.

                        </p>


                        <form method="POST">


                            <input
                                type="hidden"
                                name="brochure_id"
                                value="<?php
                                    echo (int)$brochure[
                                        "brochure_id"
                                    ];
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="correction"
                            >



                            <!-- =================================
                                 CHECKLIST
                            ================================== -->

                            <div class="checklist-box">


                                <div class="checklist-title">

                                    Correction Checklist

                                </div>


                                <?php foreach (
                                    $required_items as $index => $item
                                ): ?>


                                    <div class="check-item">


                                        <input
                                            type="checkbox"
                                            name="core_incorrect_items[]"
                                            value="<?php
                                                echo h($item);
                                            ?>"
                                            id="<?php
                                                echo
                                                "core_"
                                                . $brochure[
                                                    "brochure_id"
                                                ]
                                                . "_"
                                                . $index;
                                            ?>"
                                        >


                                        <label
                                            for="<?php
                                                echo
                                                "core_"
                                                . $brochure[
                                                    "brochure_id"
                                                ]
                                                . "_"
                                                . $index;
                                            ?>"
                                        >

                                            <?php
                                            echo h($item);
                                            ?>

                                        </label>


                                    </div>


                                <?php endforeach; ?>


                            </div>



                            <!-- =================================
                                 CORRECTION DETAILS
                            ================================== -->

                            <label
                                for="<?php
                                    echo
                                    "reason_"
                                    . (int)$brochure[
                                        "brochure_id"
                                    ];
                                ?>"
                                style="
                                    display:block;
                                    margin-bottom:7px;
                                    font-size:13px;
                                    font-weight:bold;
                                    color:#0B1F3A;
                                "
                            >

                                Correction Details

                            </label>


                            <textarea
                                id="<?php
                                    echo
                                    "reason_"
                                    . (int)$brochure[
                                        "brochure_id"
                                    ];
                                ?>"
                                name="correction_reason"
                                placeholder="Mention what needs to be corrected..."
                            ></textarea>


                            <div class="actions">


                                <button
                                    type="submit"
                                    class="correction-btn"
                                    onclick="
                                        return confirm(
                                            'Return this brochure to the organizer for correction?'
                                        );
                                    "
                                >

                                    ↩ Return for Correction

                                </button>


                            </div>


                        </form>


                    </div>

                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>


</body>

</html>