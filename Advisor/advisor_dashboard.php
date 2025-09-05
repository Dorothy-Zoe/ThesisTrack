<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the 'advisor' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header('Location: advisor_login.php'); 
    exit();
}

// Get advisor information from database
$advisor_id = $_SESSION['user_id'];
$user_name = 'Advisor';
$total_groups = 0;
$completed_chapters = 0;
$pending_reviews = 0;
$average_score = 0;
$group_progress = [];

$profile_picture = '../images/default-user.png'; // Default image

try {
    // Get advisor details including profile picture
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_picture FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor = $stmt->fetch();
    
    $user_name = ($advisor['first_name'] && $advisor['last_name']) ? $advisor['first_name'] . ' ' . $advisor['last_name'] : 'Advisor';
    
    // Check if profile picture exists and is valid
    if (!empty($advisor['profile_picture'])) {
        $relative_path = $advisor['profile_picture'];
        $absolute_path = __DIR__ . '/../' . $relative_path;
        
        if (file_exists($absolute_path) && is_readable($absolute_path)) {
            $profile_picture = '../' . $relative_path;
        } else {
            error_log("Profile image not found: " . $absolute_path);
        }
    }

    
    // Get all groups assigned to this advisor with thesis titles
    $stmt = $pdo->prepare("
        SELECT 
            sg.id,
            sg.group_name,
            sg.section,
            sg.thesis_title,
            COUNT(DISTINCT c.id) AS total_chapters,
            COUNT(DISTINCT CASE WHEN c.status = 'approved' THEN c.id END) AS completed_chapters,
            CASE 
                WHEN COUNT(DISTINCT c.id) = 0 THEN 0
                ELSE ROUND((COUNT(DISTINCT CASE WHEN c.status = 'approved' THEN c.id END) / COUNT(DISTINCT c.id)) * 100)
            END AS progress_percentage
        FROM student_groups sg
        LEFT JOIN chapters c ON sg.id = c.group_id
        WHERE sg.advisor_id = ?
        GROUP BY sg.id, sg.group_name, sg.section, sg.thesis_title
        ORDER BY sg.group_name
    ");
    $stmt->execute([$advisor_id]);
    $group_progress = $stmt->fetchAll();

    // Calculate totals based on the groups
    $total_groups = count($group_progress);
    
    foreach ($group_progress as $group) {
        $completed_chapters += $group['completed_chapters'];
        
        // Get pending reviews for each group
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS pending 
            FROM chapters 
            WHERE group_id = ? AND status IN ('pending', 'under_review')
        ");
        $stmt->execute([$group['id']]);
        $pending_reviews += $stmt->fetch()['pending'];
    }

    // Get average score if available
    $stmt = $pdo->prepare("
        SELECT AVG(score) AS average 
        FROM chapter_reviews 
        WHERE reviewer_id = ? AND reviewer_type = 'advisor'
    ");
    $stmt->execute([$advisor_id]);
    $result = $stmt->fetch();
    $average_score = $result['average'] ? round($result['average'], 2) : 0;

} catch (PDOException $e) {
    error_log("Database error in advisor dashboard: " . $e->getMessage());
}

// Handle profile picture upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    header('Content-Type: application/json');
    
    try {
        $file = $_FILES['profile_picture'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF images are allowed']);
            exit();
        }
        
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Image must be less than 2MB']);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'advisor_' . $advisor_id . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Update database
            $stmt = $pdo->prepare("UPDATE advisors SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$filePath, $advisor_id]);
            
            echo json_encode(['success' => true, 'filePath' => $filePath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        }
    } catch (PDOException $e) {
        error_log("Profile upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_dashboard.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
</head>
<body>
    <div class="app-container">
       <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                <a href="advisor_dashboard.php" class="nav-item active" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item" data-tab="students">
                    <i class="fas fa-user-graduate"></i> Student Management
                </a>
                <a href="advisor_thesis-group.php" class="nav-item" data-tab="students">
                    <i class="fas fa-users-rectangle"></i> Groups Management
                </a>
                <a href="advisor_reviews.php" class="nav-item" data-tab="reviews">
                    <i class="fas fa-tasks"></i> Pending Reviews
                </a>
                <a href="advisor_feedback.php" class="nav-item" data-tab="feedback">
                    <i class="fas fa-comments"></i> Feedback History
                </a>
                <a href="#" id="logoutBtn" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>

                <!-- Logout Confirmation Modal for SIDEBAR-->
                <div id="logoutModal" class="logout-modal" style="display:none;">
                    <div class="logout-modal-content">
                        <h3>Confirm Logout</h3>
                        <p>Are you sure you want to logout?</p>
                        <div class="modal-buttons">
                            <button id="confirmLogout" class="btn btn-danger">Yes, Logout</button>
                            <button id="cancelLogout" class="btn btn-secondary">Cancel</button>
                        </div>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Start HEADER -->
        <div class="content-wrapper">
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
          <a href="#" id="logoutLink" class="dropdown-item">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
           <!-- Logout Confirmation Modal for HEADER -->
        <div id="logoutModal" class="modal" style="display:none;">
        <div class="modal-content logout-modal-content">
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

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?></h1>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                </div>
            </header>

             <!-- Analytics Tab -->
            <div id="analytics" class="tab-content">
                <div class="card">
                    <h3>Progress Analytics</h3>
                    <p style="margin-bottom: 2rem; color: #4a5568;">Overview of thesis progress across all your supervised groups.</p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;"><?php echo $total_groups; ?></div>
                            <div style="opacity: 0.9;">Total Groups</div>
                        </div>
                        <div style="background: linear-gradient(135deg, #48bb78, #38a169); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;"><?php echo $completed_chapters; ?></div>
                            <div style="opacity: 0.9;">Completed Chapters</div>
                        </div>
                        <div style="background: linear-gradient(135deg, #ed8936, #dd6b20); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;"><?php echo $pending_reviews; ?></div>
                            <div style="opacity: 0.9;">Pending Reviews</div>
                        </div>
                        <div style="background: linear-gradient(135deg, #38b2ac, #319795); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;"><?php echo $average_score; ?>%</div>
                            <div style="opacity: 0.9;">Average Score</div>
                        </div>
                    </div>

                     <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
                        <h4 style="color: #2d3748; margin-bottom: 1rem;">Group Progress Overview</h4>
                        <?php if ($total_groups > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                <?php foreach ($group_progress as $group): 
                                    $progress = $group['progress_percentage'] ?? 0;
                                    $color = match(true) {
                                        $progress >= 70 => '#48bb78',
                                        $progress >= 40 => '#ed8936',
                                        default => '#38b2ac'
                                    };
                                    $thesis_title = !empty($group['thesis_title']) ? 
                                        htmlspecialchars($group['thesis_title']) : 
                                        '[No Thesis Title]';
                                ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span style="font-weight: 600;">
                                            <?php echo $thesis_title; ?>, 
                                            <?php echo htmlspecialchars($group['group_name']); ?> 
                                            (<?php echo htmlspecialchars($group['section']); ?>)
                                        </span>
                                        <span style="font-weight: 600; color: <?php echo $color; ?>">
                                            <?php echo $progress; ?>%
                                        </span>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo $color; ?>;">
                                            <?php if ($progress > 5): ?>
                                                <?php echo $progress; ?>%
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #718096;">
                                        <span><?php echo $group['completed_chapters']; ?> of <?php echo $group['total_chapters']; ?> chapters completed</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-groups-message">
                                <i class="fas fa-users-slash" style="font-size: 2rem; color: #a0aec0; margin-bottom: 1rem;"></i>
                                <h4 style="color: #4a5568;">No Groups Assigned</h4>
                                <p style="color: #718096;">You currently don't have any thesis groups assigned to you.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            </div>
        </main>
    </div>

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

    <script src="../JS/advisor_dashboard.js"></script>
</body>
</html>