<?php
require_once 'auth.php';
requireRole(['student', 'advisor']);

$chapterId = (int)$_GET['id'];

// Get chapter details
$stmt = $pdo->prepare("
    SELECT c.*, g.title as group_title, g.section, 
           er.ai_score, er.plagiarism_score, er.grammar_score, er.structure_score,
           er.citation_issues, er.highlighted_content,
           u.name as advisor_name
    FROM chapters c 
    JOIN groups g ON c.group_id = g.id 
    LEFT JOIN evaluation_results er ON c.id = er.chapter_id 
    LEFT JOIN users u ON g.advisor_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$chapterId]);
$chapter = $stmt->fetch();

if (!$chapter) {
    header('Location: Student/student_dashboard.php');
    exit();
}

// Check if user has access to this chapter
if ($_SESSION['role'] === 'student') {
    $userGroup = getUserGroup($_SESSION['user_id']);
    if (!$userGroup || $userGroup['id'] !== $chapter['group_id']) {
        header('Location: unauthorized.php');
        exit();
    }
}

// Get feedback for this chapter
$stmt = $pdo->prepare("
    SELECT f.*, u.name as advisor_name 
    FROM feedback f 
    JOIN users u ON f.advisor_id = u.id 
    WHERE f.chapter_id = ? 
    ORDER BY f.created_at DESC
");
$stmt->execute([$chapterId]);
$feedback = $stmt->fetchAll();

$citationIssues = $chapter['citation_issues'] ? json_decode($chapter['citation_issues'], true) : [];
$highlightedContent = $chapter['highlighted_content'] ? json_decode($chapter['highlighted_content'], true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Review - ThesisTrack</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .review-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .chapter-header {
            background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .evaluation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .score-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .score-circle {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            position: relative;
        }

        .score-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
            color: #4c1d95;
        }

        .issues-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .issue-item {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }

        .highlight-item {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }

        .feedback-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .feedback-item {
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem 0;
        }

        .feedback-item:last-child {
            border-bottom: none;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .rating-display {
            color: #f59e0b;
            font-size: 1.2rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #4c1d95;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="review-container">
        <div class="chapter-header">
            <h1>Chapter <?php echo $chapter['chapter_number']; ?>: <?php echo htmlspecialchars($chapter['chapter_name']); ?></h1>
            <p><strong>Group:</strong> <?php echo htmlspecialchars($chapter['group_title']); ?></p>
            <p><strong>Section:</strong> <?php echo htmlspecialchars($chapter['section']); ?></p>
            <p><strong>File:</strong> <?php echo htmlspecialchars($chapter['original_filename']); ?></p>
            <p><strong>Uploaded:</strong> <?php echo date('M j, Y g:i A', strtotime($chapter['upload_date'])); ?></p>
            <div class="chapter-status status-<?php echo $chapter['status']; ?>" style="margin-top: 1rem; display: inline-block;">
                <?php echo ucfirst(str_replace('_', ' ', $chapter['status'])); ?>
            </div>
        </div>

        <?php if ($chapter['ai_score']): ?>
            <div class="evaluation-grid">
                <div class="score-card">
                    <h3>Overall Score</h3>
                    <div class="score-circle">
                        <svg width="80" height="80">
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#4c1d95" stroke-width="8"
                                    stroke-dasharray="<?php echo 2 * pi() * 35; ?>"
                                    stroke-dashoffset="<?php echo 2 * pi() * 35 * (1 - $chapter['score'] / 100); ?>"
                                    stroke-linecap="round" transform="rotate(-90 40 40)"/>
                        </svg>
                        <div class="score-value"><?php echo $chapter['score']; ?>%</div>
                    </div>
                    <p>Content Quality</p>
                </div>

                <div class="score-card">
                    <h3>Originality</h3>
                    <div class="score-circle">
                        <svg width="80" height="80">
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#059669" stroke-width="8"
                                    stroke-dasharray="<?php echo 2 * pi() * 35; ?>"
                                    stroke-dashoffset="<?php echo 2 * pi() * 35 * (1 - $chapter['plagiarism_score'] / 100); ?>"
                                    stroke-linecap="round" transform="rotate(-90 40 40)"/>
                        </svg>
                        <div class="score-value"><?php echo $chapter['plagiarism_score']; ?>%</div>
                    </div>
                    <p>Plagiarism Check</p>
                </div>

                <div class="score-card">
                    <h3>Grammar</h3>
                    <div class="score-circle">
                        <svg width="80" height="80">
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#f59e0b" stroke-width="8"
                                    stroke-dasharray="<?php echo 2 * pi() * 35; ?>"
                                    stroke-dashoffset="<?php echo 2 * pi() * 35 * (1 - $chapter['grammar_score'] / 100); ?>"
                                    stroke-linecap="round" transform="rotate(-90 40 40)"/>
                        </svg>
                        <div class="score-value"><?php echo $chapter['grammar_score']; ?>%</div>
                    </div>
                    <p>Language Quality</p>
                </div>

                <div class="score-card">
                    <h3>Structure</h3>
                    <div class="score-circle">
                        <svg width="80" height="80">
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <circle cx="40" cy="40" r="35" fill="none" stroke="#8b5cf6" stroke-width="8"
                                    stroke-dasharray="<?php echo 2 * pi() * 35; ?>"
                                    stroke-dashoffset="<?php echo 2 * pi() * 35 * (1 - $chapter['structure_score'] / 100); ?>"
                                    stroke-linecap="round" transform="rotate(-90 40 40)"/>
                        </svg>
                        <div class="score-value"><?php echo $chapter['structure_score']; ?>%</div>
                    </div>
                    <p>Organization</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($citationIssues) || !empty($highlightedContent)): ?>
            <div class="issues-section">
                <h3>Analysis Results</h3>
                
                <?php if (!empty($citationIssues)): ?>
                    <h4 style="color: #dc2626; margin-bottom: 1rem;">Citation Issues</h4>
                    <?php foreach ($citationIssues as $issue): ?>
                        <div class="issue-item">
                            <strong>⚠️ Citation Issue:</strong> <?php echo htmlspecialchars($issue); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($highlightedContent)): ?>
                    <h4 style="color: #f59e0b; margin-bottom: 1rem; margin-top: 2rem;">Suggestions for Improvement</h4>
                    <?php foreach ($highlightedContent as $highlight): ?>
                        <div class="highlight-item">
                            <strong>💡 Suggestion:</strong> <?php echo htmlspecialchars($highlight); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="feedback-section">
            <h3>Advisor Feedback</h3>
            <?php if (empty($feedback)): ?>
                <p>No feedback available yet. Your advisor will review this chapter soon.</p>
            <?php else: ?>
                <?php foreach ($feedback as $fb): ?>
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <div>
                                <h4><?php echo htmlspecialchars($fb['advisor_name']); ?></h4>
                                <p><?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?></p>
                            </div>
                            <div class="rating-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo $i <= $fb['rating'] ? '⭐' : '☆'; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="feedback-content">
                            <p><?php echo nl2br(htmlspecialchars($fb['comments'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <?php if ($_SESSION['role'] === 'student'): ?>
                <a href="student_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <?php if ($chapter['status'] !== 'approved'): ?>
                    <a href="student_dashboard.php#upload" class="btn btn-primary">Re-upload Chapter</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="advisor_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <a href="provide_feedback.php?chapter_id=<?php echo $chapterId; ?>" class="btn btn-primary">Provide Feedback</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
