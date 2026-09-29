<?php

include "auth_student.php";
include "db.php";


/* =========================================================
   GET EVENT ID
========================================================= */

$event_id = filter_input(
    INPUT_GET,
    "event_id",
    FILTER_VALIDATE_INT
);

if (!$event_id) {
    die("Event not selected.");
}


/* =========================================================
   FETCH PUBLIC / APPROVED EVENT
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.fee

     FROM events e

     INNER JOIN event_brochures eb
        ON e.event_id = eb.event_id

     WHERE e.event_id = ?

       AND e.request_status = 'approved'

       AND eb.hod_status = 'approved'

       AND eb.status = 'approved'

     ORDER BY eb.upload_date DESC

     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $event_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    die(
        "This event is not currently available for registration."
    );
}

$event = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   FETCH LOGGED-IN USER DETAILS
========================================================= */

$user_stmt = $conn->prepare(
    "SELECT
        phone,
        college

     FROM users

     WHERE id = ?"
);

$user_stmt->bind_param(
    "i",
    $_SESSION["user_id"]
);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


/* =========================================================
   DETERMINE REGISTRATION TYPE AUTOMATICALLY
========================================================= */

$event_fee = (float)$event["fee"];

if ($event_fee > 0) {

    $registration_type = "paid";

} else {

    $registration_type = "free";

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
        Event Registration - Event Hub
    </title>

    <link
        rel="stylesheet"
        href="css/registration.css"
    >

</head>


<body>


<div class="page">


    <main class="container">


        <h1>
            Event Registration
        </h1>


        <p class="subtitle">

            Register for the event by filling
            in the details below.

        </p>



        <div class="registration-card">


            <h2>
                Participant Details
            </h2>



            <form
                action="registration_process.php"
                method="POST"
            >


                <!-- =========================================
                     FULL NAME
                ========================================== -->

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    value="<?php
                        echo htmlspecialchars(
                            $_SESSION["user_name"]
                        );
                    ?>"
                    readonly
                >



                <!-- =========================================
                     EMAIL
                ========================================== -->

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    value="<?php
                        echo htmlspecialchars(
                            $_SESSION["user_email"]
                        );
                    ?>"
                    readonly
                >



                <!-- =========================================
                     PHONE
                ========================================== -->

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="tel"
                    id="phone"
                    value="<?php
                        echo htmlspecialchars(
                            $user["phone"] ?? ""
                        );
                    ?>"
                    readonly
                >



                <!-- =========================================
                     COLLEGE
                ========================================== -->

                <label for="college">
                    College / Institution
                </label>

                <input
                    type="text"
                    id="college"
                    value="<?php
                        echo htmlspecialchars(
                            $user["college"] ?? ""
                        );
                    ?>"
                    readonly
                >



                <!-- =========================================
                     SELECT EVENT
                ========================================== -->

                <label for="event">
                    Selected Event
                </label>

                <select
                    id="event"
                    name="event_id"
                    required
                >

                    <option
                        value="<?php
                            echo (int)$event["event_id"];
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $event["event_name"]
                        );
                        ?>

                    </option>

                </select>



                <!-- =========================================
                     EVENT DATE
                ========================================== -->

                <label>
                    Event Date
                </label>

                <input
                    type="text"
                    value="<?php
                        echo date(
                            "d F Y",
                            strtotime(
                                $event["event_date"]
                            )
                        );
                    ?>"
                    readonly
                >



                <!-- =========================================
                     EVENT VENUE
                ========================================== -->

                <label>
                    Venue
                </label>

                <input
                    type="text"
                    value="<?php
                        echo htmlspecialchars(
                            $event["venue"]
                        );
                    ?>"
                    readonly
                >



                <!-- =========================================
                     REGISTRATION TYPE
                ========================================== -->

                <label>
                    Registration Type
                </label>


                <div class="event-type">


                    <?php if (
                        $registration_type === "free"
                    ): ?>

                        <label>

                            <input
                                type="radio"
                                checked
                                disabled
                            >

                            Free Event

                        </label>


                    <?php else: ?>


                        <label>

                            <input
                                type="radio"
                                checked
                                disabled
                            >

                            Paid Event

                            -
                            ₹<?php
                            echo number_format(
                                $event_fee,
                                2
                            );
                            ?>

                        </label>


                    <?php endif; ?>


                </div>



                <!-- =========================================
                     HIDDEN REGISTRATION TYPE
                ========================================== -->

                <input
                    type="hidden"
                    name="registration_type"
                    value="<?php
                        echo htmlspecialchars(
                            $registration_type
                        );
                    ?>"
                >



                <!-- =========================================
                     CONTINUE
                ========================================== -->

                <button
                    type="submit"
                    class="submit-btn"
                >

                    Continue

                </button>


            </form>


        </div>


    </main>


</div>


</body>

</html>