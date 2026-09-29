<?php

session_start();

include "../db.php";


/* =========================================================
   ADMIN / HOD LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.html");
    exit();
}


if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {

    echo "<script>
            alert('Access denied.');
            window.location.href='../student_dashboard.php';
          </script>";

    exit();
}


$admin_id = (int) $_SESSION["user_id"];


/* =========================================================
   GET ADMIN / HOD DEPARTMENT
========================================================= */

$admin_stmt = $conn->prepare(
    "SELECT department
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$admin_stmt->bind_param(
    "i",
    $admin_id
);

$admin_stmt->execute();

$admin_result = $admin_stmt->get_result();

if ($admin_result->num_rows !== 1) {

    $admin_stmt->close();
    $conn->close();

    die("Admin details not found.");
}

$admin = $admin_result->fetch_assoc();

$admin_department = trim(
    $admin["department"] ?? ""
);

$admin_stmt->close();


if ($admin_department === "") {

    $conn->close();

    die("Admin department is not configured.");
}


/* =========================================================
   PAYMENT ACTION
========================================================= */

$message = "";
$message_type = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payment_id = filter_input(
        INPUT_POST,
        "payment_id",
        FILTER_VALIDATE_INT
    );

    $action = $_POST["action"] ?? "";


    if (!$payment_id) {

        $message = "Invalid payment selected.";
        $message_type = "error";

    } elseif (
        $action !== "approve" &&
        $action !== "reject"
    ) {

        $message = "Invalid action.";
        $message_type = "error";

    } else {


        /* =================================================
           GET PAYMENT + REGISTRATION + DEPARTMENT
        ================================================= */

        $check_stmt = $conn->prepare(
            "SELECT
                p.payment_id,
                p.registration_id,
                p.status AS payment_status,
                r.status AS registration_status,
                e.event_name,
                e.organizer_request_id,
                u_admin.department AS event_department
             FROM payments p

             INNER JOIN registrations r
                ON p.registration_id = r.registration_id

             INNER JOIN events e
                ON r.event_id = e.event_id

             INNER JOIN organizer_requests oreq
                ON e.organizer_request_id = oreq.request_id

             INNER JOIN users u_admin
                ON oreq.requested_by = u_admin.id

             WHERE p.payment_id = ?
             LIMIT 1"
        );

        $check_stmt->bind_param(
            "i",
            $payment_id
        );

        $check_stmt->execute();

        $check_result =
            $check_stmt->get_result();


        if ($check_result->num_rows !== 1) {

            $message =
                "Payment record not found.";

            $message_type = "error";

        } else {

            $payment =
                $check_result->fetch_assoc();


            /* =========================================
               DEPARTMENT CHECK
            ========================================= */

            if (
                trim($payment["event_department"] ?? "") !==
                $admin_department
            ) {

                $message =
                    "You are not authorized to verify payments for this department.";

                $message_type = "error";

            } elseif (
                $payment["payment_status"] !== "pending"
            ) {

                $message =
                    "This payment has already been processed.";

                $message_type = "error";

            } else {


                /* =========================================
                   START TRANSACTION
                ========================================= */

                $conn->begin_transaction();


                try {


                    if ($action === "approve") {

                        /* =================================
                           APPROVE PAYMENT
                        ================================= */

                        $payment_status =
                            "approved";

                        $registration_status =
                            "approved";


                        $payment_stmt =
                            $conn->prepare(
                                "UPDATE payments
                                 SET status = ?
                                 WHERE payment_id = ?
                                   AND status = 'pending'"
                            );

                        $payment_stmt->bind_param(
                            "si",
                            $payment_status,
                            $payment_id
                        );


                        if (
                            !$payment_stmt->execute() ||
                            $payment_stmt->affected_rows !== 1
                        ) {

                            throw new Exception(
                                "Payment could not be approved."
                            );
                        }

                        $payment_stmt->close();


                        /* =================================
                           APPROVE REGISTRATION
                        ================================= */

                        $registration_stmt =
                            $conn->prepare(
                                "UPDATE registrations
                                 SET status = ?
                                 WHERE registration_id = ?"
                            );

                        $registration_stmt->bind_param(
                            "si",
                            $registration_status,
                            $payment["registration_id"]
                        );


                        if (
                            !$registration_stmt->execute()
                        ) {

                            throw new Exception(
                                "Registration could not be approved."
                            );
                        }

                        $registration_stmt->close();


                        $conn->commit();


                        $message =
                            "Payment approved successfully. Registration has also been approved.";

                        $message_type = "success";


                    } else {


                        /* =================================
                           REJECT PAYMENT
                        ================================= */

                        $payment_status =
                            "rejected";

                        $registration_status =
                            "rejected";


                        $payment_stmt =
                            $conn->prepare(
                                "UPDATE payments
                                 SET status = ?
                                 WHERE payment_id = ?
                                   AND status = 'pending'"
                            );

                        $payment_stmt->bind_param(
                            "si",
                            $payment_status,
                            $payment_id
                        );


                        if (
                            !$payment_stmt->execute() ||
                            $payment_stmt->affected_rows !== 1
                        ) {

                            throw new Exception(
                                "Payment could not be rejected."
                            );
                        }

                        $payment_stmt->close();


                        /* =================================
                           REJECT REGISTRATION
                        ================================= */

                        $registration_stmt =
                            $conn->prepare(
                                "UPDATE registrations
                                 SET status = ?
                                 WHERE registration_id = ?"
                            );

                        $registration_stmt->bind_param(
                            "si",
                            $registration_status,
                            $payment["registration_id"]
                        );


                        if (
                            !$registration_stmt->execute()
                        ) {

                            throw new Exception(
                                "Registration could not be rejected."
                            );
                        }

                        $registration_stmt->close();


                        $conn->commit();


                        $message =
                            "Payment rejected. Registration has also been rejected.";

                        $message_type = "success";
                    }


                } catch (Exception $e) {

                    $conn->rollback();

                    $message =
                        "Unable to process payment. Please try again.";

                    $message_type = "error";
                }
            }
        }


        $check_stmt->close();
    }
}


