<?php

include "auth_student.php";
include "db.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        r.registration_id,
        e.event_id,
        e.event_name,
        e.event_date,
        e.venue,
        e.event_type,
        a.verification_status,
        f.feedback_id
     FROM registrations r
     JOIN events e
     ON r.event_id = e.event_id
     JOIN attendance a
     ON r.registration_id = a.registration_id
     LEFT JOIN feedback f
     ON r.registration_id = f.registration_id
     WHERE r.user_id = ?
     AND a.verification_status = 'verified'
     ORDER BY e.event_date DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Feedback - Event Hub</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fb;
            color: #222;
        }

        header {
            background: #172554;
            color: white;
            padding: 18px 40px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        .page {
            min-height: calc(100vh - 70px);
            padding: 40px 20px;
        }

        .container {
            max-width: 850px;
            margin: auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            color: #263a8b;
            text-decoration: none;
            font-weight: 600;
        }

        h1 {
            color: #172554;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .feedback-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .event-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .event-title h2 {
            color: #1e3a8a;
            margin-bottom: 8px;
        }

        .event-title p {
            color: #666;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }

        .rating input {
            display: none;
        }

        .rating label {
            font-size: 32px;
            color: #ccc;
            cursor: pointer;
        }

        .rating input:checked ~ label {
            color: #f59e0b;
        }

        .rating label:hover,
        .rating label:hover ~ label {
            color: #f59e0b;
        }

        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .submit-btn {
            width: 100%;
            border: none;
            background: #263a8b;
            color: white;
            padding: 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }

        .submit-btn:hover {
            background: #1e3075;
        }

        .completed {
            background: #dcfce7;
            color: #166534;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .no-feedback {
            background: white;
            padding: 30px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .no-feedback h2 {
            color: #172554;
            margin-bottom: 10px;
        }

        .no-feedback p {
            color: #666;
        }

        @media (max-width: 600px) {

            header {
                padding: 16px 20px;
            }

            .page {
                padding: 30px 15px;
            }

            h1 {
                font-size: 27px;
            }

        }

    </style>

</head>

<body>

<header>

    <div class="logo">
        Event Hub
    </div>

</header>


<main class="page">

    <div class="container">

        <a href="student_dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

        <h1>Event Feedback</h1>

        <p class="subtitle">
            Share your experience about the events you attended.
        </p>


        <?php

        $has_event = false;

        while ($event = $result->fetch_assoc()):

            $has_event = true;

        ?>

            <div class="feedback-card">

                <div class="event-title">

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $event["event_name"]
                        );
                        ?>
                    </h2>

                    <p>
                        📅
                        <?php
                        echo date(
                            "d F Y",
                            strtotime($event["event_date"])
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

                    <p>
                        🎯
                        <?php
                        echo htmlspecialchars(
                            $event["event_type"]
                        );
                        ?>
                    </p>

                </div>


                <?php if (!empty($event["feedback_id"])): ?>

                    <div class="completed">

                        <h3>
                            ✅ Feedback Submitted
                        </h3>

                        <p style="margin-top:8px;">
                            You have already submitted feedback
                            for this event.
                        </p>

                    </div>


                <?php else: ?>


                    <form
                        action="feedback_process.php"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="registration_id"
                            value="<?php
                                echo $event["registration_id"];
                            ?>"
                        >


                        <!-- Rating -->

                        <div class="form-group">

                            <label>
                                ⭐ Rate the Event
                            </label>

                            <div class="rating">

                                <input
                                    type="radio"
                                    name="rating"
                                    value="5"
                                    id="star5"
                                    required
                                >

                                <label for="star5">★</label>


                                <input
                                    type="radio"
                                    name="rating"
                                    value="4"
                                    id="star4"
                                >

                                <label for="star4">★</label>


                                <input
                                    type="radio"
                                    name="rating"
                                    value="3"
                                    id="star3"
                                >

                                <label for="star3">★</label>


                                <input
                                    type="radio"
                                    name="rating"
                                    value="2"
                                    id="star2"
                                >

                                <label for="star2">★</label>


                                <input
                                    type="radio"
                                    name="rating"
                                    value="1"
                                    id="star1"
                                >

                                <label for="star1">★</label>

                            </div>

                        </div>


                        <!-- Experience -->

                        <div class="form-group">

                            <label for="experience">
                                Overall Experience
                            </label>

                            <select
                                name="experience"
                                id="experience"
                                required
                            >

                                <option value="">
                                    Select your experience
                                </option>

                                <option value="Excellent">
                                    Excellent
                                </option>

                                <option value="Very Good">
                                    Very Good
                                </option>

                                <option value="Good">
                                    Good
                                </option>

                                <option value="Average">
                                    Average
                                </option>

                                <option value="Needs Improvement">
                                    Needs Improvement
                                </option>

                            </select>

                        </div>


                        <!-- Comments -->

                        <div class="form-group">

                            <label for="comments">
                                💬 Your Experience
                            </label>

                            <textarea
                                name="comments"
                                id="comments"
                                placeholder="Tell us about your experience..."
                            ></textarea>

                        </div>


                        <!-- Suggestions -->

                        <div class="form-group">

                            <label for="suggestions">
                                💡 Suggestions
                            </label>

                            <textarea
                                name="suggestions"
                                id="suggestions"
                                placeholder="Any suggestions for future events?"
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="submit-btn"
                        >
                            Submit Feedback
                        </button>

                    </form>


                <?php endif; ?>


            </div>


        <?php endwhile; ?>


        <?php if (!$has_event): ?>

            <div class="no-feedback">

                <h2>
                    📝 No Feedback Available
                </h2>

                <p>
                    Feedback will be available after you attend
                    and verify your attendance for an event.
                </p>

            </div>

        <?php endif; ?>


    </div>

</main>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>