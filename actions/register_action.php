<?php
session_start();

require_once '../config.php';
require_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $institution_name = trim($_POST['institution_name']);

    if (empty($email) || empty($password) || empty($confirm_password) || empty($institution_name)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../e_certificate/index.php?page=management_register");
        exit();
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: ../e_certificate/index.php?page=management_register");
        perifer: exit();
    }

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT id FROM management WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists.";
        $stmt->close();
        $conn->close();
        header("Location: ../e_certificate/index.php?page=management_register");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO management (email, password, institution_name, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $email, $hashed_password, $institution_name);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful. Please login.";
        header("Location: ../e_certificate/index.php?page=management_login");
        exit();
    } else {
        $_SESSION['error'] = "An error occurred during registration.";
    }
    $stmt->close();
    $conn->close();
}

header("Location: ../e_certificate/index.php?page=management_register");
exit();
?>