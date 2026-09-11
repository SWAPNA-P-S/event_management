<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.html");
    exit();
}

require_once "../db.php";

$message = "";
$message_type = "";


/* =====================================================
   CHECKLIST ITEMS
===================================================== */

$required_items = [

    "College Logo",
    "College Name",
    "College Address",
    "College ID / Event ID",
    "Event Name",
    "Event Date",
    "Event Time",
    "Venue",
    "Guest Details",
    "College Contact Number"

];


/* =====================================================
   HANDLE APPROVE / RETURN
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $brochure_id = isset($_POST["brochure_id"])
        ? (int)$_POST["brochure_id"]
        : 0;


    /* =================================================
       APPROVE BROCHURE
    ================================================= */

    if (isset($_POST["approve"])) {

        $checklist_status =
            $_POST["checklist_status"] ?? [];

        $all_correct = true;


        foreach ($required_items as $item) {

            if (
                !isset($checklist_status[$item]) ||
                $checklist_status[$item] !== "correct"
            ) {

                $all_correct = false;
                break;
            }
        }


        if (!$all_correct) {

            $message =
                "All checklist items must be marked as Correct before approval.";

            $message_type = "error";

        } else {

            /*
             * Both pending and resubmitted brochures
             * can reach this approval process.
             */

            $stmt = $conn->prepare("

                UPDATE event_brochures

                SET status = 'approved',
                    correction_reason = NULL

                WHERE brochure_id = ?

            ");

            $stmt->bind_param(
                "i",
                $brochure_id
            );


            if ($stmt->execute()) {

                $message =
                    "Brochure approved successfully.";

                $message_type = "success";

            } else {

                $message =
                    "Failed to approve brochure.";

                $message_type = "error";
            }


            $stmt->close();
        }
    }


    /* =================================================
       RETURN FOR CORRECTION
    ================================================= */

    elseif (isset($_POST["return_correction"])) {

        $checklist_status =
            $_POST["checklist_status"] ?? [];

        $correction_details =
            $_POST["correction_details"] ?? [];

        $reasons = [];


        foreach ($required_items as $item) {

            /*
             * ONLY INCORRECT ITEMS
             * ARE STORED AS CORRECTION ITEMS
             */

            if (
                isset($checklist_status[$item]) &&
                $checklist_status[$item] === "incorrect"
            ) {

                $reason = "";

                if (isset($correction_details[$item])) {

                    $reason =
                        trim(
                            $correction_details[$item]
                        );
                }


                /*
                 * CORRECTION REASON IS OPTIONAL
                 */

                if ($reason !== "") {

                    $reasons[] =
                        $item . ": " . $reason;

                } else {

                    /*
                     * Even without a typed reason,
                     * the incorrect item itself is stored.
                     */

                    $reasons[] =
                        $item . ": Correction required.";
                }
            }
        }


        /*
         * AT LEAST ONE ITEM MUST BE INCORRECT
         */

        $has_incorrect = false;


        foreach ($required_items as $item) {

            if (
                isset($checklist_status[$item]) &&
                $checklist_status[$item] === "incorrect"
            ) {

                $has_incorrect = true;
                break;
            }
        }


        if (!$has_incorrect) {

            $message =
                "Please mark at least one incorrect item before returning the brochure.";

            $message_type = "error";

        } else {

            $correction_reason =
                implode("\n", $reasons);


            $stmt = $conn->prepare("

                UPDATE event_brochures

                SET status = 'correction_required',
                    correction_reason = ?

                WHERE brochure_id = ?

            ");

            $stmt->bind_param(
                "si",
                $correction_reason,
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
}


/* =====================================================
   FETCH BROCHURES
===================================================== */

$sql = "

    SELECT

        eb.brochure_id,
        eb.event_id,
        eb.brochure_file,
        eb.upload_date,
        eb.status,
        eb.correction_reason,

        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,

        u.name AS organizer_name,
        u.email AS organizer_email

    FROM event_brochures eb

    INNER JOIN events e
        ON eb.event_id = e.event_id

    LEFT JOIN users u
        ON e.organizer_id = u.id

    ORDER BY eb.upload_date DESC

";

$result = $conn->query($sql);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Brochure Review | Admin</title>


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


/* =====================================================
   HEADER
===================================================== */

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


/* =====================================================
   CONTAINER
===================================================== */

.container {
    max-width: 1100px;

    margin: 35px auto;

    padding: 0 20px;
}


/* =====================================================
   INTRO
===================================================== */

.intro {
    margin-bottom: 25px;
}

.intro h2 {
    color: #0b1f3a;

    margin-bottom: 7px;
}

.intro p {
    color: #697386;
}


/* =====================================================
   MESSAGE
===================================================== */

.message {
    padding: 15px;

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


/* =====================================================
   BROCHURE CARD
===================================================== */

.brochure-card {
    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.08);
}


/* =====================================================
   EVENT TITLE
===================================================== */

.event-title {
    color: #0b1f3a;

    font-size: 21px;

    font-weight: bold;

    margin-bottom: 15px;
}


/* =====================================================
   DETAILS
===================================================== */

.details {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

    margin-bottom: 20px;
}

.detail {
    background: #f4f7fb;

    padding: 12px;

    border-radius: 7px;
}

.detail strong {
    color: #0b1f3a;
}

.detail span {
    color: #596579;
}


/* =====================================================
   STATUS
===================================================== */

.status {
    display: inline-block;

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 20px;
}

.pending {
    background: #fff2cc;
    color: #956f00;
}

.approved {
    background: #dff5e5;
    color: #218838;
}

.correction {
    background: #ffe4cc;
    color: #b35a00;
}

.resubmitted {
    background: #e3edff;
    color: #1769e0;
}


/* =====================================================
   FILE BOX
===================================================== */

.file-box {
    background: #eef5ff;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.view-btn {
    display: inline-block;

    margin-top: 10px;

    background: #1769e0;

    color: white;

    text-decoration: none;

    padding: 10px 17px;

    border-radius: 7px;
}


/* =====================================================
   CHECKLIST
===================================================== */

.checklist {
    background: #f8fafc;

    padding: 20px;

    border-radius: 9px;

    margin-bottom: 20px;
}

.checklist h3 {
    color: #0b1f3a;

    margin-bottom: 8px;
}

.hint {
    margin-bottom: 20px;

    font-size: 13px;

    color: #697386;
}


/* =====================================================
   CHECK ITEM
===================================================== */

.check-item {
    background: white;

    border: 1px solid #e2e8f0;

    border-radius: 8px;

    padding: 15px;

    margin-bottom: 12px;
}

.check-item-title {
    font-weight: bold;

    color: #172033;

    margin-bottom: 10px;
}


/* =====================================================
   RADIO OPTIONS
===================================================== */

.options {
    display: flex;

    gap: 25px;

    flex-wrap: wrap;
}

.option {
    display: flex;

    align-items: center;

    gap: 7px;

    cursor: pointer;

    font-size: 14px;
}

.option input {
    width: 18px;

    height: 18px;

    cursor: pointer;
}


/* CORRECT */

.correct-option {
    color: #218838;
}


/* INCORRECT */

.incorrect-option {
    color: #b42323;
}


/* =====================================================
   CORRECTION BOX
===================================================== */

.correction-box {
    display: none;

    margin-top: 12px;
}

.correction-label {
    display: block;

    font-size: 13px;

    font-weight: bold;

    color: #b42323;

    margin-bottom: 6px;
}

.correction-box textarea {
    width: 100%;

    min-height: 75px;

    padding: 11px;

    border: 1px solid #f0a0a0;

    border-radius: 7px;

    resize: vertical;

    background: #fffafa;
}

.correction-box textarea:focus {
    outline: none;

    border-color: #d97706;
}


/* =====================================================
   ACTIONS
===================================================== */

.actions {
    display: flex;

    gap: 12px;

    flex-wrap: wrap;
}

button {
    border: none;

    padding: 12px 20px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 14px;
}


/* APPROVE */

.approve-btn {
    background: #218838;

    color: white;
}

.approve-btn:disabled {
    background: #9ca3af;

    cursor: not-allowed;

    opacity: 0.7;
}


/* RETURN */

.correction-btn {
    background: #d97706;

    color: white;
}


/* HOVER */

.approve-btn:hover:not(:disabled) {
    background: #176c2c;
}

.correction-btn:hover {
    background: #b65f04;
}


/* =====================================================
   EXISTING CORRECTION
===================================================== */

.existing-reason {
    background: #fff5eb;

    color: #8a4b08;

    padding: 14px;

    border-radius: 8px;

    margin-bottom: 18px;

    white-space: pre-line;
}


/* =====================================================
   RESUBMITTED NOTICE
===================================================== */

.resubmitted-notice {
    background: #eef5ff;

    color: #1769e0;

    padding: 14px;

    border-radius: 8px;

    margin-bottom: 18px;

    line-height: 1.5;
}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width: 650px) {

    .header {
        padding: 18px;
    }

    .header h1 {
        font-size: 19px;
    }

    .details {
        grid-template-columns: 1fr;
    }

    .options {
        flex-direction: column;

        gap: 10px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<div class="header">

    <h1>
        Admin | Brochure Review
    </h1>

    <a
        href="admin_dashboard.php"
        class="back-btn"
    >
        ← Dashboard
    </a>

</div>


<div class="container">


    <!-- =================================================
         INTRO
    ================================================== -->

    <div class="intro">

        <h2>
            Brochure Approval
        </h2>

        <p>
            Verify each brochure item before approving or
            returning it for correction.
        </p>

    </div>


    <!-- =================================================
         MESSAGE
    ================================================== -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo $message_type; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         NO BROCHURES
    ================================================== -->

    <?php if ($result->num_rows === 0): ?>

        <div class="brochure-card">

            <h3>
                No brochures uploaded yet.
            </h3>

            <p
                style="
                    margin-top:8px;
                    color:#697386;
                "
            >
                Uploaded brochures will appear here
                for Admin review.
            </p>

        </div>

    <?php endif; ?>


    <!-- =================================================
         BROCHURES
    ================================================== -->

    <?php while ($brochure = $result->fetch_assoc()): ?>


        <div class="brochure-card">


            <!-- EVENT NAME -->

            <div class="event-title">

                <?php

                echo htmlspecialchars(
                    $brochure["event_name"]
                );

                ?>

            </div>


            <!-- DETAILS -->

            <div class="details">


                <div class="detail">

                    <strong>
                        Event ID:
                    </strong>

                    <span>

                        <?php
                        echo $brochure["event_id"];
                        ?>

                    </span>

                </div>


                <div class="detail">

                    <strong>
                        Organizer:
                    </strong>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $brochure["organizer_name"]
                            ?? "Unknown"
                        );

                        ?>

                    </span>

                </div>


                <div class="detail">

                    <strong>
                        Date:
                    </strong>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $brochure["event_date"]
                        );

                        ?>

                    </span>

                </div>


                <div class="detail">

                    <strong>
                        Venue:
                    </strong>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $brochure["venue"]
                        );

                        ?>

                    </span>

                </div>


            </div>


            <!-- =================================================
                 STATUS
            ================================================== -->

            <?php if (
                $brochure["status"] === "pending"
            ): ?>

                <span class="status pending">

                    Pending Review

                </span>


            <?php elseif (
                $brochure["status"] === "resubmitted"
            ): ?>

                <span class="status resubmitted">

                    🔄 Resubmitted — Pending Review

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


            <!-- =================================================
                 RESUBMITTED NOTICE
            ================================================== -->

            <?php if (
                $brochure["status"] === "resubmitted"
            ): ?>

                <div class="resubmitted-notice">

                    <strong>
                        Brochure Resubmitted
                    </strong>

                    <br>

                    The organizer has uploaded a corrected
                    brochure. Please review the updated file
                    using the checklist below.

                </div>

            <?php endif; ?>


            <!-- =================================================
                 EXISTING CORRECTION
            ================================================== -->

            <?php if (
                !empty($brochure["correction_reason"])
            ): ?>

                <div class="existing-reason">

                    <strong>
                        Previous Correction:
                    </strong>

                    <br>

                    <?php

                    echo nl2br(
                        htmlspecialchars(
                            $brochure["correction_reason"]
                        )
                    );

                    ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 BROCHURE FILE
            ================================================== -->

            <div class="file-box">

                <strong>
                    Uploaded Brochure
                </strong>

                <br>

                <a
                    href="../uploads/<?php
                        echo htmlspecialchars(
                            $brochure["brochure_file"]
                        );
                    ?>"
                    target="_blank"
                    class="view-btn"
                >

                    👁 View Brochure

                </a>

            </div>


            <!-- =================================================
                 PENDING + RESUBMITTED REVIEW
            ================================================== -->

            <?php if (
                $brochure["status"] === "pending" ||
                $brochure["status"] === "resubmitted"
            ): ?>


                <form
                    method="POST"
                    id="reviewForm-<?php
                        echo $brochure["brochure_id"];
                    ?>"
                >


                    <input
                        type="hidden"
                        name="brochure_id"
                        value="<?php
                            echo $brochure["brochure_id"];
                        ?>"
                    >


                    <!-- =================================================
                         CHECKLIST
                    ================================================== -->

                    <div class="checklist">

                        <h3>
                            📋 Brochure Verification Checklist
                        </h3>

                        <p class="hint">

                            Select ✅ Correct if the item is
                            present and accurate. Select ❌
                            Incorrect if it needs correction.

                        </p>


                        <?php foreach (
                            $required_items as $index => $item
                        ): ?>


                            <div
                                class="check-item"
                                data-item-index="<?php
                                    echo $index;
                                ?>"
                            >


                                <div class="check-item-title">

                                    <?php

                                    echo ($index + 1) .
                                        ". " .
                                        htmlspecialchars($item);

                                    ?>

                                </div>


                                <div class="options">


                                    <!-- CORRECT -->

                                    <label
                                        class="option correct-option"
                                    >

                                        <input
                                            type="radio"

                                            name="checklist_status[<?php
                                                echo htmlspecialchars(
                                                    $item
                                                );
                                            ?>]"

                                            value="correct"

                                            onchange="updateChecklist(
                                                <?php
                                                echo $brochure["brochure_id"];
                                                ?>,
                                                <?php
                                                echo $index;
                                                ?>
                                            )"
                                        >

                                        ✅ Correct

                                    </label>


                                    <!-- INCORRECT -->

                                    <label
                                        class="option incorrect-option"
                                    >

                                        <input
                                            type="radio"

                                            name="checklist_status[<?php
                                                echo htmlspecialchars(
                                                    $item
                                                );
                                            ?>]"

                                            value="incorrect"

                                            onchange="updateChecklist(
                                                <?php
                                                echo $brochure["brochure_id"];
                                                ?>,
                                                <?php
                                                echo $index;
                                                ?>
                                            )"
                                        >

                                        ❌ Incorrect

                                    </label>


                                </div>


                                <!-- =================================================
                                     OPTIONAL CORRECTION TEXTBOX
                                ================================================== -->

                                <div
                                    class="correction-box"

                                    id="correctionBox-<?php
                                        echo $brochure["brochure_id"];
                                    ?>-<?php
                                        echo $index;
                                    ?>"
                                >

                                    <label
                                        class="correction-label"
                                    >

                                        Correction Details
                                        (Optional)

                                    </label>


                                    <textarea
                                        name="correction_details[<?php
                                            echo htmlspecialchars(
                                                $item
                                            );
                                        ?>]"

                                        placeholder="Example: College logo is misplaced. Please use the official college logo."
                                    ></textarea>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                    <!-- =================================================
                         ACTIONS
                    ================================================== -->

                    <div class="actions">


                        <!-- APPROVE -->

                        <button
                            type="submit"

                            name="approve"

                            class="approve-btn"

                            id="approveBtn-<?php
                                echo $brochure["brochure_id"];
                            ?>"

                            disabled
                        >

                            ✓ Approve Brochure

                        </button>


                        <!-- RETURN -->

                        <button
                            type="submit"

                            name="return_correction"

                            class="correction-btn"

                            onclick="return validateCorrection(
                                <?php
                                echo $brochure["brochure_id"];
                                ?>
                            )"
                        >

                            ↩ Return for Correction

                        </button>


                    </div>


                </form>


            <?php endif; ?>


        </div>


    <?php endwhile; ?>


