<?php
session_start();

include "../db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

/* =========================================================
   CHECK ORGANIZER ACCESS
========================================================= */

$approved_request_ids = [];

$stmt = $conn->prepare("
    SELECT DISTINCT orm.request_id
    FROM organizer_team_members orm
    INNER JOIN organizer_requests orq
        ON orm.request_id = orq.request_id
    WHERE orm.user_id = ?
      AND orq.status = 'approved'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $approved_request_ids[] = (int)$row["request_id"];
}

$stmt->close();

if (count($approved_request_ids) === 0) {
    die("You do not have organizer access.");
}

$message = "";
$message_type = "";

/* =========================================================
   ADD EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_expense"])) {

    $event_id = isset($_POST["event_id"]) ? (int)$_POST["event_id"] : 0;
    $expense_description = trim($_POST["expense_description"] ?? "");
    $expense_category = trim($_POST["expense_category"] ?? "");
    $amount = isset($_POST["amount"]) ? (float)$_POST["amount"] : 0;
    $expense_date = $_POST["expense_date"] ?? "";

    if ($event_id <= 0) {

        $message = "Please select an event.";
        $message_type = "error";

    } elseif ($expense_description === "") {

        $message = "Please enter an expense description.";
        $message_type = "error";

    } elseif ($amount <= 0) {

        $message = "Please enter a valid amount.";
        $message_type = "error";

    } elseif ($expense_date === "") {

        $message = "Please select the expense date.";
        $message_type = "error";

    } else {

        $placeholders = implode(
            ",",
            array_fill(0, count($approved_request_ids), "?")
        );

        $types = str_repeat("i", count($approved_request_ids));

        $sql = "
            SELECT event_id, event_name
            FROM events
            WHERE event_id = ?
              AND organizer_request_id IN ($placeholders)
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $params = array_merge(
            [$event_id],
            $approved_request_ids
        );

        $bind_types = "i" . $types;

        $stmt->bind_param($bind_types, ...$params);
        $stmt->execute();

        $event_result = $stmt->get_result();
        $event = $event_result->fetch_assoc();

        $stmt->close();

        if (!$event) {

            $message = "You are not allowed to add expenses for this event.";
            $message_type = "error";

        } else {

            $stmt = $conn->prepare("
                INSERT INTO expenses
                (
                    event_id,
                    expense_description,
                    expense_category,
                    amount,
                    expense_date
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issds",
                $event_id,
                $expense_description,
                $expense_category,
                $amount,
                $expense_date
            );

            if ($stmt->execute()) {

                $message = "Expense added successfully.";
                $message_type = "success";

            } else {

                $message = "Failed to add expense.";
                $message_type = "error";
            }

            $stmt->close();
        }
    }
}

/* =========================================================
   DELETE EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_expense"])) {

    $expense_id = isset($_POST["expense_id"])
        ? (int)$_POST["expense_id"]
        : 0;

    if ($expense_id > 0) {

        $placeholders = implode(
            ",",
            array_fill(0, count($approved_request_ids), "?")
        );

        $types = str_repeat("i", count($approved_request_ids));

        $sql = "
            SELECT ex.expense_id
            FROM expenses ex
            INNER JOIN events e
                ON ex.event_id = e.event_id
            WHERE ex.expense_id = ?
              AND e.organizer_request_id IN ($placeholders)
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $params = array_merge(
            [$expense_id],
            $approved_request_ids
        );

        $bind_types = "i" . $types;

        $stmt->bind_param($bind_types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $expense = $result->fetch_assoc();

        $stmt->close();

        if ($expense) {

            $stmt = $conn->prepare("
                DELETE FROM expenses
                WHERE expense_id = ?
            ");

            $stmt->bind_param("i", $expense_id);

            if ($stmt->execute()) {

                $message = "Expense deleted successfully.";
                $message_type = "success";

            } else {

                $message = "Failed to delete expense.";
                $message_type = "error";
            }

            $stmt->close();

        } else {

            $message = "Expense not found or access denied.";
            $message_type = "error";
        }
    }
}

/* =========================================================
   GET MY EVENTS
========================================================= */

