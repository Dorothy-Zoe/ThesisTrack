<?php
// Prevent multiple inclusions
if (defined('DB_INCLUDED')) {
    return $pdo ?? null;
}
define('DB_INCLUDED', true);

// Detect if running in CI / PHPUnit
$isCI = getenv('CI') === 'true' || getenv('PHPUNIT') === '1';

// Database configuration
if ($isCI) {
    $host = '127.0.0.1';
    $dbname = 'thesis_track_test';
    $username = 'root';
    $password = 'root';
} else {
    $host = 'localhost';
    $dbname = 'thesis_track';
    $username = 'root';
    $password = '';
}

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // More detailed error message for debugging
    $errorMsg = "Database connection failed: " . $e->getMessage() . 
                " | Host: $host | DB: $dbname | User: $username";
    error_log($errorMsg);
    
    // For CI environment, throw exception instead of dying to see the error
    if ($isCI) {
        throw new RuntimeException($errorMsg);
    } else {
        die("Database connection failed. Please check DB settings.");
    }
}

// ------------------
// Helper Functions
// ------------------
if (!function_exists('sanitize')) {
    function sanitize($data) {
        if ($data === null) return null;
        return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

// Return the PDO instance
return $pdo;
?>