/* =========================================================
   FETCH PENDING PAYMENTS
   ONLY FOR THIS ADMIN'S DEPARTMENT
========================================================= */

$payments = [];


$payment_list_stmt = $conn->prepare(
    "SELECT
        p.payment_id,
        p.registration_id,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.payment_screenshot,
        p.payment_date,
        p.status,

        r.user_id,
        r.event_id,
        r.registration_type,
        r.status AS registration_status,

        u.name AS student_name,
        u.email AS student_email,
        u.phone AS student_phone,
        u.college AS student_college,

        e.event_name,
        e.event_date,
        e.venue,

        event_admin.department AS event_department

     FROM payments p

     INNER JOIN registrations r
        ON p.registration_id = r.registration_id

     INNER JOIN users u
        ON r.user_id = u.id

     INNER JOIN events e
        ON r.event_id = e.event_id

     INNER JOIN organizer_requests oreq
        ON e.organizer_request_id = oreq.request_id

     INNER JOIN users event_admin
        ON oreq.requested_by = event_admin.id

     WHERE p.status = 'pending'
       AND event_admin.department = ?

     ORDER BY p.payment_date ASC"
);


$payment_list_stmt->bind_param(
    "s",
    $admin_department
);


$payment_list_stmt->execute();

$payment_result =
    $payment_list_stmt->get_result();


while (
    $row = $payment_result->fetch_assoc()
) {

    $payments[] = $row;
}


$payment_list_stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Payment Verification | Admin</title>


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

    background: #f4f7fb;

    color: #1f2937;
}


.header {

    background: #1e3a8a;

    color: white;

    padding: 22px 30px;
}


.header h1 {

    margin: 0 0 5px;

    font-size: 25px;
}


.header p {

    margin: 0;

    opacity: .9;
}


.container {

    width: 94%;

    max-width: 1250px;

    margin: 30px auto;
}


.back {

    display: inline-block;

    margin-bottom: 20px;

    color: #1e3a8a;

    text-decoration: none;

    font-weight: bold;
}


.department-info {

    display: inline-block;

    margin-left: 15px;

    padding: 7px 12px;

    background: #e0e7ff;

    color: #3730a3;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}


.message {

    padding: 14px 18px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;
}


.message.success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #86efac;
}


.message.error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fca5a5;
}


.card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.08);
}


.card h2 {

    margin-top: 0;

    color: #1e3a8a;
}


.payment-card {

    border: 1px solid #e2e8f0;

    border-radius: 12px;

    padding: 22px;

    margin-bottom: 22px;

    background: #ffffff;
}


.payment-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 18px;

    flex-wrap: wrap;
}


.payment-header h3 {

    margin: 0;

    color: #1e3a8a;
}


