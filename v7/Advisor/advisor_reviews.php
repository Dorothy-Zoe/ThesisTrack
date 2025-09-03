<?php
session_start();
require_once '../db/db.php'; // Assuming this file exists and handles database connection

// Check if the user is logged in and has the 'advisor' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header('Location: advisor_login.php'); 
    exit();
}

// Get the logged-in advisor's ID
$advisor_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Advisor'; // Default to 'Advisor' if name is not set

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

} catch (PDOException $e) {
    // Log the error and use default values
    error_log("Database error fetching advisor details: " . $e->getMessage());
    $user_name = 'Advisor';
    $profile_picture = '../images/default-user.png';
}


// Fetch pending chapters for this advisor
$pending_chapters = [];
try {
    // Corrected SQL with proper parentheses and using mysqli prepared statements
    $sql = "
        SELECT c.*, g.title AS group_title, s.first_name, s.last_name 
        FROM chapters c
        JOIN groups g ON c.group_id = g.id
        JOIN group_members gm ON g.id = gm.group_id
        JOIN students s ON gm.student_id = s.id
        WHERE g.advisor_id = ? 
        AND (c.status = 'pending' OR c.status = 'under_review')
        GROUP BY c.id
        ORDER BY c.upload_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $advisor_id); // "i" for integer
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_chapters = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // Log error and show a user-friendly message
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to fetch pending reviews. Please try again later.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_reviews.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                <!-- ✅ FIXED: Changed all href links to data-tab system -->
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
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
                <a href="advisor_reviews.php" class="nav-item active" data-tab="reviews">
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
        <!-- End Sidebar -->


        <!-- Start HEADER -->
        <div class="content-wrapper">
         <header class="blank-header">
             <div class="topbar-left">
    </div>
                <div class="topbar-right">
                <button class="topbar-icon" title="Notifications">
                <i class="fas fa-bell"></i></button>
                <div class="user-info dropdown">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar" class="user-avatar" id="userAvatar" tabindex="0" />
        <div class="dropdown-menu" id="userDropdown">
          <a href="#" class="dropdown-item">
            <i class="fas fa-cog"></i> Settings
          </a>
          <a href="#" id="logoutLink" class="dropdown-item">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
           <!-- Logout Confirmation Modal for HEADER -->
        <div id="logoutModal" class="modal" style="display:none;">
        <div class="modal-content logout-modal-content"> <!-- for logout -->

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
        <!-- End HEADER -->

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-tasks"></i> Pending Reviews</h1>
                    <p>Chapters awaiting your review and feedback</p>
                </div>
                <!-- End of Page Title -->

             <!-- Pending Reviews Tab -->
            <div id="reviews" class="tab-content">
                    <div class="card">
                        <h3>Pending Chapter Reviews</h3>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>

                        <?php if (empty($pending_chapters)): ?>
                            <div class="no-results">
                                <!-- <i class="fas fa-check-circle"></i> -->
                                <p>No pending reviews at this time.</p>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($pending_chapters as $chapter): ?>
                                    <div style="background: #f7fafc; border-radius: 8px; padding: 1.5rem; border-left: 4px solid #ed8936;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                            <div>
                                                <h4 style="color: #2d3748; margin-bottom: 0.5rem;">
                                                    <?php echo htmlspecialchars($chapter['group_title']); ?> - 
                                                    Chapter <?php echo htmlspecialchars($chapter['chapter_number']); ?>: 
                                                    <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                                </h4>
                                                <p style="color: #4a5568; font-size: 0.9rem;">
                                                    Submitted by: <?php echo htmlspecialchars($chapter['first_name'] . ' ' . $chapter['last_name']); ?>
                                                </p>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="background: #fef5e7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $chapter['status'])); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #718096;">
                                                    Submitted: <?php echo date('M d, Y', strtotime($chapter['upload_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn-primary btn-small" 
                                                onclick="reviewChapter(<?php echo $chapter['id']; ?>, '<?php echo htmlspecialchars($chapter['chapter_name']); ?>')">
                                                Review Now
                                            </button>
                                            <button class="btn-secondary btn-small" 
                                                onclick="viewChapterFile(<?php echo $chapter['id']; ?>)">
                                                View File
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Modal -->
                <div id="reviewModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="reviewTitle">Review Chapter</h3>
                            <span class="close" onclick="closeModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="reviewForm" action="process_review.php" method="POST">
                                <input type="hidden" id="chapterId" name="chapter_id">
                                <div class="form-group">
                                    <label for="scoreInput">Score (0-100):</label>
                                    <input type="number" id="scoreInput" name="score" min="0" max="100" placeholder="Enter score">
                                </div>
                                <div class="form-group">
                                    <label for="statusSelect">Status:</label>
                                    <select id="statusSelect" name="status">
                                        <option value="approved">Approved</option>
                                        <option value="needs_revision">Needs Revision</option>
                                        <option value="under_review">Under Review</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="feedbackText">Feedback:</label>
                                    <textarea id="feedbackText" name="feedback" rows="6" placeholder="Provide detailed feedback..."></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-primary" onclick="submitReview()">Submit Review</button>
                            <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>


    

     <script src="../JS/advisor_reviews.js"></script>

</body>
</html>