</div>



<script>


/* =====================================================
   UPDATE CHECKLIST
===================================================== */

function updateChecklist(id, index) {

    const form =
        document.getElementById(
            "reviewForm-" + id
        );


    if (!form) {
        return;
    }


    const checkItem =
        form.querySelector(
            '.check-item[data-item-index="' +
            index +
            '"]'
        );


    if (!checkItem) {
        return;
    }


    const radios =
        checkItem.querySelectorAll(
            'input[type="radio"]'
        );


    let selectedValue = "";


    radios.forEach(function(radio) {

        if (radio.checked) {

            selectedValue =
                radio.value;
        }

    });


    /* =================================================
       FIND CORRECTION BOX
    ================================================= */

    const correctionBox =
        document.getElementById(
            "correctionBox-" +
            id +
            "-" +
            index
        );


    /* =================================================
       SHOW TEXTBOX ONLY FOR INCORRECT
    ================================================= */

    if (
        correctionBox &&
        selectedValue === "incorrect"
    ) {

        correctionBox.style.display =
            "block";

    } else if (correctionBox) {

        correctionBox.style.display =
            "none";


        const textarea =
            correctionBox.querySelector(
                "textarea"
            );


        if (textarea) {

            textarea.value = "";
        }
    }


    /* =================================================
       CHECK WHETHER EVERYTHING IS CORRECT
    ================================================= */

    const allItems =
        <?php
        echo json_encode($required_items);
        ?>;


    let allCorrect = true;


    allItems.forEach(function(item) {

        const itemRadios =
            form.querySelectorAll(
                'input[type="radio"]'
            );


        let correctSelected = false;


        itemRadios.forEach(function(radio) {

            if (
                radio.name ===
                'checklist_status[' + item + ']' &&
                radio.checked &&
                radio.value === "correct"
            ) {

                correctSelected = true;
            }

        });


        if (!correctSelected) {

            allCorrect = false;
        }

    });


    /* =================================================
       ENABLE APPROVE ONLY WHEN EVERYTHING IS CORRECT
    ================================================= */

    const approveBtn =
        document.getElementById(
            "approveBtn-" + id
        );


    if (approveBtn) {

        approveBtn.disabled =
            !allCorrect;
    }

}


/* =====================================================
   VALIDATE RETURN FOR CORRECTION
===================================================== */

function validateCorrection(id) {

    const form =
        document.getElementById(
            "reviewForm-" + id
        );


    if (!form) {
        return false;
    }


    const allItems =
        <?php
        echo json_encode($required_items);
        ?>;


    let incorrectCount = 0;


    allItems.forEach(function(item) {

        const radios =
            form.querySelectorAll(
                'input[type="radio"]'
            );


        let selectedValue = "";


        radios.forEach(function(radio) {

            if (
                radio.name ===
                'checklist_status[' + item + ']' &&
                radio.checked
            ) {

                selectedValue =
                    radio.value;
            }

        });


        if (
            selectedValue === "incorrect"
        ) {

            incorrectCount++;
        }

    });


    /* =================================================
       NO INCORRECT ITEM
    ================================================= */

    if (incorrectCount === 0) {

        alert(
            "Please mark at least one item as Incorrect before returning the brochure."
        );

        return false;
    }


    /* =================================================
       CONFIRM
    ================================================= */

    return confirm(
        "Return this brochure to the organizer for correction?"
    );

}

</script>


</body>

</html>