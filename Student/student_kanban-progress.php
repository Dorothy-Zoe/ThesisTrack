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

// Get user's group information

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
    <title>ThesisTrack</title>
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../CSS/student_kanban-progress.css">
</head>
<body>


    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" 
         class="sidebar-avatar" 
         alt="Profile Picture"
         id="sidebarProfileImage" />
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
            <a href="student_feedback.php" class="nav-item" data-tab="feedback">
                <i class="fas fa-comments"></i> Feedback
            </a>
            <a href="student_kanban-progress.php" class="nav-item active" data-tab="kanban">
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

             <!-- Kanban - Progress Tab -->
            <div id="kanban" class="tab-content">
                <div class="card">
                    <h3 style="color: #1a202c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        Chapter Progress Board</h3>
                    <div class="kanban-board">
                        <?php
                        $kanbanColumns = [
                            'to_do' => ['title' => 'To Do', 'chapters' => []],
                            'in_progress' => ['title' => 'In Progress', 'chapters' => []],
                            'under_review' => ['title' => 'Under Review', 'chapters' => []],
                            'completed' => ['title' => 'Completed', 'chapters' => []],
                        ];

                        // Populate kanban columns with actual chapter data
                        foreach ($chapters as $chapter) {
                            $status = $chapter['status'];
                            $chapterTitle = $chapterNames[$chapter['chapter_number']] ?? 'N/A';
                            $cardData = [
                                'title' => "Chapter {$chapter['chapter_number']}: {$chapterTitle}",
                                'description' => htmlspecialchars($chapter['feedback'] ?? 'No specific task description.'),
                                'meta' => '', // This will be dynamically set
                                'priority_class' => '', // This will be dynamically set
                            ];

                            switch ($status) {
                                case 'not_submitted':
                                    $kanbanColumns['to_do']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Start working on this chapter.',
                                        'meta' => 'Due: TBD',
                                        'priority_class' => 'low',
                                    ]);
                                    break;
                                case 'in_progress':
                                    $kanbanColumns['in_progress']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Currently being drafted or revised.',
                                        'meta' => 'Last updated: ' . date('M j', strtotime($chapter['updated_at'] ?? $chapter['created_at'])),
                                        'priority_class' => 'medium',
                                    ]);
                                    break;
                                case 'submitted': // Assuming 'submitted' means 'under review'
                                    $kanbanColumns['under_review']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Submitted for advisor review.',
                                        'meta' => 'Submitted: ' . date('M j', strtotime($chapter['created_at'])),
                                        'priority_class' => 'medium',
                                    ]);
                                    break;
                                case 'needs_revision':
                                    $kanbanColumns['in_progress']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Needs revision based on advisor feedback.',
                                        'meta' => 'Feedback received: ' . date('M j', strtotime($chapter['updated_at'] ?? $chapter['created_at'])),
                                        'priority_class' => 'high',
                                    ]);
                                    break;
                                case 'approved':
                                    $kanbanColumns['completed']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Approved and finalized.',
                                        'meta' => 'Score: ' . htmlspecialchars($chapter['score'] ?? 'N/A') . '/100',
                                        'priority_class' => 'low',
                                    ]);
                                    break;
                            }
                        }

                        // Add placeholder chapters if not all 5 are present in DB
                        for ($i = 1; $i <= 5; $i++) {
                            $found = false;
                            foreach ($chapters as $chapter) {
                                if ($chapter['chapter_number'] == $i) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $chapterTitle = $chapterNames[$i] ?? 'N/A';
                                $kanbanColumns['to_do']['chapters'][] = [
                                    'title' => "Chapter {$i}: {$chapterTitle}",
                                    'description' => 'Not yet started.',
                                    'meta' => 'Due: TBD',
                                    'priority_class' => 'low',
                                ];
                            }
                        }

                        // Display kanban columns
                        foreach ($kanbanColumns as $columnId => $column):
                        ?>
                            <div class="kanban-column">
                                <h3><?php echo htmlspecialchars($column['title']); ?></h3>
                                <div class="kanban-cards">
                                    <?php foreach ($column['chapters'] as $card): ?>
                                        <div class="kanban-card">
                                            <h4><?php echo htmlspecialchars($card['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($card['description']); ?></p>
                                            <div class="card-meta">
                                                <span class="priority <?php echo htmlspecialchars($card['priority_class']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $card['priority_class']))); ?></span>
                                                <span><?php echo htmlspecialchars($card['meta']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

 
        </main>
    </div>


  <script src="../JS/student_kanban-progress.js"></script>
</body>
</html>
