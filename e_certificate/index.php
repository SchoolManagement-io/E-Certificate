<?php

// Prevent directory traversal
$page = isset($_GET['page']) ? basename($_GET['page']) : 'management_login';

switch ($page) {
    case 'management_login':
        include '../pages/management_login.php';
        break;
    case 'management_register':
        include '../pages/management_register.php';
        break;
    case 'management_dashboard':
        include '../pages/management_dashboard.php';
        break;
    case 'event_registration':
        include '../pages/event_registration.php';
        break;
    case 'verify_certificate':
        include '../pages/verify_certificate.php';
        break;
    case 'download_certificate':
        include '../pages/download_certificate.php';
        break;
    default:
        http_response_code(404);
        echo "<h2>404 - Page Not Found</h2>";
        exit;
}
?>