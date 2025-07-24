<?php
session_start();

if (isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
}

header("Location: ../e_certificate/index.php?page=management_login");
exit();
?>