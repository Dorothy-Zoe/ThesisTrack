<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../student_login.php');
    exit();
}


$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// In your dashboard PHP code where you fetch the profile picture:
$profile_picture = '../images/default-user.png'; // Default image

try {
    // Get student's profile picture path
    $stmt = $pdo->prepare("SELECT profile_picture FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    // Verify and set profile picture if exists
    if (!empty($student['profile_picture'])) {
        $relative_path = $student['profile_picture'];
        $absolute_path = dirname(__DIR__) . '/' . $relative_path;
        
        // Check if file exists and is readable
        if (file_exists($absolute_path) && is_readable($absolute_path)) {
            $profile_picture = '../' . $relative_path;
        }
    }
} catch (PDOException $e) {
    error_log("Database error fetching profile picture: " . $e->getMessage());
}



$user_section = null;
$userGroup = null;
try {
    $groupQuery = $pdo->prepare("
    SELECT sg.*, 
           CONCAT(a.first_name, ' ', a.middle_name, ' ', a.last_name) AS advisor_name,
           s.section AS student_section
    FROM student_groups sg
    JOIN group_members gm ON sg.group_id = gm.group_id
    JOIN students s ON gm.student_id = s.id
    LEFT JOIN advisors a ON sg.advisor_id = a.id
    WHERE gm.student_id = ?
    LIMIT 1
");

    $groupQuery->execute([$user_id]);
    $userGroup = $groupQuery->fetch(PDO::FETCH_ASSOC);
    $user_section = $userGroup['student_section'] ?? 'N/A';
} catch (Exception $e) {
    $userGroup = null;
}

$groupMembers = [];
if ($userGroup) {
$membersQuery = $pdo->prepare("
    SELECT 
        CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) AS name,
        s.email, 
        gm.role_in_group
    FROM students s
    JOIN group_members gm ON s.id = gm.student_id
    WHERE gm.group_id = ?
");
$membersQuery->execute([$userGroup['group_id']]); 
$groupMembers = $membersQuery->fetchAll(PDO::FETCH_ASSOC);

}

$chapters = [];
if ($userGroup) {
    $chaptersQuery = $pdo->prepare("
        SELECT * FROM chapters
        WHERE group_id = ?
        ORDER BY chapter_number
    ");
    $chaptersQuery->execute([$userGroup['id']]);
    $chapters = $chaptersQuery->fetchAll(PDO::FETCH_ASSOC);
}

$notifications = [];
$notificationsQuery = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$notificationsQuery->execute([$user_id]);
$notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);

// Progress Calculation
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
    <link rel="stylesheet" href="../CSS/student_dashboard.css">
</head>
<body>


    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
               <div class="sidebar-user" onclick="openUploadModal()" style="cursor: pointer;">
      <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
         class="sidebar-avatar" 
         alt="Profile Picture"
         id="sidebarProfileImage" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Student</span>
            </div>
           <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-tab="dashboard">
                <i class="fas fa-chart-bar"></i> Dashboard
            </a>
            <a href="student_chap-upload.php" class="nav-item" data-tab="upload">
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
               <!-- In the header -->
<img src="<?php echo htmlspecialchars($profile_picture); ?>"
     alt="User Avatar"
     class="user-avatar"
     id="userAvatar"
     tabindex="0" />
        <div class="dropdown-menu" id="userDropdown">
          <a href="#" class="dropdown-item">
            <i class="fas fa-cog"></i> Settings
          </a>
         <a href="#" class="dropdown-item" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> Logout
         </a>

        <!-- Logout Confirmation Modal for HEADER -->
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
            <header class="main-header">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                </div>
            </header>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <?php if ($userGroup): ?>
                    <div class="dashboard-grid">
                        <div class="card progress-card">
                            <h3>Overall Thesis Progress</h3>
                            <div class="progress-circle">
                                <svg class="progress-ring" width="120" height="120">
                                    <circle class="progress-ring-circle" stroke="rgba(255,255,255,0.3)" stroke-width="8" fill="transparent" r="52" cx="60" cy="60"/>
                                    <circle class="progress-ring-circle progress" stroke="white" stroke-width="8" fill="transparent" r="52" cx="60" cy="60" style="stroke-dasharray: 326.73; stroke-dashoffset: <?php echo 326.73 - (326.73 * $progressPercentage / 100); ?>;"/>
                                </svg>
                                <div class="progress-text"><?php echo round($progressPercentage); ?>%</div>
                            </div>
                            <p><?php echo $completedChapters; ?> of <?php echo $totalChapters; ?> Chapters Completed</p>
                        </div>

                        <div class="card status-card">
                            <h3 style="color: #1a202c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                Chapter Status Overview
                            </h3>

                            <div class="status-list">
                                <?php
                                $chapterNames = [
                                    1 => 'Introduction',
                                    2 => 'Literature Review',
                                    3 => 'Methodology',
                                    4 => 'Results & Discussion',
                                    5 => 'Conclusion'
                                ];
                                for ($i = 1; $i <= 5; $i++):
                                    $chapterStatus = 'not_submitted';
                                    $chapterTitle = $chapterNames[$i];
                                    foreach ($chapters as $chapter) {
                                        if ($chapter['chapter_number'] == $i) {
                                            $chapterStatus = $chapter['status'];
                                            // If you want to use the actual title from DB, uncomment below
                                            // $chapterTitle = htmlspecialchars($chapter['chapter_name']);
                                            break;
                                        }
                                    }
                                ?>
                                    <div class="status-item">
                                        <span class="chapter">Chapter <?php echo $i; ?>: <?php echo $chapterTitle; ?></span>
                                        <span class="status <?php echo htmlspecialchars($chapterStatus); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $chapterStatus))); ?>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="card group-info-card">
                            <h3>Group Information</h3>
                            <div class="course-badge"><?php echo htmlspecialchars($user_section); ?></div>
                            <p><strong>Group:</strong> <?php echo htmlspecialchars($userGroup['group_name'] ?? 'N/A'); ?></p>
                            <p><strong>Section:</strong> <?php echo htmlspecialchars($userGroup['section'] ?? 'N/A'); ?></p>
                            <p><strong>Thesis Title:</strong> <?php echo htmlspecialchars($userGroup['thesis_title'] ?? 'N/A'); ?></p>
                            <?php if (!empty($groupMembers)): ?>
                                <p><strong>Members:</strong></p>
                              <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <?php foreach ($groupMembers as $member): ?>
                                    <li>
                                        <?php echo htmlspecialchars($member['name']); ?>
                                        <?php if ($member['role_in_group'] === 'leader'): ?>
                                            <span class="role-leader">(Leader)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <p style="margin-top: 1rem;"><strong>Advisor:</strong> <?php echo htmlspecialchars($userGroup['advisor_name'] ?? 'Not Assigned'); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3 style="color: #1a202c;" >No Group Assigned</h3>
                        <p>You are not currently assigned to any thesis group. Please contact your thesis advisor.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

     <!-- Upload Modal (add this at the bottom of your page) -->
<div class="profile-upload-modal" id="uploadModal">
    <div class="profile-upload-modal-content">
        <span class="profile-upload-close" onclick="closeUploadModal()">&times;</span>
        <h3>Update Profile Picture</h3>
        
        <form id="avatarUploadForm" enctype="multipart/form-data">
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Click to select an image</p>
                <img id="imagePreview" class="preview-image">
                <input type="file" id="fileInput" name="profile_picture" accept="image/*" style="display:none;" onchange="previewImage(this)">
            </div>
            <button type="button" class="upload-button" id="uploadBtn" onclick="uploadProfilePicture()">Upload</button>
        </form>
    </div>
</div>
   

        <script src="../JS/student_dashboard.js"></script>
</body>
</html>
