<?php
session_start();
require_once '../db/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$chapter_number = $_POST['chapter_number'] ?? null;
$file = $_FILES['file'] ?? null;

// Validate inputs
if (!$chapter_number || !$file || $file['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'Invalid file or chapter number';
    if ($file && $file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File upload incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file selected';
                break;
            default:
                $error_message = 'Upload error occurred';
        }
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

try {
    // Get user's group
    $groupQuery = $pdo->prepare("SELECT g.id FROM groups g JOIN group_members gm ON g.id = gm.group_id WHERE gm.student_id = ?");
    $groupQuery->execute([$user_id]);
    $userGroup = $groupQuery->fetch(PDO::FETCH_ASSOC);

    if (!$userGroup) {
        echo json_encode(['success' => false, 'message' => 'No group found for this student']);
        exit();
    }

    $group_id = $userGroup['id'];

    // Validate file type and size
    $max_file_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
        exit();
    }

    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'doc', 'docx'];

    if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF and Word documents (.pdf, .doc, .docx) are allowed']);
        exit();
    }

    // Create upload directory if it doesn't exist
    $upload_dir = dirname(__DIR__) . '/uploads/chapters/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit();
        }
    }

    if (!is_writable($upload_dir)) {
        error_log("Upload directory is not writable: " . $upload_dir);
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit();
    }

    // Generate unique filename
    $original_filename = $file['name'];
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_filename);
    $filename = 'chapter_' . $chapter_number . '_group_' . $group_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        exit();
    }

    // Define chapter names
    $chapter_names = [
        1 => 'Introduction',
        2 => 'Review of Related Literature',
        3 => 'Methodology',
        4 => 'Results and Discussion',
        5 => 'Summary, Conclusion, and Recommendation'
    ];

    $chapter_name = $chapter_names[$chapter_number] ?? 'Chapter ' . $chapter_number;

    // Save to database
    $pdo->beginTransaction();

    try {
        // Check if chapter already exists
        $checkStmt = $pdo->prepare("SELECT id FROM chapters WHERE group_id = ? AND chapter_number = ?");
        $checkStmt->execute([$group_id, $chapter_number]);
        $existingChapter = $checkStmt->fetch();

        if ($existingChapter) {
            // Delete old file if exists
            $oldFileStmt = $pdo->prepare("SELECT file_path FROM chapters WHERE id = ?");
            $oldFileStmt->execute([$existingChapter['id']]);
            $oldFile = $oldFileStmt->fetch();
            
            if ($oldFile && $oldFile['file_path']) {
                $old_file_path = dirname(__DIR__) . '/' . $oldFile['file_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }

            // Update existing chapter
            $updateStmt = $pdo->prepare("UPDATE chapters SET filename = ?, original_filename = ?, file_path = ?, file_size = ?, file_type = ?, status = 'pending', upload_date = NOW(), updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([
                $filename, 
                $original_filename, 
                'uploads/chapters/' . $filename, 
                $file['size'], 
                $file['type'], 
                $existingChapter['id']
            ]);
        } else {
            // Insert new chapter
            $insertStmt = $pdo->prepare("INSERT INTO chapters (group_id, chapter_number, chapter_name, filename, original_filename, file_path, file_size, file_type, status, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $insertStmt->execute([
                $group_id, 
                $chapter_number, 
                $chapter_name, 
                $filename, 
                $original_filename, 
                'uploads/chapters/' . $filename, 
                $file['size'], 
                $file['type']
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Chapter uploaded successfully',
            'filename' => $original_filename,
            'chapter_name' => $chapter_name,
            'file_path' => 'uploads/chapters/' . $filename,
            'download_url' => '../uploads/chapters/' . $filename
        ]);

    } catch (PDOException $e) {
        $pdo->rollback();
        // Delete uploaded file if database save fails
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        error_log("Database error in upload_handler: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }

} catch (Exception $e) {
    error_log("General error in upload_handler: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during upload']);
}
?>
