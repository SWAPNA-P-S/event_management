<?php

include "auth_student.php";
include "db.php";


/*
|--------------------------------------------------------------------------
| PUBLIC EVENTS
|--------------------------------------------------------------------------
| An event is visible to students only when:
|
| 1. Event request is approved
| 2. Brochure is approved by HOD
| 3. Brochure is finally approved by Core Admin / PIMC
| 4. Event is officially published
|
| Core Admin final approval:
| event_brochures.status = 'approved'
|
| Official publication:
| events.publication_status = 'published'
|
| Only upcoming events are displayed.
|--------------------------------------------------------------------------
*/


$events_query = $conn->query(
    "SELECT DISTINCT
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

     WHERE e.event_date >= CURDATE()

     AND e.request_status = 'approved'

     AND e.publication_status = 'published'

     AND eb.hod_status = 'approved'

     AND eb.status = 'approved'

     ORDER BY e.event_date ASC, e.start_time ASC"
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

    <title>Events - Event Hub</title>

    <link
        rel="stylesheet"
        href="css/event.css"
    >

</head>


<body>

    <div class="dashboard">


        <!-- =====================================================
             SIDEBAR
        ====================================================== -->

        <aside class="sidebar">

            <div class="logo">
                🎓 Event Hub
            </div>


            <nav>

                <a href="student_dashboard.php">
                    🏠 Dashboard
                </a>

                <a
                    href="events.php"
                    class="active"
                >
                    📅 Events
                </a>

                <a href="my_registration.php">
                    📝 My Registrations
                </a>

                <a href="my_qr.php">
                    🎟 My QR
                </a>

                <a href="feedback.html">
                    💬 Feedback
                </a>

                <a href="certificate.html">
                    🏆 Certificates
                </a>

                <a href="profile.html">
                    👤 Profile
                </a>

            </nav>


            <a
                href="login.html"
                class="logout"
            >
                🚪 Logout
            </a>

        </aside>



        <!-- =====================================================
             MAIN CONTENT
        ====================================================== -->

        <main class="main-content">


            <!-- =================================================
                 TOP BAR
            ================================================== -->

            <header class="topbar">

                <div>

                    <h1>
                        Events
                    </h1>

                    <p>
                        Explore upcoming college events
                    </p>

                </div>


                <div class="user-info">

                    🔔 &nbsp;
                    👤
                    <strong>
                        Student
                    </strong>

                </div>

            </header>



            <!-- =================================================
                 SEARCH AND FILTER
            ================================================== -->

            <section class="event-controls">

                <input
                    type="text"
                    id="eventSearch"
                    placeholder="🔍 Search events..."
                >


                <select id="eventFilter">

                    <option value="all">
                        All Events
                    </option>

                    <option value="technical">
                        Technical
                    </option>

                    <option value="arts">
                        Arts
                    </option>

                    <option value="sports">
                        Sports
                    </option>

                    <option value="cultural">
                        Cultural
                    </option>

                </select>

            </section>



            <!-- =================================================
                 EVENT CARDS
            ================================================== -->

            <section class="event-grid">

                <?php if (
                    $events_query &&
                    $events_query->num_rows > 0
                ): ?>


                    <?php while (
                        $event = $events_query->fetch_assoc()
                    ): ?>


                        <div
                            class="event-card"
                            data-event-name="<?php
                                echo htmlspecialchars(
                                    strtolower(
                                        $event["event_name"]
                                    )
                                );
                            ?>"
                            data-event-type="<?php
                                echo htmlspecialchars(
                                    strtolower(
                                        $event["event_type"]
                                    )
                                );
                            ?>"
                        >


                            <!-- EVENT IMAGE -->

                            <div class="event-image">
                                🎓
                            </div>



                            <!-- EVENT CONTENT -->

                            <div class="event-content">


                                <span class="event-type">

                                    <?php
                                    echo htmlspecialchars(
                                        $event["event_type"]
                                    );
                                    ?>

                                </span>



                                <h2>

                                    <?php
                                    echo htmlspecialchars(
                                        $event["event_name"]
                                    );
                                    ?>

                                </h2>



                                <?php if (
                                    !empty(
                                        $event["description"]
                                    )
                                ): ?>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $event["description"]
                                        );
                                        ?>

                                    </p>

                                <?php endif; ?>



                                <p>

                                    📅

                                    <?php
                                    echo date(
                                        "d F Y",
                                        strtotime(
                                            $event["event_date"]
                                        )
                                    );
                                    ?>

                                </p>



                                <p>

                                    ⏰

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



                                <p>

                                    📍

                                    <?php
                                    echo htmlspecialchars(
                                        $event["venue"]
                                    );
                                    ?>

                                </p>



                                <!-- EVENT BOTTOM -->

                                <div class="event-bottom">


                                    <?php if (
                                        $event["fee"] == 0
                                    ): ?>

                                        <span class="free">

                                            FREE

                                        </span>

                                    <?php else: ?>

                                        <span class="paid">

                                            ₹<?php
                                            echo number_format(
                                                $event["fee"],
                                                2
                                            );
                                            ?>

                                        </span>

                                    <?php endif; ?>



                                    <a
                                        href="event-details.php?event_id=<?php
                                            echo (int)$event["event_id"];
                                        ?>"
                                        class="view-details-btn"
                                    >

                                        View Details

                                    </a>


                                </div>


                            </div>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <p>
                        No upcoming events available.
                    </p>


                <?php endif; ?>


            </section>


        </main>


    </div>



    <!-- =====================================================
         SEARCH + FILTER
    ====================================================== -->

    <script>

        const searchInput =
            document.getElementById("eventSearch");

        const filterSelect =
            document.getElementById("eventFilter");

        const eventCards =
            document.querySelectorAll(".event-card");


        function filterEvents() {

            const searchText =
                searchInput.value
                    .toLowerCase()
                    .trim();

            const selectedType =
                filterSelect.value
                    .toLowerCase();


            eventCards.forEach(function(card) {

                const eventName =
                    card.dataset.eventName;

                const eventType =
                    card.dataset.eventType;


                const matchesSearch =
                    eventName.includes(searchText);


                const matchesType =
                    selectedType === "all"
                    ||
                    eventType.includes(selectedType);


                if (
                    matchesSearch &&
                    matchesType
                ) {

                    card.style.display = "";

                } else {

                    card.style.display = "none";

                }

            });

        }


        searchInput.addEventListener(
            "input",
            filterEvents
        );


        filterSelect.addEventListener(
            "change",
            filterEvents
        );

    </script>


</body>

</html>