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

    exit("Invalid event ID.");

}


/* =========================================================
   FETCH PUBLIC / APPROVED EVENT
=========================================================

   An event can be viewed publicly only when:

   1. Event request is approved
   2. HOD has approved the brochure
   3. Core Admin / PIMC has finally approved the brochure

========================================================= */

$stmt = $conn->prepare(
    "SELECT
        e.event_id,
        e.event_name,
        e.description,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.event_type,
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

$event = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$event) {

    exit(
        "This event is not currently available for public viewing."
    );

}


/* =========================================================
   FETCH FINAL APPROVED BROCHURE
========================================================= */

$brochure_stmt = $conn->prepare(
    "SELECT
        brochure_file

     FROM event_brochures

     WHERE event_id = ?

       AND hod_status = 'approved'

       AND status = 'approved'

     ORDER BY upload_date DESC

     LIMIT 1"
);

$brochure_stmt->bind_param(
    "i",
    $event_id
);

$brochure_stmt->execute();

$brochure =
    $brochure_stmt
        ->get_result()
        ->fetch_assoc();

$brochure_stmt->close();


/* =========================================================
   FETCH EVENT UPDATES
========================================================= */

$updates_stmt = $conn->prepare(
    "SELECT
        title,
        message,
        update_type,
        created_at

     FROM updates

     WHERE event_id = ?

     ORDER BY created_at DESC"
);

$updates_stmt->bind_param(
    "i",
    $event_id
);

$updates_stmt->execute();

$updates = $updates_stmt->get_result();

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

        <?php
        echo htmlspecialchars(
            $event["event_name"]
        );
        ?>

        - Event Hub

    </title>


    <link
        rel="stylesheet"
        href="css/event-details.css?v=4"
    >


    <style>

        /* =========================
           BROCHURE SECTION
        ========================= */

        .brochure-section {

            margin-top: 25px;

            padding: 25px;

            background: #ffffff;

            border-radius: 15px;

            box-shadow:
                0 5px 20px
                rgba(0, 0, 0, 0.08);

        }


        .brochure-section h2 {

            margin-bottom: 8px;

        }


        .brochure-section p {

            margin-bottom: 18px;

            color: #666;

        }


        .brochure-btn {

            display: inline-block;

            padding: 12px 22px;

            background: #4f46e5;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: 600;

            transition: 0.3s;

        }


        .brochure-btn:hover {

            background: #3730a3;

            transform: translateY(-2px);

        }



        /* =========================
           LIVE UPDATES
        ========================= */

        .updates-section {

            margin-top: 25px;

            padding: 25px;

            background: #ffffff;

            border-radius: 15px;

            box-shadow:
                0 5px 20px
                rgba(0, 0, 0, 0.08);

        }


        .updates-heading {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 20px;

        }


        .updates-heading h2 {

            margin: 0;

        }


        .updates-heading span {

            background: #dc2626;

            color: white;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .updates-list {

            display: flex;

            flex-direction: column;

            gap: 15px;

        }


        .update-card {

            padding: 18px;

            border-left: 5px solid #4f46e5;

            background: #f8f9ff;

            border-radius: 10px;

        }


        .update-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 8px;

        }


        .update-top h3 {

            margin: 0;

            color: #222;

        }


        .update-top small {

            color: #777;

            white-space: nowrap;

        }


        .update-card p {

            margin: 8px 0 12px;

            color: #444;

            line-height: 1.6;

        }


        .update-type {

            display: inline-block;

            padding: 4px 10px;

            background: #e0e7ff;

            color: #3730a3;

            border-radius: 15px;

            font-size: 12px;

            font-weight: 600;

        }


        .no-updates {

            color: #777;

            text-align: center;

            padding: 15px;

        }


        /* =========================
           PUBLIC APPROVAL BADGE
        ========================= */

        .approved-badge {

            display: inline-block;

            margin-top: 12px;

            padding: 7px 13px;

            background: #dcfce7;

            color: #166534;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;

        }


        @media (max-width: 600px) {

            .update-top {

                flex-direction: column;

                align-items: flex-start;

            }


            .update-top small {

                white-space: normal;

            }

        }

    </style>

</head>


<body>


