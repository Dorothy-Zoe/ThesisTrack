<?php
session_start();
require_once '../db/db.php'; // Assuming this file exists and handles database connection

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../student_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_section = $_SESSION['section'];

//Get user's group information
$userGroup = null;
if (isset($pdo)) {
    // Get group and advisor name
    $groupQuery = $pdo->prepare("
        SELECT g.*, 
               CONCAT(a.first_name, ' ', a.middle_name, ' ', a.last_name) AS advisor_name
        FROM groups g
        JOIN group_members gm ON g.id = gm.group_id
        LEFT JOIN advisors a ON g.advisor_id = a.id
        WHERE gm.student_id = ?
    ");
    $groupQuery->execute([$user_id]);
    $userGroup = $groupQuery->fetch(PDO::FETCH_ASSOC);
}

// Get group members
$groupMembers = [];
if ($userGroup && isset($pdo)) {
    $membersQuery = $pdo->prepare("
        SELECT 
            CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) AS name,
            s.email, 
            gm.role_in_group
        FROM students s
        JOIN group_members gm ON s.id = gm.student_id
        WHERE gm.group_id = ?
    ");
    $membersQuery->execute([$userGroup['id']]);
    $groupMembers = $membersQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Get chapters for the group
$chapters = [];
if ($userGroup && isset($pdo)) {
    $chaptersQuery = $pdo->prepare("
        SELECT * FROM chapters
        WHERE group_id = ?
        ORDER BY chapter_number
    ");
    $chaptersQuery->execute([$userGroup['id']]);
    $chapters = $chaptersQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Get notifications
$notifications = [];
if (isset($pdo)) {
    $notificationsQuery = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $notificationsQuery->execute([$user_id]);
    $notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate progress
$totalChapters = 5;
$completedChapters = 0;
foreach ($chapters as $chapter) {
    if ($chapter['status'] === 'approved') {
        $completedChapters++;
    }
}
$progressPercentage = ($totalChapters > 0) ? ($completedChapters / $totalChapters) * 100 : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <title>ThesisTrack </title>
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../CSS/student_chap-upload.css">
</head>
<body>


    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="../images/default-user.png" class="sidebar-avatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Student</span>
            </div>
           <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="nav-item" data-tab="dashboard">
                <i class="fas fa-chart-bar"></i> Dashboard
            </a>
            <a href="student_chap-upload.php" class="nav-item active" data-tab="upload">
                <i class="fas fa-folder"></i> Chapter Uploads
            </a>
            <a href="student_feedback.php" class="nav-item" data-tab="feedback">
                <i class="fas fa-comments"></i> Feedback
            </a>
            <a href="student_kanban-progress.php" class="nav-item" data-tab="kanban">
                <i class="fas fa-clipboard-list"></i> Chapter Progress
            </a>
           <a href="#" id="logoutBtn" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i> Logout
           </a>

            <!-- Logout Confirmation Modal for SIDEBAR-->
            <div id="logoutModal" class="modal">
            <div class="modal-content">
                <h3>Confirm Logout</h3>
                <p>Are you sure you want to logout?</p>
                <div class="modal-buttons">
                <button id="confirmLogout" class="btn btn-danger">Yes, Logout</button>
                <button id="cancelLogout" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
            </div>

        </aside>
           <!-- End Sidebar -->

    <div class="content-wrapper">
        <!-- Start Header -->
         <header class="blank-header">
             <div class="topbar-left">
    </div>
                <div class="topbar-right">
                <button class="topbar-icon" title="Notifications">
                <i class="fas fa-bell"></i></button>
                <div class="user-info dropdown">
                <img
                src="../images/default-user.png"
                alt="User Avatar"
                class="user-avatar"
                id="userAvatar"
                tabindex="0"
                />
        <div class="dropdown-menu" id="userDropdown">
          <a href="#" class="dropdown-item">
            <i class="fas fa-cog"></i> Settings
          </a>
         <a href="#" class="dropdown-item" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> Logout
         </a>

        <!-- Logout Confirmation Modal for HEADER-->
        <div id="logoutModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="modal-buttons">
            <button id="confirmLogout" class="btn btn-danger">Yes, Logout</button>
            <button id="cancelLogout" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
        </div>
        </div>
      </div>
    </div>
        </header>
        <!-- End Header -->


        <main class="main-content">
            <!-- <header class="main-header">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                </div>
            </header> -->

            <!-- Chapter Uploads Tab -->
            <div id="upload" class="tab-content">
                <div class="card">
                    <h3>Chapter-Based Thesis Uploads</h3>
                    <p style="margin-bottom: 2rem; color: #4a5568;">Upload each chapter individually for evaluation and advisor review.</p>

                    <div class="chapter-uploads">
                        <?php
                        $chapterData = [
                            1 => ['title' => 'Introduction', 'status' => 'completed', 'file' => 'Chapter1_Introduction.pdf', 'score' => 92, 'issues' => '✅ Structure complete, citations formatted correctly'],
                            2 => ['title' => 'Review of Related Literature', 'status' => 'completed', 'file' => 'Chapter2_Literature.pdf', 'score' => 88, 'issues' => '⚠️ Consider adding 3-5 more recent sources (2022-2024)'],
                            3 => ['title' => 'Methodology', 'status' => 'in-progress', 'file' => 'Chapter3_Methodology.pdf', 'score' => 75, 'issues' => '⚠️ Missing data collection timeline, unclear sampling method'],
                            4 => ['title' => 'Results and Discussion', 'status' => 'pending', 'file' => null, 'score' => null, 'issues' => null],
                            5 => ['title' => 'Summary, Conclusion, and Recommendation', 'status' => 'pending', 'file' => null, 'score' => null, 'issues' => null],
                        ];

                        foreach ($chapterData as $chapterNum => $data):
                            $currentChapterStatus = 'pending'; // Default status
                            $currentChapterFile = null;
                            $currentChapterScore = null;
                            $currentChapterFeedback = null;

                            // Override with actual data from DB if available
                            foreach ($chapters as $dbChapter) {
                                if ($dbChapter['chapter_number'] == $chapterNum) {
                                    $currentChapterStatus = $dbChapter['status'];
                                    $currentChapterFile = $dbChapter['file_path']; // Assuming file_path stores the filename
                                    $currentChapterScore = $dbChapter['score'];
                                    $currentChapterFeedback = $dbChapter['feedback'];
                                    break;
                                }
                            }

                            // Use mock AI validation data if no real feedback/score
                            $displayScore = $currentChapterScore ?? $data['score'];
                            $displayIssues = $currentChapterFeedback ?? $data['issues'];
                            $displayFile = $currentChapterFile ?? $data['file'];
                        ?>
                            <div class="chapter-card">
                                <div class="chapter-header">
                                    <div class="chapter-title">Chapter <?php echo $chapterNum; ?>: <?php echo htmlspecialchars($data['title']); ?></div>
                                    <div class="chapter-status status <?php echo htmlspecialchars($currentChapterStatus); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $currentChapterStatus))); ?></div>
                                </div>
                               <div class="upload-area" onclick="triggerFileUpload('chapter<?php echo $chapterNum; ?>')">
                                    <div class="upload-icon">
                                        <?php if ($displayFile): ?>
                                            <i class="fas fa-file-alt"></i> 
                                        <?php else: ?>
                                            <i class="fas fa-folder-open"></i> 
                                        <?php endif; ?>
                                    </div>
                                    <p><?php echo $displayFile ? htmlspecialchars($displayFile) : 'Click to upload or drag and drop'; ?></p>
                                    <input type="file" id="chapter<?php echo $chapterNum; ?>" accept=".pdf,.doc,.docx">
                                    </div>

                                <?php if ($displayScore !== null || $displayIssues !== null): ?>
                                    <div class="ai-validation">
                                        <?php if ($displayScore !== null): ?>
                                            <div class="validation-score">
                                                <span>Evaluation Score:</span>
                                                <span class="score-badge" style="<?php echo ($displayScore < 80 && $displayScore >= 60) ? 'background: #ed8936;' : (($displayScore < 60) ? 'background: #f56565;' : ''); ?>"><?php echo htmlspecialchars($displayScore); ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($displayIssues !== null): ?>
                                            <div class="validation-issues">
                                                <?php echo htmlspecialchars($displayIssues); ?>
                                            </div>
                                        <?php endif; ?>
                                        <button class="btn-secondary btn-small" onclick="viewValidationReport('chapter<?php echo $chapterNum; ?>')">View Report</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

 
        </main>
    </div>

  <script src="../JS/student_chap-upload.js"></script>
</body>
</html>
