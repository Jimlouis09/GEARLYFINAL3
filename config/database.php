<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'gearly_db';

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
}
catch(Throwable $e) {
    http_response_code(500);
    exit('Database connection failed. Import database/gearly_db.sql in phpMyAdmin and check config/database.php.');
}