<div class="event-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="event-header">


        <a
            href="events.php"
            class="back-btn"
        >

            ← Back to Events

        </a>


        <div class="event-icon">

            🎓

        </div>


        <div class="event-header-content">


            <span class="event-type">

                <?php

                echo htmlspecialchars(
                    $event["event_type"]
                );

                ?>

            </span>


            <h1>

                <?php

                echo htmlspecialchars(
                    $event["event_name"]
                );

                ?>

            </h1>


            <p>

                <?php

                echo htmlspecialchars(
                    $event["description"]
                );

                ?>

            </p>


            <span class="approved-badge">

                ✓ Officially Approved Event

            </span>


        </div>


    </header>



    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="event-details-container">


        <!-- =================================================
             EVENT BASIC INFORMATION
        ================================================== -->

        <section class="info-grid">


            <div class="info-card">

                <span>📅</span>

                <h3>
                    Date
                </h3>

                <p>

                    <?php

                    echo date(
                        "d F Y",
                        strtotime(
                            $event["event_date"]
                        )
                    );

                    ?>

                </p>

            </div>



            <div class="info-card">

                <span>⏰</span>

                <h3>
                    Time
                </h3>

                <p>

                    <?php

                    echo date(
                        "h:i A",
                        strtotime(
                            $event["start_time"]
                        )
                    );

                    ?>

                    -

                    <?php

                    echo date(
                        "h:i A",
                        strtotime(
                            $event["end_time"]
                        )
                    );

                    ?>

                </p>

            </div>



            <div class="info-card">

                <span>📍</span>

                <h3>
                    Venue
                </h3>

                <p>

                    <?php

                    echo htmlspecialchars(
                        $event["venue"]
                    );

                    ?>

                </p>

            </div>



            <div class="info-card">

                <span>💰</span>

                <h3>
                    Registration Fee
                </h3>

                <p>

                    <?php

                    if (
                        (float)$event["fee"] == 0
                    ) {

                        echo "FREE";

                    } else {

                        echo "₹" .
                            number_format(
                                (float)$event["fee"],
                                2
                            );

                    }

                    ?>

                </p>

            </div>


        </section>



        <!-- =================================================
             ABOUT EVENT
        ================================================== -->

        <section class="about-section">

            <h2>
                About Event
            </h2>

            <p>

                <?php

                echo nl2br(
                    htmlspecialchars(
                        $event["description"]
                    )
                );

                ?>

            </p>

        </section>



        <!-- =================================================
             OBJECTIVES + ORGANIZING COMMITTEE
        ================================================== -->

        <section class="extra-section">


            <div>

                <h2>
                    🎯 Objectives
                </h2>

                <ul>

                    <li>
                        Enhance technical knowledge
                    </li>

                    <li>
                        Encourage student participation
                    </li>

                    <li>
                        Provide practical learning opportunities
                    </li>

                </ul>

            </div>



            <div>

                <h2>
                    👥 Organizing Committee
                </h2>

                <p>

                    College Event Organizing Committee

                </p>

            </div>


        </section>



        <!-- =================================================
             APPROVED BROCHURE
        ================================================== -->

        <?php if ($brochure): ?>


            <section class="brochure-section">


                <h2>
                    📄 Event Brochure
                </h2>


                <p>

                    View the official approved brochure
                    for this event.

                </p>


                <a
                    href="uploads/<?php
                        echo rawurlencode(
                            $brochure["brochure_file"]
                        );
                    ?>"
                    target="_blank"
                    class="brochure-btn"
                >

                    📖 View Brochure

                </a>


            </section>


        <?php endif; ?>



        <!-- =================================================
             LIVE EVENT UPDATES
        ================================================== -->

        <section class="updates-section">


            <div class="updates-heading">


                <h2>
                    🔴 Live Event Updates
                </h2>


                <span>
                    LIVE
                </span>


            </div>



            <?php if (
                $updates->num_rows > 0
            ): ?>


                <div class="updates-list">


                    <?php while (
                        $update =
                        $updates->fetch_assoc()
                    ): ?>


                        <div class="update-card">


                            <div class="update-top">


                                <h3>

                                    <?php

                                    echo htmlspecialchars(
                                        $update["title"]
                                    );

                                    ?>

                                </h3>


                                <small>

                                    <?php

                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $update["created_at"]
                                        )
                                    );

                                    ?>

                                </small>


                            </div>



                            <p>

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $update["message"]
                                    )
                                );

                                ?>

                            </p>



                            <?php if (
                                !empty(
                                    $update["update_type"]
                                )
                            ): ?>


                                <span class="update-type">

                                    <?php

                                    echo htmlspecialchars(
                                        $update["update_type"]
                                    );

                                    ?>

                                </span>


                            <?php endif; ?>


                        </div>


                    <?php endwhile; ?>


                </div>


            <?php else: ?>


                <p class="no-updates">

                    No live updates available
                    for this event.

                </p>


            <?php endif; ?>


        </section>



        <!-- =================================================
             REGISTER
        ================================================== -->

        <section class="register-section">


            <h2>
                Ready to participate?
            </h2>


            <p>

                Register now and be part of this event.

            </p>


            <a
                href="registration.php?event_id=<?php
                    echo (int)$event["event_id"];
                ?>"
                class="register-btn"
            >

                Register Now

            </a>


        </section>


    </main>


</div>


</body>

</html>