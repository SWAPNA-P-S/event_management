<?php

session_start();

require_once "../db.php";


// ==========================================================
// HELPER
// ==========================================================

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


// ==========================================================
// LOGIN CHECK
// ==========================================================

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

$user_role = $_SESSION["role"] ?? "";


// ==========================================================
// ORGANIZER ACCESS CHECK
// ==========================================================

$is_organizer = false;


// Existing organizer role
if ($user_role === "organizer") {

    $is_organizer = true;
}


// Approved student-organizer
if ($user_role === "student") {

    $check = $conn->prepare("
        SELECT tm.team_member_id
        FROM organizer_team_members tm

        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id

        WHERE tm.user_id = ?
          AND r.status = 'approved'

        LIMIT 1
    ");

    $check->bind_param(
        "i",
        $user_id
    );

    $check->execute();

    $result = $check->get_result();

    if ($result->num_rows > 0) {

        $is_organizer = true;

        $_SESSION["is_organizer"] = true;
    }

    $check->close();
}


if (!$is_organizer) {

    header("Location: ../login.html");
    exit();
}


// ==========================================================
// EVENT ID CHECK
// ==========================================================

if (
    !isset($_GET["event_id"])
    ||
    !is_numeric($_GET["event_id"])
) {

    die("Invalid Event ID.");
}

$event_id = (int)$_GET["event_id"];


// ==========================================================
// FETCH EVENT
// ==========================================================

$event = null;


if ($user_role === "organizer") {

    $event_stmt = $conn->prepare("
        SELECT
            event_id,
            event_name,
            event_date,
            start_time,
            end_time,
            venue,
            event_type,
            status,
            request_status,
            organizer_id,
            organizer_request_id
        FROM events

        WHERE event_id = ?
          AND organizer_id = ?

        LIMIT 1
    ");

    $event_stmt->bind_param(
        "ii",
        $event_id,
        $user_id
    );

} else {

    $event_stmt = $conn->prepare("
        SELECT DISTINCT
            e.event_id,
            e.event_name,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.event_type,
            e.status,
            e.request_status,
            e.organizer_id,
            e.organizer_request_id

        FROM events e

        INNER JOIN organizer_team_members tm
            ON e.organizer_request_id = tm.request_id

        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id

        WHERE e.event_id = ?
          AND tm.user_id = ?
          AND r.status = 'approved'

        LIMIT 1
    ");

    $event_stmt->bind_param(
        "ii",
        $event_id,
        $user_id
    );
}


$event_stmt->execute();

$event_result =
    $event_stmt->get_result();


if ($event_result->num_rows === 0) {

    die(
        "You do not have permission to manage this event."
    );
}


$event =
    $event_result->fetch_assoc();


$event_stmt->close();


// ==========================================================
// FETCH EXISTING BROCHURE
// ==========================================================

$brochure = null;


$brochure_stmt = $conn->prepare("
    SELECT
        brochure_id,
        event_id,
        brochure_file,
        upload_date,
        status,
        correction_reason

    FROM event_brochures

    WHERE event_id = ?

    ORDER BY brochure_id DESC

    LIMIT 1
");


$brochure_stmt->bind_param(
    "i",
    $event_id
);


$brochure_stmt->execute();


$brochure_result =
    $brochure_stmt->get_result();


if ($brochure_result->num_rows > 0) {

    $brochure =
        $brochure_result->fetch_assoc();
}


$brochure_stmt->close();


// ==========================================================
// MESSAGE VARIABLES
// ==========================================================

$error = "";

$success = "";


// ==========================================================
// HANDLE BROCHURE UPLOAD
// ==========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    // ------------------------------------------------------
    // CHECK FILE
    // ------------------------------------------------------

    if (
        !isset($_FILES["brochure"])
        ||
        $_FILES["brochure"]["error"]
        === UPLOAD_ERR_NO_FILE
    ) {

        $error =
            "Please select a brochure file.";
    }


    // ------------------------------------------------------
    // FILE ERROR
    // ------------------------------------------------------

    if (
        empty($error)
        &&
        $_FILES["brochure"]["error"]
        !== UPLOAD_ERR_OK
    ) {

        $error =
            "Brochure upload failed. Please try again.";
    }


    // ------------------------------------------------------
    // FILE VALIDATION
    // ------------------------------------------------------

    $new_file_name = "";

    $uploaded_file = null;


    if (empty($error)) {

        $uploaded_file =
            $_FILES["brochure"];


        $original_name =
            $uploaded_file["name"];


        $temporary_name =
            $uploaded_file["tmp_name"];


        $file_size =
            (int)$uploaded_file["size"];


        $extension =
            strtolower(
                pathinfo(
                    $original_name,
                    PATHINFO_EXTENSION
                )
            );


        $allowed_extensions = [
            "pdf",
            "jpg",
            "jpeg",
            "png"
        ];


        if (
            !in_array(
                $extension,
                $allowed_extensions,
                true
            )
        ) {

            $error =
                "Only PDF, JPG, JPEG and PNG brochure files are allowed.";
        }


        if (
            empty($error)
            &&
            $file_size > 10 * 1024 * 1024
        ) {

            $error =
                "Brochure file size must not exceed 10 MB.";
        }


        if (empty($error)) {

            $new_file_name =
                "brochure_"
                .
                $event_id
                .
                "_"
                .
                time()
                .
                "."
                .
                $extension;
        }
    }


    // ------------------------------------------------------
    // SAVE FILE
    // ------------------------------------------------------

    if (
        empty($error)
        &&
        $uploaded_file
    ) {

        $upload_directory =
            "../uploads/";


        if (
            !is_dir(
                $upload_directory
            )
        ) {

            mkdir(
                $upload_directory,
                0777,
                true
            );
        }


        $upload_path =
            $upload_directory
            .
            $new_file_name;


        if (
            !move_uploaded_file(
                $uploaded_file["tmp_name"],
                $upload_path
            )
        ) {

            $error =
                "Unable to save the brochure file.";
        }
    }


    // ------------------------------------------------------
    // DATABASE SAVE
    // ------------------------------------------------------

    if (
        empty($error)
        &&
        !empty($new_file_name)
    ) {

        $old_file = "";


        if ($brochure) {

            $old_file =
                $brochure["brochure_file"];
        }


        /*
         * New submission status:
         *
         * correction_required
         *      -> resubmitted
         *
         * anything else
         *      -> pending
         */

        if (
            $brochure
            &&
            $brochure["status"]
            === "correction_required"
        ) {

            $new_status =
                "resubmitted";

        } else {

            $new_status =
                "pending";
        }


        $conn->begin_transaction();


        try {


            // ==================================================
            // UPDATE EXISTING BROCHURE
            // ==================================================

            if ($brochure) {

                $update =
                    $conn->prepare("
                        UPDATE event_brochures

                        SET
                            brochure_file = ?,
                            upload_date = CURRENT_TIMESTAMP,
                            status = ?,
                            correction_reason = NULL

                        WHERE brochure_id = ?
                    ");


                $update->bind_param(
                    "ssi",
                    $new_file_name,
                    $new_status,
                    $brochure["brochure_id"]
                );


                if (
                    !$update->execute()
                ) {

                    throw new Exception(
                        "Failed to update brochure."
                    );
                }


                $update->close();


            } else {


                // ==================================================
                // INSERT NEW BROCHURE
                // ==================================================

                $insert =
                    $conn->prepare("
                        INSERT INTO event_brochures
                        (
                            event_id,
                            brochure_file,
                            upload_date,
                            status,
                            correction_reason
                        )

                        VALUES
                        (
                            ?,
                            ?,
                            CURRENT_TIMESTAMP,
                            'pending',
                            NULL
                        )
                    ");


                $insert->bind_param(
                    "is",
                    $event_id,
                    $new_file_name
                );


                if (
                    !$insert->execute()
                ) {

                    throw new Exception(
                        "Failed to save brochure."
                    );
                }


                $insert->close();
            }


            $conn->commit();


            // ==================================================
            // DELETE OLD FILE
            // ==================================================

            if (
                !empty($old_file)
                &&
                $old_file !== $new_file_name
            ) {

                $old_path =
                    "../uploads/"
                    .
                    $old_file;


                if (
                    file_exists($old_path)
                ) {

                    unlink($old_path);
                }
            }


            // ==================================================
            // SUCCESS
            // ==================================================

            $success =
                "Brochure uploaded successfully and sent for review.";


            // Refresh brochure information
            $brochure_stmt =
                $conn->prepare("
                    SELECT
                        brochure_id,
                        event_id,
                        brochure_file,
                        upload_date,
                        status,
                        correction_reason

                    FROM event_brochures

                    WHERE event_id = ?

                    ORDER BY brochure_id DESC

                    LIMIT 1
                ");


            $brochure_stmt->bind_param(
                "i",
                $event_id
            );


            $brochure_stmt->execute();


            $brochure_result =
                $brochure_stmt->get_result();


            if (
                $brochure_result->num_rows > 0
            ) {

                $brochure =
                    $brochure_result->fetch_assoc();
            }


            $brochure_stmt->close();


        } catch (Exception $e) {


            $conn->rollback();


            // Remove uploaded file
            if (
                file_exists($upload_path)
            ) {

                unlink($upload_path);
            }


            $error =
                "Unable to submit brochure. "
                .
                $e->getMessage();
        }
    }
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
    Brochure Upload | Organizer
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


.container {

    width: 92%;

    max-width: 1000px;

    margin: 35px auto 60px;
}


.back {

    display: inline-block;

    margin-bottom: 20px;

    color: #1E4E8C;

    text-decoration: none;

    font-weight: 600;

    font-size: 14px;
}


.back:hover {

    text-decoration: underline;
}


.page-header {

    background: #0B1F3A;

    color: white;

    border-radius: 14px;

    padding: 25px;

    margin-bottom: 25px;
}


.page-header h1 {

    margin: 0 0 8px;

    font-size: 27px;
}


.page-header p {

    margin: 0;

    color: #D7E3F2;

    line-height: 1.6;
}


/* ==========================================================
   EVENT CARD
========================================================== */

.event-card {

    background: white;

    border: 1px solid #DCE4EF;

    border-radius: 14px;

    padding: 22px;

    margin-bottom: 22px;

    box-shadow:
        0 4px 14px rgba(11, 31, 58, 0.05);
}


.event-card h2 {

    margin: 0 0 18px;

    color: #0B1F3A;

    font-size: 21px;
}


.event-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 14px;
}


.event-item {

    background: #F4F7FB;

    border-radius: 9px;

    padding: 13px;
}


.event-label {

    color: #718096;

    font-size: 12px;

    margin-bottom: 5px;
}


.event-value {

    color: #172033;

    font-size: 14px;

    font-weight: 600;
}


/* ==========================================================
   MAIN CARD
========================================================== */

.main-card {

    background: white;

    border: 1px solid #DCE4EF;

    border-radius: 14px;

    padding: 25px;

    box-shadow:
        0 4px 14px rgba(11, 31, 58, 0.05);
}


.main-card h2 {

    margin-top: 0;

    color: #0B1F3A;
}


/* ==========================================================
   INFORMATION
========================================================== */

.info-box {

    background: #EAF2FB;

    border: 1px solid #C9DDF2;

    border-radius: 10px;

    padding: 16px;

    margin: 18px 0;

    color: #25405F;

    line-height: 1.6;

    font-size: 14px;
}


/* ==========================================================
   OFFICIAL CHECKLIST
========================================================== */

.checklist-card {

    background: #F8FAFD;

    border: 1px solid #DCE4EF;

    border-radius: 11px;

    padding: 18px;

    margin: 20px 0;
}


.checklist-card h3 {

    margin-top: 0;

    color: #0B1F3A;

    font-size: 16px;
}


.checklist-card ul {

    margin: 0;

    padding-left: 22px;
}


.checklist-card li {

    margin-bottom: 9px;

    color: #4A5568;

    line-height: 1.5;

    font-size: 13px;
}


/* ==========================================================
   CURRENT BROCHURE
========================================================== */

.current-brochure {

    border: 1px solid #DCE4EF;

    background: #F8FAFD;

    border-radius: 10px;

    padding: 16px;

    margin: 20px 0;
}


.current-brochure h3 {

    margin-top: 0;

    color: #0B1F3A;

    font-size: 16px;
}


.current-file {

    background: white;

    border: 1px solid #DCE4EF;

    border-radius: 8px;

    padding: 12px;

    font-size: 13px;

    color: #4A5568;

    word-break: break-word;
}


.status {

    display: inline-block;

    margin-top: 10px;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.status-pending {

    background: #FEF3C7;

    color: #92400E;
}


.status-approved {

    background: #DCFCE7;

    color: #166534;
}


.status-correction {

    background: #FEE2E2;

    color: #991B1B;
}


.status-resubmitted {

    background: #DBEAFE;

    color: #1E40AF;
}


/* ==========================================================
   FORM
========================================================== */

.form-group {

    margin-top: 22px;
}


.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #172033;

    font-size: 14px;

    font-weight: bold;
}


.file-input {

    width: 100%;

    padding: 14px;

    background: #F8FAFD;

    border: 2px dashed #B9C8DA;

    border-radius: 10px;

    cursor: pointer;
}


.file-input:hover {

    border-color: #1E4E8C;

    background: #F4F8FD;
}


.file-note {

    margin-top: 8px;

    color: #718096;

    font-size: 12px;

    line-height: 1.5;
}


/* ==========================================================
   ALERTS
========================================================== */

.alert {

    border-radius: 10px;

    padding: 14px 16px;

    margin-bottom: 20px;

    line-height: 1.5;

    font-size: 14px;
}


.alert-success {

    background: #DCFCE7;

    border: 1px solid #86EFAC;

    color: #166534;
}


.alert-error {

    background: #FEE2E2;

    border: 1px solid #FCA5A5;

    color: #991B1B;
}


/* ==========================================================
   CORRECTION
========================================================== */

.correction-box {

    background: #FFF7ED;

    border: 1px solid #FDBA74;

    border-radius: 10px;

    padding: 16px;

    margin: 20px 0;
}


.correction-box h3 {

    margin-top: 0;

    color: #9A3412;

    font-size: 16px;
}


.correction-box p {

    margin-bottom: 0;

    color: #7C2D12;

    line-height: 1.6;

    font-size: 14px;
}


/* ==========================================================
   BUTTONS
========================================================== */

.buttons {

    margin-top: 25px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.btn {

    display: inline-block;

    border: none;

    border-radius: 8px;

    padding: 12px 20px;

    font-size: 14px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;
}


.upload-btn {

    background: #1E4E8C;

    color: white;
}


.upload-btn:hover {

    background: #173D70;
}


.cancel-btn {

    background: #E2E8F0;

    color: #172033;
}


.cancel-btn:hover {

    background: #CBD5E1;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media(max-width: 700px) {

    .container {

        width: 95%;

        margin-top: 20px;
    }


    .event-grid {

        grid-template-columns: 1fr;
    }


    .page-header h1 {

        font-size: 23px;
    }


    .main-card,
    .event-card {

        padding: 18px;
    }

}

</style>

</head>


<body>


<div class="container">


<!-- ========================================================
     BACK
======================================================== -->

<a
    href="planning.php?event_id=<?php echo $event_id; ?>"
    class="back"
>
    ← Back to Event Planning
</a>


<!-- ========================================================
     HEADER
======================================================== -->

<div class="page-header">

    <h1>
        📄 Event Brochure
    </h1>

    <p>
        Upload the official brochure/publicity material
        for your event. The brochure will be submitted
        for administrative scrutiny before publication.
    </p>

</div>


<!-- ========================================================
     SUCCESS
======================================================== -->

<?php if ($success !== ""): ?>

    <div class="alert alert-success">

        ✓

        <?php
        echo h($success);
        ?>

    </div>

<?php endif; ?>


<!-- ========================================================
     ERROR
======================================================== -->

<?php if ($error !== ""): ?>

    <div class="alert alert-error">

        ⚠️

        <?php
        echo h($error);
        ?>

    </div>

<?php endif; ?>


<!-- ========================================================
     EVENT DETAILS
======================================================== -->

<div class="event-card">

    <h2>
        <?php
        echo h(
            $event["event_name"]
        );
        ?>
    </h2>


    <div class="event-grid">


        <div class="event-item">

            <div class="event-label">
                Event Date
            </div>

            <div class="event-value">

                <?php

                echo h(
                    date(
                        "d M Y",
                        strtotime(
                            $event["event_date"]
                        )
                    )
                );

                ?>

            </div>

        </div>


        <div class="event-item">

            <div class="event-label">
                Time
            </div>

            <div class="event-value">

                <?php

                echo h(
                    date(
                        "h:i A",
                        strtotime(
                            $event["start_time"]
                        )
                    )
                );

                ?>

                -

                <?php

                echo h(
                    date(
                        "h:i A",
                        strtotime(
                            $event["end_time"]
                        )
                    )
                );

                ?>

            </div>

        </div>


        <div class="event-item">

            <div class="event-label">
                Venue
            </div>

            <div class="event-value">

                <?php

                echo h(
                    $event["venue"]
                    ?: "Not specified"
                );

                ?>

            </div>

        </div>


        <div class="event-item">

            <div class="event-label">
                Event Type
            </div>

            <div class="event-value">

                <?php

                echo h(
                    $event["event_type"]
                    ?: "Not specified"
                );

                ?>

            </div>

        </div>


    </div>

</div>


<!-- ========================================================
     MAIN BROCHURE CARD
======================================================== -->

<div class="main-card">


    <h2>
        Upload Brochure
    </h2>


    <div class="info-box">

        The brochure should contain the required institutional
        details, programme information, organizing details and
        contact information prescribed by the college.

        <br><br>

        After submission, the brochure will remain under
        <strong>administrative review</strong> until it is
        approved for official publication.

    </div>


    <!-- ====================================================
         CORRECTION MESSAGE
    ==================================================== -->

    <?php if (
        $brochure
        &&
        $brochure["status"]
        === "correction_required"
    ): ?>


        <div class="correction-box">

            <h3>
                ⚠️ Correction Required
            </h3>


            <p>

                <?php

                echo h(
                    $brochure["correction_reason"]
                    ?:
                    "Please upload a corrected brochure."
                );

                ?>

                <br><br>

                Upload the corrected brochure below.
                After resubmission, it will be sent for
                review again.

            </p>

        </div>


    <?php endif; ?>


    <!-- ====================================================
         CURRENT BROCHURE
    ==================================================== -->

    <?php if ($brochure): ?>


        <div class="current-brochure">

            <h3>
                Current Brochure
            </h3>


            <div class="current-file">

                📄

                <?php

                echo h(
                    $brochure["brochure_file"]
                );

                ?>


                <br>


                <span
                    class="status
                    <?php

                    if (
                        $brochure["status"]
                        === "approved"
                    ) {

                        echo "status-approved";

                    } elseif (
                        $brochure["status"]
                        === "correction_required"
                    ) {

                        echo "status-correction";

                    } elseif (
                        $brochure["status"]
                        === "resubmitted"
                    ) {

                        echo "status-resubmitted";

                    } else {

                        echo "status-pending";
                    }

                    ?>"
                >

                    <?php

                    echo h(
                        ucfirst(
                            str_replace(
                                "_",
                                " ",
                                $brochure["status"]
                            )
                        )
                    );

                    ?>

                </span>


                <br><br>


                Uploaded on:

                <?php

                echo h(
                    date(
                        "d M Y, h:i A",
                        strtotime(
                            $brochure["upload_date"]
                        )
                    )
                );

                ?>

            </div>

        </div>


    <?php endif; ?>


    <!-- ====================================================
         OFFICIAL BROCHURE CHECKLIST
    ==================================================== -->

    <div class="checklist-card">


        <h3>
            Official Brochure Checklist
        </h3>


        <ul>

            <li>
                Official college emblem/seal and required
                institutional emblems should be placed correctly.
            </li>

            <li>
                The mandatory institutional text should be included.
            </li>

            <li>
                Programme title and nature of programme should
                be clearly mentioned.
            </li>

            <li>
                Target audience should be mentioned where applicable.
            </li>

            <li>
                Date, day, time and venue should be clearly shown.
            </li>

            <li>
                Organizing department, cell or club details should
                be included.
            </li>

            <li>
                Principal, Convenor, Staff Coordinator and Student
                Coordinator/contact details should be provided
                where applicable.
            </li>

            <li>
                Official college contact information should be included.
            </li>

            <li>
                External supporting/funding body details should be
                included where applicable.
            </li>

        </ul>


    </div>


    <!-- ====================================================
         UPLOAD FORM
    ==================================================== -->

    <form
        method="POST"
        enctype="multipart/form-data"
        id="brochureForm"
    >


        <div class="form-group">


            <label for="brochure">

                <?php

                if (
                    $brochure
                    &&
                    $brochure["status"]
                    === "correction_required"
                ) {

                    echo "Upload Corrected Brochure *";

                } elseif ($brochure) {

                    echo "Upload New Brochure *";

                } else {

                    echo "Upload Brochure *";
                }

                ?>

            </label>


            <input
                type="file"
                name="brochure"
                id="brochure"
                class="file-input"
                accept=".pdf,.jpg,.jpeg,.png"
                required
            >


            <div class="file-note">

                Allowed formats:
                PDF, JPG, JPEG, PNG

                <br>

                Maximum file size:
                10 MB

            </div>


        </div>


        <!-- ==================================================
             BUTTONS
        ================================================== -->

        <div class="buttons">


            <button
                type="submit"
                class="btn upload-btn"
                id="uploadButton"
            >

                📄

                <?php

                if (
                    $brochure
                    &&
                    $brochure["status"]
                    === "correction_required"
                ) {

                    echo "Resubmit Brochure";

                } else {

                    echo "Submit Brochure";
                }

                ?>

            </button>


            <a
                href="planning.php?event_id=<?php echo $event_id; ?>"
                class="btn cancel-btn"
            >

                Cancel

            </a>


        </div>


    </form>


</div>


</div>


<script>

const brochureForm =
    document.getElementById(
        "brochureForm"
    );


const brochureInput =
    document.getElementById(
        "brochure"
    );


const uploadButton =
    document.getElementById(
        "uploadButton"
    );


brochureForm.addEventListener(
    "submit",
    function(event)
    {

        if (
            !brochureInput.files.length
        ) {

            event.preventDefault();

            alert(
                "Please select a brochure file."
            );

            return;
        }


        const file =
            brochureInput.files[0];


        const allowedExtensions = [
            "pdf",
            "jpg",
            "jpeg",
            "png"
        ];


        const extension =
            file.name
            .split(".")
            .pop()
            .toLowerCase();


        if (
            !allowedExtensions.includes(
                extension
            )
        ) {

            event.preventDefault();

            alert(
                "Only PDF, JPG, JPEG and PNG files are allowed."
            );

            return;
        }


        if (
            file.size >
            10 * 1024 * 1024
        ) {

            event.preventDefault();

            alert(
                "Brochure file size must not exceed 10 MB."
            );

            return;
        }


        uploadButton.disabled =
            true;


        uploadButton.textContent =
            "Uploading brochure...";

    }
);

</script>


</body>

</html>


<?php

$conn->close();

?>