<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "core_admin") {
    header("Location: ../login.html");
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$equipment = [];

$sql = "
    SELECT
        equipment_name,
        SUM(quantity) AS total_quantity,
        SUM(assigned_quantity) AS assigned_quantity
    FROM stage_equipment
    GROUP BY equipment_name
    ORDER BY equipment_name ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {

        $total = (int)$row["total_quantity"];
        $assigned = (int)$row["assigned_quantity"];

        $remaining = $total - $assigned;

        if ($remaining < 0) {
            $remaining = 0;
        }

        $row["total_quantity"] = $total;
        $row["assigned_quantity"] = $assigned;
        $row["remaining_quantity"] = $remaining;

        $equipment[] = $row;
    }
}


/* =========================
   SUMMARY TOTALS
========================= */

$total_stock = 0;
$total_assigned = 0;
$total_remaining = 0;

foreach ($equipment as $item) {

    $total_stock += $item["total_quantity"];
    $total_assigned += $item["assigned_quantity"];
    $total_remaining += $item["remaining_quantity"];
}

$total_types = count($equipment);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Stock Availability | Core Admin</title>

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

    background: #F4F7FB;

    color: #172033;
}


/* =========================
   HEADER
========================= */

.header {

    background: #0B1F3A;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.header-left h1 {

    margin: 0;

    font-size: 22px;
}

.header-left p {

    margin: 5px 0 0;

    color: #DCE4EF;

    font-size: 13px;
}

.back-btn {

    text-decoration: none;

    color: white;

    border:
        1px solid
        rgba(255,255,255,0.3);

    padding: 9px 15px;

    border-radius: 8px;

    font-size: 13px;
}


/* =========================
   CONTAINER
========================= */

.container {

    width: 94%;

    max-width: 1250px;

    margin: 30px auto;
}


/* =========================
   SUMMARY CARDS
========================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(200px, 1fr)
        );

    gap: 16px;

    margin-bottom: 25px;
}

.summary-card {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 12px;

    padding: 20px;
}

.summary-label {

    color: #667085;

    font-size: 13px;

    margin-bottom: 8px;
}

.summary-value {

    color: #0B1F3A;

    font-size: 28px;

    font-weight: bold;
}

.summary-note {

    margin-top: 5px;

    color: #98A2B3;

    font-size: 12px;
}


/* =========================
   MAIN CARD
========================= */

.main-card {

    background: white;

    border:
        1px solid
        #DCE4EF;

    border-radius: 13px;

    overflow: hidden;
}

.card-header {

    padding: 20px;

    border-bottom:
        1px solid
        #DCE4EF;

    background: #F8FAFD;
}

.card-header h2 {

    margin: 0 0 6px;

    color: #0B1F3A;

    font-size: 19px;
}

.card-header p {

    margin: 0;

    color: #667085;

    font-size: 13px;
}


/* =========================
   TABLE
========================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;
}

thead {

    background: #0B1F3A;

    color: white;
}

th {

    padding: 14px 16px;

    text-align: left;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}

td {

    padding: 15px 16px;

    border-bottom:
        1px solid
        #E8EDF4;

    font-size: 13px;
}

tbody tr:hover {

    background: #F8FAFD;
}

.equipment-name {

    font-weight: 600;

    color: #172033;
}


/* =========================
   BADGES
========================= */

.badge {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 15px;

    font-size: 11px;

    font-weight: bold;
}

.badge-available {

    background: #E8F7EE;

    color: #176B3A;
}

.badge-low {

    background: #FFF4D6;

    color: #8A6100;
}

.badge-none {

    background: #FDECEC;

    color: #A52828;
}


/* =========================
   EMPTY
========================= */

.empty {

    padding: 55px 20px;

    text-align: center;
}

.empty-icon {

    font-size: 42px;

    margin-bottom: 12px;
}

.empty h3 {

    margin: 0 0 7px;
}

.empty p {

    margin: 0;

    color: #667085;

    font-size: 14px;
}


/* =========================
   FOOTER NOTE
========================= */

