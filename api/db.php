<?php
$host = 'localhost';
$db   = 'playon';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset($charset);
} catch (\mysqli_sql_exception $e) {
    // If database doesn't exist, try to create it?
    // For now, just exit with error
    die("Database connection failed: " . $e->getMessage() . " Please import playon.sql in phpMyAdmin.");
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
