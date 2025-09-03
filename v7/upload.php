<?php
session_start();
require_once 'db/db.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get user's group
$userGroup = getUserGroup($_SESSION['user_id']);
if (!$userGroup) {
    echo json_encode(['success' => false, 'message' => 'You are not assigned to any group']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate form data
        $chapter_number = (int)$_POST['chapter_number'];
        $chapter_name = sanitize($_POST['chapter_name']);
        
        if ($chapter_number < 1 || $chapter_number > 5) {
            throw new Exception('Invalid chapter number');
        }
        
        if (empty($chapter_name)) {
            throw new Exception('Chapter name is required');
        }
        
        // Validate file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }
        
        $file = $_FILES['file'];
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Only PDF and Word documents are allowed');
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('File size must be less than 10MB');
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'chapter_' . $userGroup['id'] . '_' . $chapter_number . '_' . time() . '.' . $extension;
        $uploadPath = 'uploads/chapters/' . $filename;
        
        // Create upload directory if it doesn't exist
        if (!file_exists('uploads/chapters')) {
            mkdir('uploads/chapters', 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Check if chapter already exists for this group
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE group_id = ? AND chapter_number = ?");
        $stmt->execute([$userGroup['id'], $chapter_number]);
        $existingChapter = $stmt->fetch();
        
        if ($existingChapter) {
            // Update existing chapter
            $stmt = $pdo->prepare("
                UPDATE chapters 
                SET chapter_name = ?, filename = ?, original_filename = ?, file_size = ?, 
                    file_type = ?, file_path = ?, status = 'pending', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $chapter_name,
                $filename,
                $file['name'],
                $file['size'],
                $extension,
                $uploadPath,
                $existingChapter['id']
            ]);
            $chapterId = $existingChapter['id'];
        } else {
            // Insert new chapter
            $stmt = $pdo->prepare("
                INSERT INTO chapters (group_id, chapter_number, chapter_name, filename, original_filename, 
                                    file_size, file_type, file_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $userGroup['id'],
                $chapter_number,
                $chapter_name,
                $filename,
                $file['name'],
                $file['size'],
                $extension,
                $uploadPath
            ]);
            $chapterId = $pdo->lastInsertId();
        }
        
        // Generate mock evaluation results
        $aiScore = rand(70, 95);
        $plagiarismScore = rand(5, 25);
        $grammarScore = rand(80, 98);
        $structureScore = rand(75, 95);
        $overallScore = round(($aiScore + (100 - $plagiarismScore) + $grammarScore + $structureScore) / 4);
        
        $citationIssues = [
            'Minor APA formatting issues detected',
            'Some citations missing page numbers',
            'Inconsistent citation style',
            'Missing DOI for some journal articles',
            'Citation format looks good'
        ];
        
        $suggestions = [
            'Consider adding more recent references',
            'Expand the theoretical framework section',
            'Improve transition between paragraphs',
            'Add more supporting evidence',
            'Well-structured content overall'
        ];
        
        // Insert or update evaluation results
        $stmt = $pdo->prepare("
            INSERT INTO evaluation_results (chapter_id, ai_score, plagiarism_score, grammar_score, 
                                          structure_score, overall_score, content_quality_score, 
                                          citation_issues, suggestions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            ai_score = VALUES(ai_score),
            plagiarism_score = VALUES(plagiarism_score),
            grammar_score = VALUES(grammar_score),
            structure_score = VALUES(structure_score),
            overall_score = VALUES(overall_score),
            content_quality_score = VALUES(content_quality_score),
            citation_issues = VALUES(citation_issues),
            suggestions = VALUES(suggestions),
            processed_at = NOW()
        ");
        $stmt->execute([
            $chapterId,
            $aiScore,
            $plagiarismScore,
            $grammarScore,
            $structureScore,
            $overallScore,
            $overallScore,
            $citationIssues[array_rand($citationIssues)],
            $suggestions[array_rand($suggestions)]
        ]);
        
        // Update chapter score
        $stmt = $pdo->prepare("UPDATE chapters SET score = ?, completeness_score = ? WHERE id = ?");
        $stmt->execute([$overallScore, $overallScore, $chapterId]);
        
        // Create notification
        createNotification(
            $_SESSION['user_id'],
            'Chapter Uploaded Successfully',
            "Chapter {$chapter_number}: {$chapter_name} has been uploaded and is being processed",
            'success'
        );
        
        // Notify advisor if assigned
        if ($userGroup['advisor_id']) {
            createNotification(
                $userGroup['advisor_id'],
                'New Chapter Submission',
                "A new chapter has been submitted by {$_SESSION['name']} for review",
                'info'
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Chapter uploaded successfully! Evaluation results are now available.',
            'redirect' => 'chapter_review.php?id=' . $chapterId
        ]);
        
    } catch (Exception $e) {
        // Clean up uploaded file if it exists
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