$events = [];

$placeholders = implode(
    ",",
    array_fill(0, count($approved_request_ids), "?")
);

$types = str_repeat("i", count($approved_request_ids));

$sql = "
    SELECT
        event_id,
        event_name,
        event_date,
        venue,
        status
    FROM events
    WHERE organizer_request_id IN ($placeholders)
      AND status <> 'cancelled'
    ORDER BY event_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$approved_request_ids);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

$stmt->close();

/* =========================================================
   SELECT EVENT
========================================================= */

$selected_event_id = isset($_GET["event_id"])
    ? (int)$_GET["event_id"]
    : 0;

/* =========================================================
   GET EXPENSES
========================================================= */

$expenses = [];
$total_expense = 0;

if ($selected_event_id > 0) {

    $event_allowed = false;

    foreach ($events as $event) {

        if ((int)$event["event_id"] === $selected_event_id) {
            $event_allowed = true;
            break;
        }
    }

    if ($event_allowed) {

        $stmt = $conn->prepare("
            SELECT
                expense_id,
                expense_description,
                expense_category,
                amount,
                expense_date
            FROM expenses
            WHERE event_id = ?
            ORDER BY expense_date DESC, expense_id DESC
        ");

        $stmt->bind_param("i", $selected_event_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $expenses[] = $row;
            $total_expense += (float)$row["amount"];
        }

        $stmt->close();

    } else {

        $selected_event_id = 0;
    }
}

/* =========================================================
   USER NAME
========================================================= */

$user_name = $_SESSION["user_name"] ?? "Organizer";

$stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $user_name = $row["name"];
}

$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Expenses | Organizer</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    background: #f4f7fb;
    color: #14213d;
}

/* =========================================================
   SIDEBAR — SAME ORGANIZER DASHBOARD THEME
========================================================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: #192b5a;
    color: white;
    padding: 22px 10px;
    overflow-y: auto;
}

.logo {
    text-align: center;
    margin-bottom: 32px;
}

.logo h2 {
    font-size: 28px;
    font-weight: 700;
    color: white;
    margin-bottom: 8px;
}

.logo p {
    font-size: 15px;
    color: #dbe4ff;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

/* Sidebar links */

.sidebar a {
    display: block;
    text-decoration: none;
    color: #ffffff;
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 6px;
    font-size: 17px;
    transition: 0.2s ease;
}

.sidebar a:hover {
    background: rgba(255,255,255,0.10);
}

.sidebar a.active {
    background: #2864e8;
    color: white;
    font-weight: 600;
}

/* Logout */

.sidebar .logout {
    margin-top: 28px;
    background: #ef2929;
    text-align: center;
    font-weight: 600;
}

.sidebar .logout:hover {
    background: #d91f1f;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 280px;
    padding: 28px 38px;
}

/* =========================================================
   TOP BAR
========================================================= */

.topbar {
    background: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 28px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow: 0 4px 18px rgba(20,33,61,0.07);
}

.topbar h1 {
    font-size: 31px;
    color: #102a63;
    margin-bottom: 6px;
}

.topbar p {
    color: #58708f;
    font-size: 17px;
}

.user-name {
    color: #2864e8;
    font-weight: 700;
}

/* =========================================================
   MESSAGE
========================================================= */

