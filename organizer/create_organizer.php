<?php

include "../db.php";

// Organizer details
$name = "Event Organizer";
$email = "organizer@eventhub.com";
$phone = "9876543210";
$college = "College Event Hub";
$department = "Event Management";
$student_id = "ORG001";

$password = "Organizer@123";

// Create secure password hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check organizer account
$check = $conn->prepare(
    "SELECT id FROM users WHERE email = ?"
);

$check->bind_param("s", $email);
$check->execute();

$result = $check->get_result();

if ($result->num_rows > 0) {

    // Account already exists → reset password
    $user = $result->fetch_assoc();
    $user_id = $user["id"];

    $update = $conn->prepare(
        "UPDATE users
         SET password = ?, role = 'organizer'
         WHERE id = ?"
    );

    $update->bind_param(
        "si",
        $hashed_password,
        $user_id
    );

    if ($update->execute()) {

        echo "<h2 style='color:green;'>
        Organizer password reset successfully!
        </h2>";

        echo "<p><b>Email:</b> organizer@eventhub.com</p>";
        echo "<p><b>Password:</b> Organizer@123</p>";

        echo "<p>
        <a href='../login.html'>Go to Login</a>
        </p>";

    } else {

        echo "Password reset failed: " . $update->error;
    }

    $update->close();

} else {

    // Create new organizer account
    $role = "organizer";

    $stmt = $conn->prepare(
        "INSERT INTO users
        (name, email, phone, college, department, student_id, password, role)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssssssss",
        $name,
        $email,
        $phone,
        $college,
        $department,
        $student_id,
        $hashed_password,
        $role
    );

    if ($stmt->execute()) {

        echo "<h2 style='color:green;'>
        Organizer account created successfully!
        </h2>";

        echo "<p><b>Email:</b> organizer@eventhub.com</p>";
        echo "<p><b>Password:</b> Organizer@123</p>";

        echo "<p>
        <a href='../login.html'>Go to Login</a>
        </p>";

    } else {

        echo "Account creation failed: " . $stmt->error;
    }

    $stmt->close();
}

$check->close();
$conn->close();

?>