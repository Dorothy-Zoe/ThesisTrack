<?php
// Detect if running in GitHub Actions CI
$isCI = getenv('CI') === 'true';

// Database configuration
if ($isCI) {
    // CI / PHPUnit test database
    $host = '127.0.0.1';
    $dbname = 'thesis_track_test';
    $username = 'root';
    $password = 'root';
} else {
    // Local development database
    $host = 'localhost';
    $dbname = 'thesis_track';
    $username = 'root';
    $password = '';
}

// Create mysqli connection
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("MySQLi connection failed: " . $conn->connect_error);
}

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// ------------------
// Helper Functions
// ------------------
function sanitize($data) {
    if ($data === null) return null;
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
?>