.note {

    margin-top: 18px;

    padding: 13px 15px;

    background: #EAF2FB;

    border:
        1px solid
        #D5E4F5;

    border-radius: 9px;

    color: #1E4E8C;

    font-size: 12px;

    line-height: 1.5;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 650px) {

    .header {

        padding: 16px;

        gap: 10px;
    }

    .header-left h1 {

        font-size: 18px;
    }

    .container {

        width: 92%;
    }

    th,
    td {

        padding: 12px;
    }
}

</style>

</head>


<body>


<header class="header">

    <div class="header-left">

        <h1>
            Stock Availability
        </h1>

        <p>
            Core Admin / Resource Monitoring
        </p>

    </div>


    <a
        href="core_admin_dashboard.php"
        class="back-btn"
    >
        ← Back to Dashboard
    </a>

</header>



<div class="container">


    <!-- SUMMARY -->

    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-label">
                Equipment Types
            </div>

            <div class="summary-value">
                <?php echo $total_types; ?>
            </div>

            <div class="summary-note">
                Different equipment categories
            </div>

        </div>



        <div class="summary-card">

            <div class="summary-label">
                Total Stock
            </div>

            <div class="summary-value">
                <?php echo $total_stock; ?>
            </div>

            <div class="summary-note">
                Total available inventory
            </div>

        </div>



        <div class="summary-card">

            <div class="summary-label">
                Allocated / Used
            </div>

            <div class="summary-value">
                <?php echo $total_assigned; ?>
            </div>

            <div class="summary-note">
                Currently assigned quantities
            </div>

        </div>



        <div class="summary-card">

            <div class="summary-label">
                Remaining
            </div>

            <div class="summary-value">
                <?php echo $total_remaining; ?>
            </div>

            <div class="summary-note">
                Currently available quantities
            </div>

        </div>


    </div>



    <!-- MAIN TABLE -->

    <div class="main-card">


        <div class="card-header">

            <h2>
                Equipment Availability
            </h2>

            <p>
                Current equipment stock, allocation and remaining availability.
            </p>

        </div>



        <?php if (empty($equipment)): ?>


            <div class="empty">

                <div class="empty-icon">
                    📦
                </div>

                <h3>
                    No Equipment Records
                </h3>

                <p>
                    There are currently no equipment records available.
                </p>

            </div>


        <?php else: ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Equipment
                            </th>

                            <th>
                                Total Quantity
                            </th>

                            <th>
                                Allocated / Used
                            </th>

                            <th>
                                Remaining
                            </th>

                            <th>
                                Availability
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php
                        $number = 1;
                        ?>


                        <?php foreach ($equipment as $item): ?>


                            <?php

                            $remaining =
                                $item[
                                    "remaining_quantity"
                                ];

                            $total =
                                $item[
                                    "total_quantity"
                                ];

                            if ($remaining <= 0) {

                                $badge_class =
                                    "badge-none";

                                $badge_text =
                                    "Not Available";

                            } elseif (
                                $total > 0 &&
                                $remaining <=
                                ceil($total * 0.25)
                            ) {

                                $badge_class =
                                    "badge-low";

                                $badge_text =
                                    "Low Availability";

                            } else {

                                $badge_class =
                                    "badge-available";

                                $badge_text =
                                    "Available";
                            }

                            ?>


                            <tr>


                                <td>
                                    <?php
                                    echo $number++;
                                    ?>
                                </td>


                                <td class="equipment-name">

                                    <?php
                                    echo h(
                                        $item[
                                            "equipment_name"
                                        ]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo $total;
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo $item[
                                        "assigned_quantity"
                                    ];
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo $remaining;
                                    ?>

                                </td>


                                <td>

                                    <span
                                        class="badge
                                        <?php
                                        echo $badge_class;
                                        ?>"
                                    >

                                        <?php
                                        echo $badge_text;
                                        ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </div>



    <div class="note">

        <strong>Note:</strong>
        Remaining quantity is calculated as
        Total Quantity − Allocated / Used Quantity.
        Equipment is grouped by equipment type across all stages.

    </div>


</div>


</body>

</html>