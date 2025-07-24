<?php
session_start();

require_once '../config.php';
require_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("SELECT id, password FROM management WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $email;
                header("Location: ../e_certificate/index.php?page=management_dashboard");
                exit();
            } else {
                $_SESSION['error'] = "Invalid email or password.";
            }
        } else {
            $_SESSION['error'] = "Invalid email or password.";
        }
        $stmt->close();
        $conn->close();
    }
}

header("Location: ../e_certificate/index.php?page=management_login");
exit();
?>