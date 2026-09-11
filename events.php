<?php

include "auth_student.php";
include "db.php";

$events_query = $conn->query(
    "SELECT event_id, event_name, description, event_date, start_time, end_time, venue, event_type, fee
     FROM events
     WHERE event_date >= CURDATE()
     AND status = 'upcoming'
     ORDER BY event_date ASC, start_time ASC"
);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Events - Event Hub</title>

    <link rel="stylesheet" href="css/event.css">
</head>

<body>

    <div class="dashboard">

        <!-- Sidebar -->
        <aside class="sidebar">

            <div class="logo">
                🎓 Event Hub
            </div>

            <nav>
                <a href="student_dashboard.php">🏠 Dashboard</a>
                <a href="events.php" class="active">📅 Events</a>
                <a href="my_registration.php">📝 My Registrations</a>
                <a href="my_qr.php">🎟 My QR</a>
                <a href="feedback.html">💬 Feedback</a>
                <a href="certificate.html">🏆 Certificates</a>
                <a href="profile.html">👤 Profile</a>
            </nav>

            <a href="login.html" class="logout">🚪 Logout</a>

        </aside>


        <!-- Main Content -->
        <main class="main-content">

            <!-- Top Bar -->
            <header class="topbar">

                <div>
                    <h1>Events</h1>
                    <p>Explore upcoming college events</p>
                </div>

                <div class="user-info">
                    🔔 &nbsp; 👤 <strong>Student</strong>
                </div>

            </header>


            <!-- Search and Filter -->
            <section class="event-controls">

                <input 
                    type="text" 
                    placeholder="🔍 Search events..."
                >

                <select>
                    <option>All Events</option>
                    <option>Technical</option>
                    <option>Arts</option>
                    <option>Sports</option>
                    <option>Cultural</option>
                </select>

            </section>


            <!-- Event Cards -->
            <section class="event-grid">


                <?php if ($events_query->num_rows > 0): ?>

    <?php while ($event = $events_query->fetch_assoc()): ?>

        <div class="event-card">

            <div class="event-image">
                🎓
            </div>

            <div class="event-content">

                <span class="event-type">
                    <?php echo htmlspecialchars($event["event_type"]); ?>
                </span>

                <h2>
                    <?php echo htmlspecialchars($event["event_name"]); ?>
                </h2>

                <p>
                    📅
                    <?php echo date("d F Y", strtotime($event["event_date"])); ?>
                </p>

                <p>
                    ⏰
                    <?php echo date("h:i A", strtotime($event["start_time"])); ?>
                    -
                    <?php echo date("h:i A", strtotime($event["end_time"])); ?>
                </p>

                <p>
                    📍 <?php echo htmlspecialchars($event["venue"]); ?>
                </p>

                <div class="event-bottom">

                    <?php if ($event["fee"] == 0): ?>

                        <span class="free">
                            FREE
                        </span>

                    <?php else: ?>

                        <span class="paid">
                            ₹<?php echo number_format($event["fee"], 2); ?>
                        </span>

                    <?php endif; ?>
                    <a href="event-details.php?event_id=<?php echo $event["event_id"]; ?>" class="view-details-btn">
                        View Details
                   </a>

                    

                </div>

            </div>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <p>No upcoming events available.</p>

<?php endif; ?>

            </section>

        </main>

    </div>

</body>

</html>