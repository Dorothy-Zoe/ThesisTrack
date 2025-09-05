<?php
session_start();
require_once '../db/db.php';

// Check coordinator session
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// In your coordinator session verification code:
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, profile_picture FROM coordinators WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $coordinator = $stmt->fetch();
    
    if (!$coordinator) {
        header('Location: ../login.php');
        exit();
    }
    
    $user_name = $coordinator['name'];
    
    // Check if profile picture exists
    $profile_picture = '../images/default-user.png'; // Default fallback
    if (!empty($coordinator['profile_picture'])) {
        $imagePath = '../uploads/profile_pictures/' . $coordinator['profile_picture'];
        if (file_exists($imagePath)) {
            $profile_picture = $imagePath;
        } else {
            // Clean up invalid reference
            $updateStmt = $pdo->prepare("UPDATE coordinators SET profile_picture = NULL WHERE id = ?");
            $updateStmt->execute([$_SESSION['user_id']]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ../login.php');
    exit();
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate weeks separately
    $weeks = floor($diff->d / 7);
    $days = $diff->d % 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    // Create a custom object with all time units
    $time_units = (object)[
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s
    ];

    foreach ($string as $k => &$v) {
        if ($time_units->$k > 0) {
            $v = $time_units->$k . ' ' . $v . ($time_units->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Initialize default values
$total_sections = 0;
$total_advisors = 0;
$total_groups = 0;
$total_students = 0;
$chapters_completed = 0;
$pending_reviews = 0;
$program_stats = [];
$recent_activity = [];

// Fetch statistics from database
try {
    // Total sections (unique section-course combinations from student_groups)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT CONCAT(course, '-', section)) AS total_sections FROM advisor_sections");
    $total_sections = $stmt->fetch()['total_sections'] ?? 0;

    // Active advisors
    $stmt = $pdo->query("SELECT COUNT(*) AS total_advisors FROM advisors WHERE status = 'active'");
    $total_advisors = $stmt->fetch()['total_advisors'] ?? 0;

    // Active thesis groups
    $stmt = $pdo->query("SELECT COUNT(*) AS total_groups FROM student_groups WHERE status = 'active'");
    $total_groups = $stmt->fetch()['total_groups'] ?? 0;

    // Active students
    $stmt = $pdo->query("SELECT COUNT(*) AS total_students FROM students WHERE status = 'active'");
    $total_students = $stmt->fetch()['total_students'] ?? 0;

    // Chapters completed (approved status)
    $stmt = $pdo->query("SELECT COUNT(*) AS chapters_completed FROM chapters WHERE status = 'approved'");
    $chapters_completed = $stmt->fetch()['chapters_completed'] ?? 0;

    // Pending reviews
    $stmt = $pdo->query("SELECT COUNT(*) AS pending_reviews FROM chapters WHERE status IN ('pending', 'under_review')");
    $pending_reviews = $stmt->fetch()['pending_reviews'] ?? 0;

    // Program statistics - now using student_groups as the source of truth
    $stmt = $pdo->query("
    SELECT 
        sg.course,
        COUNT(DISTINCT sg.id) AS group_count,
        COUNT(DISTINCT sg.advisor_id) AS advisor_count,
        COUNT(DISTINCT gm.student_id) AS student_count,
        GROUP_CONCAT(DISTINCT sg.section ORDER BY sg.section SEPARATOR ', ') AS sections
    FROM student_groups sg
    LEFT JOIN groups g ON g.id = sg.group_id
    LEFT JOIN group_members gm ON gm.group_id = g.id
    WHERE sg.status = 'active'
    GROUP BY sg.course
");

    
    while ($row = $stmt->fetch()) {
        $program_stats[$row['course']] = $row;
    }

    // Recent activity - improved queries to match your database schema
    $stmt = $pdo->query("
        (SELECT 
            'chapter_review' AS type,
            CONCAT(a.first_name, ' ', a.last_name) AS actor,
            CONCAT('reviewed Chapter ', c.chapter_number, ' for ', sg.group_name) AS action,
            c.updated_at AS timestamp
        FROM chapters c
        JOIN student_groups sg ON c.group_id = sg.id
        JOIN advisors a ON sg.advisor_id = a.id
        WHERE c.status = 'approved' AND c.reviewer_type = 'advisor'
        ORDER BY c.updated_at DESC
        LIMIT 1)
        
        UNION ALL
        
        (SELECT 
            'chapter_submission' AS type,
            sg.group_name AS actor,
            CONCAT('submitted Chapter ', c.chapter_number, ' (', c.chapter_name, ') for review') AS action,
            c.upload_date AS timestamp
        FROM chapters c
        JOIN student_groups sg ON c.group_id = sg.id
        WHERE c.status IN ('pending', 'under_review')
        ORDER BY c.upload_date DESC
        LIMIT 1)
        
        UNION ALL
        
        (SELECT 
            'chapter_completion' AS type,
            sg.group_name AS actor,
            CONCAT('completed Chapter ', c.chapter_number, ' (', c.chapter_name, ')') AS action,
            c.updated_at AS timestamp
        FROM chapters c
        JOIN student_groups sg ON c.group_id = sg.id
        WHERE c.status = 'approved'
        ORDER BY c.updated_at DESC
        LIMIT 1)
        
        UNION ALL
        
        (SELECT 
            'advisor_assignment' AS type,
            CONCAT(a.first_name, ' ', a.last_name) AS actor,
            CONCAT('assigned to ', asec.section, ' (', asec.course, ')') AS action,
            asec.created_at AS timestamp
        FROM advisor_sections asec
        JOIN advisors a ON asec.advisor_id = a.id
        ORDER BY asec.created_at DESC
        LIMIT 1)
        
        ORDER BY timestamp DESC
        LIMIT 4
    ");
    $recent_activity = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in dashboard: " . $e->getMessage());
    // Fallback to default values if there's an error
    $total_sections = 0;
    $total_advisors = 0;
    $total_groups = 0;
    $total_students = 0;
    $chapters_completed = 0;
    $pending_reviews = 0;
    $program_stats = [];
    $recent_activity = [];
}

function getProfilePicture($user_id) {
    global $pdo;
    
    try {
        // Query the coordinators table instead
        $stmt = $pdo->prepare("SELECT profile_picture FROM coordinators WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        // Check if profile picture exists in database and filesystem
        if ($result && !empty($result['profile_picture'])) {
            $imagePath = '../uploads/profile_pictures/' . $result['profile_picture'];
            
            // Verify the file actually exists
            if (file_exists($imagePath)) {
                return $imagePath;
            } else {
                // Clean up invalid reference
                $updateStmt = $pdo->prepare("UPDATE coordinators SET profile_picture = NULL WHERE id = ?");
                $updateStmt->execute([$user_id]);
            }
        }
    } catch (PDOException $e) {
        error_log("Database error fetching profile picture: " . $e->getMessage());
    }
    
    // Default fallback image
    return '../images/default-user.png';
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/coordinator_dashboard.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
</head>
<body>
    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
               <div class="sidebar-user" onclick="openUploadModal()">
    <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
         class="sidebar-avatar" 
         alt="User Avatar" 
         id="currentProfilePicture" />
    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
</div>

                <span class="role-badge">Research Coordinator</span>
            </div>
            <!-- Sidebar Navigation -->
            <nav class="sidebar-nav">
                <a href="coordinator_dashboard.php" class="nav-item active">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="coordinator_sec-advisors.php" class="nav-item">
                    <i class="fas fa-school"></i> Sections & Advisors
                </a>
                <a href="coordinator_thesis-groups.php" class="nav-item">
                    <i class="fas fa-users"></i> Thesis Groups
                </a>
                <a href="coordinator_advisor-mngt.php" class="nav-item">
                    <i class="fas fa-chalkboard-teacher"></i> Advisor Management
                </a>
                <a href="#" id="logoutBtn" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>

                <!-- Logout Confirmation Modal for SIDEBAR -->
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
        <!-- End Sidebar -->

        <!-- Start HEADER -->
        <div class="content-wrapper">
            <header class="blank-header">
                <div class="topbar-left">
                </div>
                <div class="topbar-right">
                    <button class="topbar-icon" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    <div class="user-info dropdown">
                        <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>?t=<?php echo time(); ?>" 
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
                        </div>
                    </div>
                </div>
            </header>
            <!-- End HEADER -->

            <!-- Main Content -->
            <main class="main-content">
                <!-- Message container for notifications -->
                <div id="messageContainer"></div>

                <header class="main-header">
                    <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
                    <div class="header-actions">
                        <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                    </div>
                </header>

                <!-- Overview Tab -->
                <div id="overview" class="tab-content active">
                    <div class="overview-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo htmlspecialchars($total_sections); ?></div>
                            <div class="stat-label">CICT Sections</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo htmlspecialchars($total_advisors); ?></div>
                            <div class="stat-label">Active Advisors</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo htmlspecialchars($total_groups); ?></div>
                            <div class="stat-label">Thesis Groups</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo htmlspecialchars($total_students); ?></div>
                            <div class="stat-label">CICT Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo htmlspecialchars($chapters_completed); ?></div>
                            <div class="stat-label">Chapters Completed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo htmlspecialchars($pending_reviews); ?></div>
                            <div class="stat-label">Pending Reviews</div>
                        </div>
                    </div>

                    <div class="card">
                        <h3>CICT Program Group Summary</h3>
                        <?php if (!empty($program_stats)): ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                                <?php foreach ($program_stats as $course => $stats): ?>
                                    <div class="cict-section">
                                        <h4><?php echo htmlspecialchars($course); ?></h4>
                                        <div class="course-badge <?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $course))); ?>">
                                            <?php echo htmlspecialchars($course); ?> Program
                                        </div>
                                        <p style="font-size: 1.5rem; font-weight: bold; color: #805ad5; margin: 0.5rem 0;">
                                            <?php echo htmlspecialchars($stats['group_count']); ?> Groups
                                        </p>
                                        <p style="color: #4a5568; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($stats['advisor_count']); ?> Advisors • 
                                            <?php echo htmlspecialchars($stats['student_count']); ?> Students
                                        </p>
                                        <p style="color: #4a5568; font-size: 0.9rem; margin-top: 0.5rem;">
                                            Sections: <?php echo htmlspecialchars($stats['sections']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No program data available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h3>Recent CICT System Activity</h3>
                        <?php if (!empty($recent_activity)): ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #e2e8f0;">
                                        <div style="font-size: 1.2rem;">
                                            <?php 
                                            switch ($activity['type']) {
                                                case 'chapter_review':
                                                    echo '<i class="fas fa-chalkboard-teacher" style="color: #4c51bf;"></i>';
                                                    break;
                                                case 'chapter_submission':
                                                    echo '<i class="fas fa-file-alt" style="color: #38a169;"></i>';
                                                    break;
                                                case 'chapter_completion':
                                                    echo '<i class="fas fa-check-circle" style="color: #9f7aea;"></i>';
                                                    break;
                                                case 'advisor_assignment':
                                                    echo '<i class="fas fa-users" style="color: #dd6b20;"></i>';
                                                    break;
                                                default:
                                                    echo '<i class="fas fa-info-circle" style="color: #718096;"></i>';
                                            }
                                            ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #2d3748;">
                                                <?php echo htmlspecialchars($activity['actor'] . ' ' . $activity['action']); ?>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #718096;">
                                                <?php echo time_elapsed_string($activity['timestamp']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No recent activity to display.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
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
   
    <script src="../JS/coordinator_dashboard.js"></script>
    
</body>
</html>