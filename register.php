<?php
require_once 'db/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$name = sanitize($_POST['name']);
$email = sanitize($_POST['email']);
$password = $_POST['password'];
$role = sanitize($_POST['role']);

// Validate required fields
if (empty($name) || empty($email) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Email address already registered.']);
    exit();
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    if ($role === 'student') {
        $student_id = sanitize($_POST['student_id']);
        $course = sanitize($_POST['course']);
        $year_level = sanitize($_POST['year_level']);
        $section = sanitize($_POST['section']);
        
        // Validate student-specific fields
        if (empty($student_id) || empty($course) || empty($year_level) || empty($section)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all student information.']);
            exit();
        }
        
        // Check if student ID already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student ID already registered.']);
            exit();
        }
        
        // Create section format (e.g., BSCS-4A)
        $full_section = $course . '-' . $year_level . $section;
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, student_id, course, year_level, section, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $email, $hashedPassword, $role, $student_id, $course, $year_level, $full_section]);
        
        echo json_encode(['success' => true, 'message' => 'Student registration successful! You can now login.']);
        
    } elseif ($role === 'advisor') {
        $employee_id = sanitize($_POST['employee_id']);
        $course = sanitize($_POST['course']);
        $year_handled = sanitize($_POST['year_handled']);
        $sections_handled = sanitize($_POST['sections_handled']);
        $department = sanitize($_POST['department']);
        
        // Validate advisor-specific fields
        if (empty($employee_id) || empty($course) || empty($year_handled) || empty($sections_handled)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all advisor information.']);
            exit();
        }
        
        // Check if employee ID already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Employee ID already registered.']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, employee_id, course, year_handled, sections_handled, department, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $email, $hashedPassword, $role, $employee_id, $course, $year_handled, $sections_handled, $department]);
        
        echo json_encode(['success' => true, 'message' => 'Advisor registration successful! You can now login.']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    }
    
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
?>
