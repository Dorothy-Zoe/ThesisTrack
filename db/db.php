<?php
// Database configuration
$host = 'localhost';
$dbname = 'thesis_track';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO attributes
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $pdo->query('SELECT 1');
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        die("Database access denied. Please check username and password.");
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        die("Database 'thesis_track' not found. Please create the database first.");
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Sanitize input data
 * @param string $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize($data) {
    if ($data === null) {
        return null;
    }
    
    // Remove whitespace from beginning and end
    $data = trim($data);
    
    // Remove backslashes
    $data = stripslashes($data);
    
    // Convert special characters to HTML entities
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Validate email address
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a secure random password
 * @param int $length The length of the password
 * @return string The generated password
 */
function generatePassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charactersLength = strlen($characters);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $password;
}

// /**
//  * Log activity to database or file
//  * @param string $action The action performed
//  * @param int $userId The user who performed the action
//  * @param string $details Additional details
//  */
// function logActivity($action, $userId = null, $details = null) {
//     global $pdo;
    
//     try {
//         $stmt = $pdo->prepare("
//             INSERT INTO activity_logs (user_id, action, details, created_at) 
//             VALUES (?, ?, ?, NOW())
//         ");
//         $stmt->execute([$userId, $action, $details]);
//     } catch (PDOException $e) {
//         // Log to file if database logging fails
//         error_log("Activity log failed: " . $e->getMessage());
//     }
// }


?>