.message {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-weight: 600;
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

/* =========================================================
   CARDS
========================================================= */

.card {
    background: white;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 25px;

    box-shadow: 0 4px 18px rgba(20,33,61,0.07);
}

.card h2 {
    color: #102a63;
    font-size: 25px;
    margin-bottom: 20px;
}

/* =========================================================
   INFO BOX
========================================================= */

.info-box {
    background: #eef5ff;
    border-left: 4px solid #2864e8;
    padding: 15px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #21447d;
    font-size: 15px;
}

/* =========================================================
   EVENT SELECT
========================================================= */

.event-select {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
}

.event-select select {
    flex: 1;
    min-width: 250px;
}

/* =========================================================
   FORM ELEMENTS
========================================================= */

select,
input,
textarea {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #d5dce8;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
    background: white;
    color: #172b4d;
}

select:focus,
input:focus,
textarea:focus {
    border-color: #2864e8;
    box-shadow: 0 0 0 3px rgba(40,100,232,0.10);
}

textarea {
    resize: vertical;
    min-height: 95px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #243b63;
    font-weight: 600;
}

/* =========================================================
   BUTTONS — SAME BLUE
========================================================= */

.btn {
    border: none;
    padding: 13px 22px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    display: inline-block;
}

.btn-blue {
    background: #2864e8;
    color: white;
}

.btn-blue:hover {
    background: #1f55ca;
}

.submit-area {
    margin-top: 22px;
}

/* =========================================================
   TOTAL BOX
========================================================= */

.total-box {
    background: white;
    border-radius: 16px;
    padding: 25px 28px;
    margin-bottom: 25px;

    border-left: 6px solid #2864e8;

    box-shadow: 0 4px 18px rgba(20,33,61,0.07);
}

.total-box p {
    color: #58708f;
    font-size: 15px;
    margin-bottom: 7px;
}

.total-box h2 {
    color: #102a63;
    font-size: 32px;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 15px 13px;
    border-bottom: 1px solid #e4e9f1;
    text-align: left;
}

th {
    background: #f4f7fb;
    color: #243b63;
    font-size: 14px;
}

td {
    color: #334765;
    font-size: 14px;
}

tr:hover td {
    background: #fafcff;
}

.amount {
    font-weight: 700;
    color: #102a63;
}

/* =========================================================
   CATEGORY
========================================================= */

.category {
    display: inline-block;
    padding: 6px 10px;
    background: #eef5ff;
    color: #2864e8;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* =========================================================
   DELETE
========================================================= */

.delete-form {
    display: inline;
}

.delete-btn {
    border: none;
    background: #fee2e2;
    color: #c62828;
    padding: 7px 11px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: 600;
}

.delete-btn:hover {
    background: #fecaca;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {
    text-align: center;
    padding: 40px 15px;
    color: #718096;
}

.empty h3 {
    color: #102a63;
    margin-bottom: 8px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .sidebar {
        width: 240px;
    }

    .main {
        margin-left: 240px;
        padding: 25px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 15px;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

}

</style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">

    <div class="logo">

        <h2>Event Hub</h2>

        <p>Organizer Panel</p>

    </div>

    <a href="organizer_dashboard.php">
        🏠 Dashboard
    </a>

    <a href="../student_dashboard.php">
        🎓 Student Area
    </a>

    <a href="event_request.php">
        Event Request
    </a>

    <a href="approval_status.php">
        Approval Status
    </a>

    <a href="planning.php">
        Event Planning
    </a>

    <a href="brochure_upload.php">
        📄 Brochure
    </a>

    <a href="checklist.php">
        ✅ Checklist
    </a>

    <a href="participants.php">
        Participants
    </a>

    <a href="scan_qr.php">
        Scan QR
    </a>

    <a href="expenses.php" class="active">
        💰 Expenses
    </a>

    <a href="reports.php">
        📊 Reports
    </a>

    <a href="../logout.php" class="logout">
        Logout
    </a>

</div>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main">

    <!-- TOP BAR -->

    <div class="topbar">

        <div>

            <h1>Event Expenses</h1>

            <p>Manage and track expenses for your events.</p>

        </div>

        <div>

            Welcome,
            <span class="user-name">
                <?php echo htmlspecialchars($user_name); ?>
            </span>

        </div>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo $message_type; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SELECT EVENT
    ====================================================== -->

    <div class="card">

        <h2>📌 Select Event</h2>

        <div class="info-box">

            Select one of your approved team events to add and view its expenses.

        </div>

        <form method="GET">

            <div class="event-select">

                <select name="event_id" required>

                    <option value="">
                        -- Select Event --
                    </option>

                    <?php foreach ($events as $event): ?>

                        <option
                            value="<?php echo (int)$event["event_id"]; ?>"
                            <?php
                            echo (
                                $selected_event_id ===
                                (int)$event["event_id"]
                            )
                            ? "selected"
                            : "";
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $event["event_name"]
                            );
                            ?>

                            -

                            <?php
                            echo date(
                                "d M Y",
                                strtotime($event["event_date"])
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <button
                    type="submit"
                    class="btn btn-blue"
                >
                    View Expenses
                </button>

            </div>

        </form>

    </div>


    <?php if ($selected_event_id > 0): ?>


        <!-- =================================================
             ADD EXPENSE
        ================================================== -->

        <div class="card">

            <h2>➕ Add New Expense</h2>

            <form method="POST">

                <input
                    type="hidden"
                    name="event_id"
                    value="<?php echo $selected_event_id; ?>"
                >

                <div class="form-grid">

                    <div class="form-group full">

                        <label>
                            Expense Description
                        </label>

                        <textarea
                            name="expense_description"
                            placeholder="Example: Printing of event brochures"
                            required
                        ></textarea>

                    </div>


                    <div class="form-group">

                        <label>
                            Category
                        </label>

                        <select name="expense_category">

                            <option value="">
                                -- Select Category --
                            </option>

                            <option value="Printing">
                                Printing
                            </option>

                            <option value="Food">
                                Food
                            </option>

                            <option value="Decoration">
                                Decoration
                            </option>

                            <option value="Equipment">
                                Equipment
                            </option>

                            <option value="Travel">
                                Travel
                            </option>

                            <option value="Guest">
                                Guest / Resource Person
                            </option>

                            <option value="Publicity">
                                Publicity
                            </option>

                            <option value="Certificate">
                                Certificate
                            </option>

                            <option value="Other">
                                Other
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Amount (₹)
                        </label>

                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            placeholder="Enter amount"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Expense Date
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            value="<?php echo date("Y-m-d"); ?>"
                            required
                        >

                    </div>

                </div>


                <div class="submit-area">

                    <button
                        type="submit"
                        name="add_expense"
                        class="btn btn-blue"
                    >
                        ➕ Add Expense
                    </button>

                </div>

            </form>

        </div>


        <!-- =================================================
             TOTAL
        ================================================== -->

        <div class="total-box">

            <p>
                Total Expense for Selected Event
            </p>

            <h2>
                ₹ <?php echo number_format($total_expense, 2); ?>
            </h2>

        </div>


        <!-- =================================================
             EXPENSE LIST
        ================================================== -->

        <div class="card">

            <h2>💰 Expense Details</h2>

            <?php if (count($expenses) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Description
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($expenses as $expense): ?>

                                <tr>

                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $expense["expense_date"]
                                            )
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $expense[
                                                "expense_description"
                                            ]
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        if (
                                            !empty(
                                                $expense[
                                                    "expense_category"
                                                ]
                                            )
                                        ):
                                        ?>

                                            <span class="category">

                                                <?php
                                                echo htmlspecialchars(
                                                    $expense[
                                                        "expense_category"
                                                    ]
                                                );
                                                ?>

                                            </span>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>

                                    <td class="amount">

                                        ₹
                                        <?php
                                        echo number_format(
                                            (float)$expense["amount"],
                                            2
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <form
                                            method="POST"
                                            class="delete-form"
                                            onsubmit="return confirm('Are you sure you want to delete this expense?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="expense_id"
                                                value="<?php echo (int)$expense["expense_id"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="event_id"
                                                value="<?php echo $selected_event_id; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_expense"
                                                class="delete-btn"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">

                    <p>
                        No expenses have been added for this event yet.
                    </p>

                </div>

            <?php endif; ?>

        </div>


    <?php else: ?>

        <?php if (count($events) === 0): ?>

            <div class="card">

                <div class="empty">

                    <h3>No Approved Team Events</h3>

                    <p>
                        Your approved organizing team does not have
                        any events available for expense management.
                    </p>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

</body>

</html>