<?php
session_start();
require_once '../db/db.php'; 

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
    <title>ThesisTrack</title>
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../CSS/student_feedback.css">
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
            <a href="student_chap-upload.php" class="nav-item" data-tab="upload">
                <i class="fas fa-folder"></i> Chapter Uploads
            </a>
            <a href="student_feedback.php" class="nav-item active" data-tab="feedback">
                <i class="fas fa-comments"></i> Feedback
            </a>
            <a href="student_kanban-progress.php" class="nav-item" data-tab="kanban">
                <i class="fas fa-clipboard-list"></i> Chapter Progress
            </a>
           <a href="#" id="logoutBtn" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i> Logout
           </a>

            <!-- Logout Confirmation Modal for SIDEBAR -->
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

            <!-- Feedback Tab -->
            <div id="feedback" class="tab-content">
                <div class="card">
                   <h3 style="color: #1a202c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        Chapter Feedback from Advisor
                   </h3>

                    <?php if (!empty($chapters)): ?>
                        <?php
                        // Filter chapters that have feedback
                        $feedbackChapters = array_filter($chapters, function($c) {
                            return !empty($c['feedback']);
                        });
                        // Sort by updated_at or created_at descending
                        usort($feedbackChapters, function($a, $b) {
                            return strtotime($b['updated_at'] ?? $b['created_at']) - strtotime($a['updated_at'] ?? $a['created_at']);
                        });
                        ?>
                        <?php if (!empty($feedbackChapters)): ?>
                            <?php foreach ($feedbackChapters as $chapter): ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <strong><?php echo htmlspecialchars($userGroup['advisor_name'] ?? 'Advisor'); ?></strong>
                                        <span class="feedback-date"><?php echo date('M j, Y', strtotime($chapter['updated_at'] ?? $chapter['created_at'])); ?></span>
                                    </div>
                                    <div class="feedback-chapter">Chapter <?php echo htmlspecialchars($chapter['chapter_number']); ?>: <?php echo htmlspecialchars($chapterNames[$chapter['chapter_number']] ?? 'N/A'); ?></div>
                                    <div class="feedback-content">
                                        <p><?php echo nl2br(htmlspecialchars($chapter['feedback'])); ?></p>
                                    </div>
                                    <?php if ($chapter['score'] !== null): ?>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span>Score: </span>
                                            <span style="background: <?php echo ($chapter['score'] < 80 && $chapter['score'] >= 60) ? '#ed8936' : (($chapter['score'] < 60) ? '#f56565' : '#48bb78'); ?>; color: white; padding: 0.25rem 0.6rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600;"><?php echo htmlspecialchars($chapter['score']); ?>/100</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No feedback received yet.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No group chapters found to display feedback.</p>
                    <?php endif; ?>
                </div>
            </div>

 
        </main>
    </div>

  <script src="../JS/student_feedback.js"></script>
</body>
</html>