.pending {

    background: #fef3c7;

    color: #92400e;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}


.details {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 14px;

    margin-bottom: 20px;
}


.detail {

    background: #f8fafc;

    border-radius: 8px;

    padding: 12px;
}


.detail strong {

    display: block;

    color: #475569;

    font-size: 13px;

    margin-bottom: 5px;
}


.detail span {

    color: #111827;

    font-weight: 500;
}


.screenshot {

    margin-top: 20px;

    padding: 15px;

    background: #f8fafc;

    border-radius: 10px;

}


.screenshot h4 {

    margin-top: 0;

    color: #1e3a8a;
}


.screenshot img {

    display: block;

    max-width: 300px;

    max-height: 350px;

    object-fit: contain;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    background: white;

    padding: 5px;
}


.no-screenshot {

    color: #991b1b;

    font-weight: bold;
}


.actions {

    display: flex;

    gap: 12px;

    margin-top: 20px;

    flex-wrap: wrap;
}


.btn {

    border: none;

    padding: 12px 20px;

    border-radius: 7px;

    color: white;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}


.btn-approve {

    background: #16a34a;
}


.btn-approve:hover {

    background: #15803d;
}


.btn-reject {

    background: #dc2626;
}


.btn-reject:hover {

    background: #b91c1c;
}


.empty {

    text-align: center;

    padding: 45px 20px;

    color: #64748b;
}


.amount {

    color: #166534 !important;

    font-size: 18px;

    font-weight: bold;
}


@media (max-width: 700px) {

    .details {

        grid-template-columns: 1fr;
    }

    .container {

        width: 95%;
    }

    .department-info {

        display: block;

        margin-left: 0;

        margin-top: 10px;

        width: fit-content;
    }

}

</style>

</head>


<body>


<div class="header">

    <h1>💳 Payment Verification</h1>

    <p>Department Admin / HOD</p>

</div>


<div class="container">


<a
    href="admin_dashboard.php"
    class="back"
>
    ← Back to Admin Dashboard
</a>

<span class="department-info">
    Department: <?php echo htmlspecialchars($admin_department); ?>
</span>


<?php if (!empty($message)): ?>

<div
    class="message
    <?php echo htmlspecialchars($message_type); ?>"
>

    <?php
    echo htmlspecialchars($message);
    ?>

</div>

<?php endif; ?>


<div class="card">

<h2>⏳ Pending Payment Verification</h2>


<?php if (count($payments) === 0): ?>


<div class="empty">

    <h3>✅ No Pending Payments</h3>

    <p>
        There are currently no payment submissions
        waiting for verification for your department.
    </p>

</div>


<?php else: ?>


<?php foreach ($payments as $payment): ?>


<div class="payment-card">


<div class="payment-header">

    <h3>

        🎫

        <?php
        echo htmlspecialchars(
            $payment["event_name"]
        );
        ?>

    </h3>


    <span class="pending">

        Payment Pending

    </span>

</div>


<div class="details">


<div class="detail">

<strong>Student</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["student_name"]
);
?>

</span>

</div>


<div class="detail">

<strong>Email</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["student_email"]
);
?>

</span>

</div>


<div class="detail">

<strong>Phone</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["student_phone"] ?? ""
);
?>

</span>

</div>


<div class="detail">

<strong>College</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["student_college"] ?? ""
);
?>

</span>

</div>


<div class="detail">

<strong>Event Date</strong>

<span>

<?php
echo date(
    "d F Y",
    strtotime(
        $payment["event_date"]
    )
);
?>

</span>

</div>


<div class="detail">

<strong>Venue</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["venue"]
);
?>

</span>

</div>


<div class="detail">

<strong>Amount</strong>

<span class="amount">

₹<?php
echo number_format(
    (float)$payment["amount"],
    2
);
?>

</span>

</div>


<div class="detail">

<strong>Payment Method</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["payment_method"]
);
?>

</span>

</div>


<div class="detail">

<strong>Transaction ID</strong>

<span>

<?php
echo htmlspecialchars(
    $payment["transaction_id"] ?? "Not provided"
);
?>

</span>

</div>


<div class="detail">

<strong>Payment Date</strong>

<span>

<?php
echo date(
    "d M Y, h:i A",
    strtotime(
        $payment["payment_date"]
    )
);
?>

</span>

</div>


</div>


<div class="screenshot">

<h4>📷 Payment Screenshot</h4>


<?php

$screenshot =
    trim($payment["payment_screenshot"] ?? "");

?>


<?php if (!empty($screenshot)): ?>


<?php

/*
 * payment_process.php stores the path as:
 * uploads/payments/filename.ext
 *
 * Therefore we only add ../ here because this page
 * is inside the admin folder.
 */

$screenshot_url =
    "../" . ltrim($screenshot, "/");

?>


<img
    src="<?php echo htmlspecialchars($screenshot_url); ?>"
    alt="Payment Screenshot"
>


<br>


<a
    href="<?php echo htmlspecialchars($screenshot_url); ?>"
    target="_blank"
>

    🔍 Open Full Screenshot

</a>


<?php else: ?>


<div class="no-screenshot">

    No payment screenshot available.

</div>


<?php endif; ?>


</div>


<div class="actions">


<form
    method="POST"
    action="payment_verification.php"
    onsubmit="
        return confirm(
            'Are you sure you want to APPROVE this payment?'
        );
    "
>

<input
    type="hidden"
    name="payment_id"
    value="<?php
        echo (int)$payment["payment_id"];
    ?>"
>


<input
    type="hidden"
    name="action"
    value="approve"
>


<button
    type="submit"
    class="btn btn-approve"
>

    ✅ Approve Payment

</button>

</form>


<form
    method="POST"
    action="payment_verification.php"
    onsubmit="
        return confirm(
            'Are you sure you want to REJECT this payment?'
        );
    "
>

<input
    type="hidden"
    name="payment_id"
    value="<?php
        echo (int)$payment["payment_id"];
    ?>"
>


<input
    type="hidden"
    name="action"
    value="reject"
>


<button
    type="submit"
    class="btn btn-reject"
>

    ❌ Reject Payment

</button>

</form>


</div>


</div>


<?php endforeach; ?>


<?php endif; ?>

</div>


</div>


</body>

</html>


<?php

$conn->close();

?>