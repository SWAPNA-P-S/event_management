<?php

session_start();

require_once "../db.php";


// --------------------------------------------------
// CHECK LOGIN
// --------------------------------------------------

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$user_role = $_SESSION["role"] ?? "";


// --------------------------------------------------
// CHECK ORGANIZER ACCESS
// --------------------------------------------------

$is_organizer = false;


// Existing organizer role
if ($user_role === "organizer") {
    $is_organizer = true;
}


// Approved student-organizer
if ($user_role === "student") {

    $organizer_check = $conn->prepare("
        SELECT tm.team_member_id
        FROM organizer_team_members tm
        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id
        WHERE tm.user_id = ?
          AND r.status = 'approved'
        LIMIT 1
    ");

    $organizer_check->bind_param("i", $user_id);
    $organizer_check->execute();

    $organizer_result = $organizer_check->get_result();

    if ($organizer_result->num_rows > 0) {

        $is_organizer = true;
        $_SESSION["is_organizer"] = true;
    }

    $organizer_check->close();
}


if (!$is_organizer) {
    header("Location: ../login.html");
    exit();
}


// --------------------------------------------------
// CHECK EVENT ID
// --------------------------------------------------

if (!isset($_GET["event_id"]) || !is_numeric($_GET["event_id"])) {
    die("Invalid Event ID.");
}

$event_id = (int)$_GET["event_id"];


// --------------------------------------------------
// FETCH EVENT
// --------------------------------------------------

if ($user_role === "organizer") {

    $stmt = $conn->prepare("
        SELECT event_id,
               event_name,
               event_date,
               venue,
               request_status
        FROM events
        WHERE event_id = ?
          AND organizer_id = ?
    ");

    $stmt->bind_param("ii", $event_id, $user_id);

} else {

    $stmt = $conn->prepare("
        SELECT e.event_id,
               e.event_name,
               e.event_date,
               e.venue,
               e.request_status
        FROM events e
        INNER JOIN organizer_team_members tm
            ON e.organizer_request_id = tm.request_id
        INNER JOIN organizer_requests r
            ON tm.request_id = r.request_id
        WHERE e.event_id = ?
          AND tm.user_id = ?
          AND r.status = 'approved'
    ");

    $stmt->bind_param("ii", $event_id, $user_id);
}

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("You do not have permission to manage this event.");
}

$event = $result->fetch_assoc();

$stmt->close();


// --------------------------------------------------
// MESSAGE
// --------------------------------------------------

$message = "";
$message_type = "";


// --------------------------------------------------
// UPLOAD / RESUBMIT PROCESSING
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_FILES["brochure"])) {

        $message = "Please select a brochure file.";
        $message_type = "error";

    } else {

        $file = $_FILES["brochure"];

        if ($file["error"] !== UPLOAD_ERR_OK) {

            $message = "File upload failed.";
            $message_type = "error";

        } else {

            $file_name = $file["name"];
            $file_tmp = $file["tmp_name"];
            $file_size = $file["size"];

            $extension = strtolower(
                pathinfo($file_name, PATHINFO_EXTENSION)
            );

            $allowed = ["pdf", "jpg", "jpeg", "png"];


            if (!in_array($extension, $allowed)) {

                $message =
                    "Only PDF, JPG, JPEG and PNG files are allowed.";

                $message_type = "error";

            } elseif ($file_size > 10 * 1024 * 1024) {

                $message =
                    "File size must be below 10 MB.";

                $message_type = "error";

            } else {

                $new_file_name =
                    "brochure_" .
                    $event_id .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $upload_path =
                    "../uploads/" .
                    $new_file_name;


                if (move_uploaded_file($file_tmp, $upload_path)) {


                    // --------------------------------------------------
                    // CHECK EXISTING BROCHURE
                    // --------------------------------------------------

                    $check = $conn->prepare("
                        SELECT brochure_id, status, brochure_file
                        FROM event_brochures
                        WHERE event_id = ?
                        LIMIT 1
                    ");

                    $check->bind_param("i", $event_id);
                    $check->execute();

                    $check_result = $check->get_result();


                    if ($check_result->num_rows > 0) {

                        $existing_brochure =
                            $check_result->fetch_assoc();

                        $old_file =
                            $existing_brochure["brochure_file"];

                        $old_status =
                            $existing_brochure["status"];


                        // --------------------------------------------------
                        // UPDATE SAME BROCHURE RECORD
                        // --------------------------------------------------

                        if ($old_status === "correction_required") {

                            $new_status = "resubmitted";

                        } else {

                            $new_status = "pending";
                        }


                        $update = $conn->prepare("
                            UPDATE event_brochures
                            SET brochure_file = ?,
                                upload_date = CURRENT_TIMESTAMP,
                                status = ?,
                                correction_reason = NULL
                            WHERE brochure_id = ?
                        ");

                        $update->bind_param(
                            "ssi",
                            $new_file_name,
                            $new_status,
                            $existing_brochure["brochure_id"]
                        );


                        if ($update->execute()) {


                            // --------------------------------------------------
                            // DELETE OLD FILE
                            // --------------------------------------------------

                            if (
                                !empty($old_file) &&
                                $old_file !== $new_file_name
                            ) {

                                $old_file_path =
                                    "../uploads/" . $old_file;

                                if (file_exists($old_file_path)) {
                                    unlink($old_file_path);
                                }
                            }


                            if ($new_status === "resubmitted") {

                                $message =
                                    "Brochure resubmitted successfully and sent for Admin review.";

                            } else {

                                $message =
                                    "Brochure uploaded successfully and sent for Admin review.";
                            }

                            $message_type = "success";

                        } else {

                            $message =
                                "Failed to update brochure.";

                            $message_type = "error";

                            // Remove newly uploaded file
                            if (file_exists($upload_path)) {
                                unlink($upload_path);
                            }
                        }

                        $update->close();


                    } else {


                        // --------------------------------------------------
                        // FIRST BROCHURE UPLOAD
                        // --------------------------------------------------

                        $insert = $conn->prepare("
                            INSERT INTO event_brochures
                            (
                                event_id,
                                brochure_file,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                'pending'
                            )
                        ");

                        $insert->bind_param(
                            "is",
                            $event_id,
                            $new_file_name
                        );


                        if ($insert->execute()) {

                            $message =
                                "Brochure uploaded successfully and sent for Admin review.";

                            $message_type = "success";

                        } else {

                            $message =
                                "Failed to save brochure.";

                            $message_type = "error";

                            // Remove newly uploaded file
                            if (file_exists($upload_path)) {
                                unlink($upload_path);
                            }
                        }

                        $insert->close();
                    }


                    $check->close();


                } else {

                    $message =
                        "Unable to save the uploaded file.";

                    $message_type = "error";
                }
            }
        }
    }
}


// --------------------------------------------------
// GET CURRENT BROCHURE
// --------------------------------------------------

$brochure = null;

$stmt2 = $conn->prepare("
    SELECT brochure_id,
           brochure_file,
           upload_date,
           status,
           correction_reason
    FROM event_brochures
    WHERE event_id = ?
    LIMIT 1
");

$stmt2->bind_param("i", $event_id);
$stmt2->execute();

$result2 = $stmt2->get_result();

if ($result2->num_rows > 0) {
    $brochure = $result2->fetch_assoc();
}

$stmt2->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Upload Brochure | Organizer</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #eef2f7;
    color: #172033;
}

.header {
    background: #0b1f3a;
    color: white;
    padding: 20px 35px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 24px;
}

.back-btn {
    text-decoration: none;
    color: white;
    background: #1769e0;
    padding: 10px 18px;
    border-radius: 7px;
}

.container {
    max-width: 850px;
    margin: 35px auto;
    padding: 0 20px;
}

.event-card,
.upload-card,
.status-card {
    background: white;
    padding: 25px;
    border-radius: 12px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.08);

    margin-bottom: 20px;
}

.event-card h2 {
    color: #0b1f3a;
    margin-bottom: 10px;
}

.event-info {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 18px;
}

.info {
    background: #f2f5fa;
    padding: 12px 16px;
    border-radius: 8px;
}

.info strong {
    color: #0b1f3a;
}

.upload-card h2,
.status-card h2 {
    color: #0b1f3a;
    margin-bottom: 18px;
}

.note {
    background: #eef5ff;
    border-left: 4px solid #1769e0;

    padding: 14px;
    margin-bottom: 20px;

    color: #43536b;
    line-height: 1.5;
}

input[type="file"] {
    width: 100%;

    padding: 15px;

    border:
        2px dashed #b9c5d6;

    border-radius: 8px;

    background: #f8fafc;

    cursor: pointer;
}

.upload-btn {
    margin-top: 18px;

    background: #1769e0;
    color: white;

    border: none;

    padding: 13px 25px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 15px;
}

.upload-btn:hover {
    background: #0d55bd;
}

.resubmit-btn {
    background: #d97706;
}

.resubmit-btn:hover {
    background: #b65f04;
}

.message {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success {
    background: #dff5e5;
    color: #1d7a35;
}

.error {
    background: #ffe1e1;
    color: #b42323;
}

.status {
    display: inline-block;

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}

.status.pending {
    background: #fff2cc;
    color: #956f00;
}

.status.approved {
    background: #dff5e5;
    color: #218838;
}

.status.correction {
    background: #ffe4cc;
    color: #b35a00;
}

.status.resubmitted {
    background: #e3edff;
    color: #1769e0;
}

.view-btn {
    display: inline-block;

    margin-top: 15px;

    text-decoration: none;

    background: #e8edf5;

    color: #172033;

    padding: 10px 16px;

    border-radius: 7px;
}

.correction {
    margin-top: 15px;

    background: #fff5eb;

    padding: 15px;

    border-radius: 8px;

    color: #8a4b08;

    line-height: 1.6;
}

.correction-title {
    font-weight: bold;
    margin-bottom: 8px;
}

.correction-line {
    background: white;

    padding: 9px 11px;

    margin-top: 7px;

    border-radius: 6px;

    border-left: 4px solid #d97706;
}

@media(max-width:600px) {

    .header {
        padding: 18px;
    }

    .header h1 {
        font-size: 19px;
    }

    .event-info {
        flex-direction: column;
    }

}

</style>

</head>


<body>


<div class="header">

    <h1>
        Organizer | Brochure Upload
    </h1>


    <!-- BACK TO ORGANIZER DASHBOARD -->

    <a
        href="organizer_dashboard.php"
        class="back-btn">

        ← Back

    </a>

</div>


<div class="container">


    <!-- EVENT -->

    <div class="event-card">

        <h2>

            <?php
            echo htmlspecialchars(
                $event["event_name"]
            );
            ?>

        </h2>


        <p>
            Upload the brochure prepared by the organizing committee.
        </p>


        <div class="event-info">


            <div class="info">

                <strong>Date:</strong>

                <?php
                echo htmlspecialchars(
                    $event["event_date"]
                );
                ?>

            </div>


            <div class="info">

                <strong>Venue:</strong>

                <?php
                echo htmlspecialchars(
                    $event["venue"]
                );
                ?>

            </div>


        </div>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div
            class="message
            <?php echo $message_type; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         CORRECTION DETAILS
    ================================================== -->

    <?php if (
        $brochure &&
        $brochure["status"] === "correction_required"
    ): ?>

        <div class="status-card">

            <h2>
                ⚠ Correction Required
            </h2>


            <p
                style="
                    color:#697386;
                    margin-bottom:15px;
                    line-height:1.5;
                "
            >
                Admin has reviewed your brochure and marked
                one or more items for correction. Please make
                the required changes and resubmit the brochure.
            </p>


            <?php if (
                !empty($brochure["correction_reason"])
            ): ?>

                <div class="correction">

                    <div class="correction-title">
                        Admin Correction Details:
                    </div>


                    <?php

                    $correction_lines =
                        explode(
                            "\n",
                            $brochure["correction_reason"]
                        );

                    foreach ($correction_lines as $line):

                        $line = trim($line);

                        if ($line === "") {
                            continue;
                        }

                    ?>

                        <div class="correction-line">

                            ❌
                            <?php
                            echo htmlspecialchars($line);
                            ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         UPLOAD / RESUBMIT
    ================================================== -->

    <div class="upload-card">

        <h2>

            <?php
            if (
                $brochure &&
                $brochure["status"] === "correction_required"
            ) {
                echo "Resubmit Brochure";
            } else {
                echo "Upload Brochure";
            }
            ?>

        </h2>


        <?php if (
            $brochure &&
            $brochure["status"] === "correction_required"
        ): ?>

            <div
                class="note"
                style="
                    border-left-color:#d97706;
                    background:#fff8ef;
                "
            >

                Make the required corrections in your brochure
                and upload the corrected file here.

                <br><br>

                <strong>
                    The same brochure record will be updated
                    and sent back to Admin for review.
                </strong>

            </div>

        <?php else: ?>

            <div class="note">

                The organizer should prepare the brochure separately
                and upload the final file here.

                <br><br>

                The uploaded brochure will be reviewed by the Admin
                before it becomes publicly visible.

            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data">


            <input
                type="file"
                name="brochure"
                accept=".pdf,.jpg,.jpeg,.png"
                required>


            <?php if (
                $brochure &&
                $brochure["status"] === "correction_required"
            ): ?>

                <button
                    type="submit"
                    class="upload-btn resubmit-btn">

                    🔄 Resubmit Brochure

                </button>

            <?php else: ?>

                <button
                    type="submit"
                    class="upload-btn">

                    📤 Upload Brochure

                </button>

            <?php endif; ?>


        </form>

    </div>


    <!-- =================================================
         CURRENT STATUS
    ================================================== -->

    <?php if ($brochure): ?>

    <div class="status-card">

        <h2>
            Brochure Status
        </h2>


        <?php if (
            $brochure["status"] === "pending"
        ): ?>

            <span class="status pending">

                Pending Admin Review

            </span>


        <?php elseif (
            $brochure["status"] === "resubmitted"
        ): ?>

            <span class="status resubmitted">

                🔄 Resubmitted — Pending Admin Review

            </span>


        <?php elseif (
            $brochure["status"] === "approved"
        ): ?>

            <span class="status approved">

                ✓ Approved

            </span>


        <?php elseif (
            $brochure["status"] === "correction_required"
        ): ?>

            <span class="status correction">

                ⚠ Correction Required

            </span>

        <?php endif; ?>


        <br>


        <a
            href="../uploads/<?php
                echo htmlspecialchars(
                    $brochure["brochure_file"]
                );
            ?>"
            target="_blank"
            class="view-btn">

            👁 View Uploaded Brochure

        </a>


    </div>

    <?php endif; ?>


</div>


</body>

</html>