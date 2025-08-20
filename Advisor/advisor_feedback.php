<?php
session_start();
require_once '../db/db.php'; // Assuming this uses MySQLi connection

// Check if the user is logged in and has the 'advisor' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header('Location: advisor_login.php'); 
    exit();
}

// Get the logged-in advisor's ID
$advisor_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Advisor';

// Fetch feedback history for this advisor
$feedback_history = [];
try {
    $sql = "
        SELECT 
            cc.id AS comment_id,
            c.id AS chapter_id,
            c.chapter_number,
            c.chapter_name,
            c.status,
            g.title AS group_title,
            cc.comment,
            cc.created_at AS feedback_date,
            s.first_name,
            s.last_name
        FROM chapter_comments cc
        JOIN chapters c ON cc.chapter_id = c.id
        JOIN groups g ON c.group_id = g.id
        JOIN group_members gm ON g.id = gm.group_id
        JOIN students s ON gm.student_id = s.id
        WHERE g.advisor_id = ?
        AND cc.commenter_type = 'advisor'
        ORDER BY cc.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $advisor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $feedback_history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to fetch feedback history. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_feedback.css">
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
                <div class="sidebar-user"><img src="../images/default-user.png" class="user-avatar" />
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
                <a href="advisor_reviews.php" class="nav-item" data-tab="reviews">
                    <i class="fas fa-tasks"></i> Pending Reviews
                </a>
                <a href="advisor_feedback.php" class="nav-item active" data-tab="feedback">
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
                    <h1><i class="fas fa-comments"></i> Feedback History</h1>
                    <p>Review all feedback provided to your thesis groups.</p>
                </div>

           <!-- Feedback History Tab -->
              <div id="feedback" class="tab-content">
                    <div class="card">
                        <h3>Thesis Group Feedback Review</h3>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>

                        <?php if (empty($feedback_history)): ?>
                            <div class="no-results">
                                <!-- <i class="fas fa-comment-slash"></i> -->
                                <p>No feedback history found.</p>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                <?php foreach ($feedback_history as $feedback): ?>
                                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                            <div>
                                                <h4 style="color: #2d3748;">
                                                    <?php echo htmlspecialchars($feedback['group_title']); ?> - 
                                                    Chapter <?php echo htmlspecialchars($feedback['chapter_number']); ?>: 
                                                    <?php echo htmlspecialchars($feedback['chapter_name']); ?>
                                                </h4>
                                                <p style="color: #4a5568; font-size: 0.9rem;">
                                                    Submitted by: <?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?>
                                                </p>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="background: <?php 
                                                    echo $feedback['status'] === 'approved' ? '#48bb78' : 
                                                           ($feedback['status'] === 'needs_revision' ? '#e53e3e' : '#ed8936'); 
                                                ?>; color: white; padding: 0.25rem 0.6rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $feedback['status'])); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #718096;">
                                                    Reviewed: <?php echo date('M d, Y', strtotime($feedback['feedback_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="background: #f7fafc; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                                            <p style="color: #4a5568; line-height: 1.6;">
                                                <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                                            </p>
                                        </div>
                                        <button class="btn-secondary btn-small" 
                                            onclick="editFeedback(<?php echo $feedback['comment_id']; ?>)">
                                            Edit Feedback
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Edit Feedback Modal -->
                <div id="editModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Feedback</h3>
                            <span class="close" onclick="closeModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editFeedbackForm" action="process_feedback_edit.php" method="POST">
                                <input type="hidden" id="commentId" name="comment_id">
                                <div class="form-group">
                                    <label for="editFeedbackText">Feedback:</label>
                                    <textarea id="editFeedbackText" name="feedback" rows="6" required></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-primary" onclick="submitEdit()">Save Changes</button>
                            <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>


    

     <script src="../JS/advisor_feedback.js"></script>

</body>
</html>
