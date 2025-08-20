<?php
session_start();
require_once 'db/db.php';

function requireRole($allowedRoles) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header('Location: login.php?error=access_denied');
        exit();
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function getUserGroup($student_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT g.*, u.name as advisor_name 
        FROM groups g 
        JOIN group_members gm ON g.id = gm.group_id 
        LEFT JOIN users u ON g.advisor_id = u.id 
        WHERE gm.student_id = ?
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetch();
}

function getGroupMembers($group_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.*, gm.role_in_group 
        FROM users u 
        JOIN group_members gm ON u.id = gm.student_id 
        WHERE gm.group_id = ?
    ");
    $stmt->execute([$group_id]);
    return $stmt->fetchAll();
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
