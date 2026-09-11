<?php

include "auth_student.php";
include "db.php";

if (!isset($_GET["event_id"])) {
    die("Event not selected.");
}

$event_id = $_GET["event_id"];

$stmt = $conn->prepare(
    "SELECT event_id, event_name, event_date, start_time, end_time, venue, fee
     FROM events
     WHERE event_id = ?"
);

$stmt->bind_param("i", $event_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Event not found.");
}

$event = $result->fetch_assoc();
$user_stmt = $conn->prepare(
    "SELECT phone, college
     FROM users
     WHERE id = ?"
);

$user_stmt->bind_param("i", $_SESSION["user_id"]);
$user_stmt->execute();

$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Event Registration - Event Hub</title>

    <link rel="stylesheet" href="css/registration.css">
</head>

<body>

    <div class="page">

        <main class="container">

            <h1>Event Registration</h1>

            <p class="subtitle">
                Register for the event by filling in the details below.
            </p>

            <div class="registration-card">

                <h2>Participant Details</h2>

                <form action="registration_process.php" method="POST">

                    <label for="name">Full Name</label>
                    <input type="text"
                        id="name"
                        value="<?php echo htmlspecialchars($_SESSION["user_name"]); ?>"
                        readonly>
                    
                    <label for="email">Email</label>
                    <input type="email"
                        id="email"
                        value="<?php echo htmlspecialchars($_SESSION["user_email"]); ?>"
                        readonly>
        
                    <label for="phone">Phone Number</label>
                    <input type="tel"
                            id="phone"
                            value="<?php echo htmlspecialchars($user["phone"]); ?>"
                            readonly>
                    
                    <label for="college">College / Institution</label>
                    <input type="text"
                            id="college"
                            value="<?php echo htmlspecialchars($user["college"]); ?>"
                            readonly>
                    

                    <label for="event">Select Event</label>
                    <select id="event" name="event_id" required>
                        <option value="<?php echo $event["event_id"]; ?>">
                            <?php echo htmlspecialchars($event["event_name"]); ?>
                        </option>
                    </select>

                    <label for="event-type">Registration Type</label>

                    <div class="event-type">
                        <label>
                            <input type="radio" name="event-type" value="free">
                            Free Event
                        </label>

                        <label>
                            <input type="radio" name="event-type" value="paid">
                            Paid Event
                        </label>
                    </div>
                    <button type="submit" class="submit-btn">
                        Continue
                    </button>

                </form>

            </div>

        </main>

    </div>

</body>
</html>