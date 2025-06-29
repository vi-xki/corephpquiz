<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $gender   = $_POST['gender'];
    $dob      = $_POST['dob'];
    $bio      = $_POST['bio'];
    $skills   = isset($_POST['skills']) ? implode(",", $_POST['skills']) : '';

    $sql = "INSERT INTO users (name, email, password, gender, dob, bio, skills)
            VALUES ('$name', '$email', '$password', '$gender', '$dob', '$bio', '$skills')";

    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'User created successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
?>
