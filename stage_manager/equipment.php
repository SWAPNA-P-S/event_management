<?php

session_start();

require_once "../db.php";

/* =========================
   LOGIN CHECK
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit();
}

/* =========================
   ROLE CHECK
========================= */

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "stage_manager"
) {
    echo "Access denied.";
    exit();
}

/* =========================
   FETCH EQUIPMENT
   =========================
   Total = inventory quantity

   Assigned =
   Event-wise quantities stored
   in event_equipment_requirements

   Available =
   Total - Assigned
========================= */

$equipment_query = "
    SELECT
        se.equipment_id,
        se.equipment_name,
        SUM(se.quantity) AS total_quantity,

        COALESCE(
            (
                SELECT SUM(eer.quantity)
                FROM event_equipment_requirements eer
                WHERE eer.equipment_id = se.equipment_id
            ),
            0
        ) AS assigned_quantity

    FROM stage_equipment se

    GROUP BY
        se.equipment_id,
        se.equipment_name

    ORDER BY se.equipment_name ASC
";

$equipment_result = $conn->query($equipment_query);

$equipment_data = [];

$total_quantity = 0;
$total_assigned = 0;
$total_left = 0;

if ($equipment_result) {

    while ($row = $equipment_result->fetch_assoc()) {

        $total = (int) $row["total_quantity"];
        $assigned = (int) $row["assigned_quantity"];

        $left = $total - $assigned;

        if ($assigned < 0) {
            $assigned = 0;
        }

        if ($left < 0) {
            $left = 0;
        }

        $row["total_quantity"] = $total;
        $row["assigned_quantity"] = $assigned;
        $row["left_quantity"] = $left;
        $row["available_quantity"] = $left;

        $equipment_data[] = $row;

        $total_quantity += $total;
        $total_assigned += $assigned;
        $total_left += $left;
    }
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

    <title>Equipment | Stage Manager</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #172033;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            width: 240px;
            background: #0B1F3A;
            color: white;
            padding: 25px 15px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
        }

        .logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo h2 {
            font-size: 21px;
            margin-bottom: 5px;
        }

        .logo p {
            font-size: 12px;
            color: #cbd7e8;
        }

        .nav a {
            display: block;
            text-decoration: none;
            color: #dbe5f2;
            padding: 13px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            font-size: 14px;
        }

        .nav a:hover,
        .nav a.active {
            background: #1E4E8C;
            color: white;
        }

        .logout {
            margin-top: 30px;
            border-top: 1px solid #294361;
            padding-top: 20px;
        }

        .logout a {
            color: #ffb4b4;
        }

        /* =========================
           MAIN CONTENT
        ========================= */

        .main {
            margin-left: 240px;
            width: calc(100% - 240px);
            padding: 35px;
        }

        .top-section {
            margin-bottom: 30px;
        }

        .top-section h1 {
            color: #0B1F3A;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .top-section p {
            color: #667085;
            font-size: 14px;
        }

        /* =========================
           SUMMARY
        ========================= */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border: 1px solid #DCE4EF;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(11, 31, 58, 0.05);
        }

        .summary-card h3 {
            color: #667085;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .summary-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #1E4E8C;
        }

        /* =========================
           EQUIPMENT CARD
        ========================= */

        .equipment-card {
            background: white;
            border: 1px solid #DCE4EF;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(11, 31, 58, 0.05);
        }

        .equipment-card h2 {
            color: #0B1F3A;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #EAF2FB;
            color: #0B1F3A;
            padding: 15px;
            text-align: center;
            font-size: 13px;
        }

        th:first-child {
            text-align: left;
        }

        td {
            padding: 16px 15px;
            border-bottom: 1px solid #edf0f5;
            text-align: center;
            font-size: 14px;
        }

        td:first-child {
            text-align: left;
        }

        tr:hover td {
            background: #f8fafd;
        }

        /* =========================
           EQUIPMENT NAME
        ========================= */

        .equipment-name {
            font-weight: bold;
            color: #172033;
            font-size: 15px;
        }

        /* =========================
           NUMBERS
        ========================= */

        .total-number {
            font-weight: bold;
            color: #333;
        }

        .assigned-number {
            font-weight: bold;
            color: #d97706;
        }

        .left-number {
            font-weight: bold;
            color: #2563eb;
        }

        .available-number {
            display: inline-block;
            min-width: 55px;
            padding: 7px 12px;
            border-radius: 20px;
            background: #e4f8eb;
            color: #218838;
            font-weight: bold;
        }

        .available-number.zero {
            background: #fde7e7;
            color: #c62828;
        }

        /* =========================
           INFO BOX
        ========================= */

        .info-box {
            margin-top: 20px;
            padding: 14px 16px;
            background: #EAF2FB;
            border: 1px solid #DCE4EF;
            border-radius: 10px;
            color: #34506f;
            font-size: 13px;
            line-height: 1.5;
        }

        /* =========================
           EMPTY
        ========================= */

        .empty {
            text-align: center;
            padding: 45px 20px;
            color: #777;
        }

        .empty h3 {
            margin-bottom: 8px;
            color: #0B1F3A;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .summary {
                grid-template-columns: 1fr;
            }

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                width: calc(100% - 210px);
                padding: 25px;
            }
        }

        @media (max-width: 650px) {

            .layout {
                display: block;
            }

            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<div class="layout">

    <!-- =========================
         SIDEBAR
    ========================= -->

    <aside class="sidebar">

        <div class="logo">

            <h2>
                Stage Manager
            </h2>

            <p>
                Event Management System
            </p>

        </div>

        <nav class="nav">

            <a href="dashboard.php">
                📊 Dashboard
            </a>

            <a
                href="equipment.php"
                class="active"
            >
                🎤 Equipment
            </a>

            <a href="handover.php">
                🤝 Stage Handover
            </a>

            <a href="profile.php">
                👤 Profile
            </a>

            <div class="logout">

                <a href="../logout.php">
                    🚪 Logout
                </a>

            </div>

        </nav>

    </aside>


    <!-- =========================
         MAIN
    ========================= -->

    <main class="main">

        <div class="top-section">

            <h1>
                Equipment Management
            </h1>

            <p>
                View total, assigned, remaining and available equipment.
            </p>

        </div>


        <!-- =========================
             SUMMARY
        ========================= -->

        <section class="summary">

            <div class="summary-card">

                <h3>
                    Total Quantity
                </h3>

                <div class="number">
                    <?php echo $total_quantity; ?>
                </div>

            </div>


            <div class="summary-card">

                <h3>
                    Assigned
                </h3>

                <div class="number">
                    <?php echo $total_assigned; ?>
                </div>

            </div>


            <div class="summary-card">

                <h3>
                    Available
                </h3>

                <div class="number">
                    <?php echo $total_left; ?>
                </div>

            </div>

        </section>


        <!-- =========================
             EQUIPMENT TABLE
        ========================= -->

        <section class="equipment-card">

            <h2>
                Equipment Inventory
            </h2>

            <div class="table-wrapper">

                <?php if (count($equipment_data) > 0): ?>

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Equipment
                                </th>

                                <th>
                                    Total Quantity
                                </th>

                                <th>
                                    Assigned
                                </th>

                                <th>
                                    Remaining
                                </th>

                                <th>
                                    Available
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($equipment_data as $equipment): ?>

                                <tr>

                                    <td class="equipment-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment["equipment_name"]
                                        );
                                        ?>

                                    </td>

                                    <td class="total-number">

                                        <?php
                                        echo $equipment["total_quantity"];
                                        ?>

                                    </td>

                                    <td class="assigned-number">

                                        <?php
                                        echo $equipment["assigned_quantity"];
                                        ?>

                                    </td>

                                    <td class="left-number">

                                        <?php
                                        echo $equipment["left_quantity"];
                                        ?>

                                    </td>

                                    <td>

                                        <span
                                            class="available-number <?php
                                                echo
                                                    $equipment["available_quantity"] <= 0
                                                    ? "zero"
                                                    : "";
                                            ?>"
                                        >

                                            <?php
                                            echo $equipment["available_quantity"];
                                            ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                    <div class="info-box">

                        <strong>Equipment availability:</strong>
                        Available quantity is calculated from the total
                        inventory minus quantities already assigned to events.

                    </div>

                <?php else: ?>

                    <div class="empty">

                        <h3>
                            No Equipment Found
                        </h3>

                        <p>
                            No equipment has been added yet.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

</body>

</html>

<?php

$conn->close();

?>