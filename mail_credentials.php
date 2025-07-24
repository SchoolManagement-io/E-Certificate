<?php

// Prevent direct access to this file
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.1 403 Forbidden');
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Direct access attempt blocked\n", FILE_APPEND);
    die('Access denied');
}

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'pranavshukla866@gmail.com');
define('SMTP_PASSWORD', 'bjfb wqqu urqy tzjd');
define('SMTP_PORT', 587);
?>