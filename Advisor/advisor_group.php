<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the 'advisor' role
if (!isset($_SESSION['user_id'])) {
    header('Location: advisor_login.php'); 
    exit();
}

// Get the logged-in advisor's ID
$advisor_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Advisor';

// Fetch all groups assigned to this advisor with course from student_groups
$groups_query = "SELECT g.id, g.title, g.section, g.status, sg.course, sg.thesis_title
                 FROM groups g
                 LEFT JOIN student_groups sg ON sg.group_id = g.id
                 WHERE g.advisor_id = ?
                 ORDER BY g.section, g.title";
$stmt = $conn->prepare($groups_query);
$stmt->bind_param("i", $advisor_id);
$stmt->execute();
$groups_result = $stmt->get_result();


// We'll store all group data in this array
$groups_data = [];

while ($group = $groups_result->fetch_assoc()) {
    $group_id = $group['id'];
    
    // Get members for this group
    $members_query = "SELECT s.id, s.first_name, s.last_name, gm.role_in_group
                      FROM group_members gm
                      JOIN students s ON gm.student_id = s.id
                      WHERE gm.group_id = ?
                      ORDER BY 
                    CASE gm.role_in_group
                      WHEN 'leader' THEN 0
                      ELSE 1
                    END,
                    s.last_name";
    $stmt_m = $conn->prepare($members_query);
    $stmt_m->bind_param("i", $group_id);
    $stmt_m->execute();
    $members_result = $stmt_m->get_result();
    
    $members = [];
    while ($member = $members_result->fetch_assoc()) {
        $members[] = $member;
    }
    
    // Get chapters for this group
    $chapters_query = "SELECT id, chapter_number, chapter_name, status, 
                       (SELECT COUNT(*) FROM chapter_comments 
                        WHERE chapter_id = chapters.id AND is_resolved = 0) as unresolved_comments
                       FROM chapters 
                       WHERE group_id = ?
                       ORDER BY chapter_number";
    $stmt_c = $conn->prepare($chapters_query);
    $stmt_c->bind_param("i", $group_id);
    $stmt_c->execute();
    $chapters_result = $stmt_c->get_result();
    
    $chapters = [];
    while ($chapter = $chapters_result->fetch_assoc()) {
        $chapters[] = $chapter;
    }
    
    // Store all data for this group
    $groups_data[] = [
        'group_info' => $group,
        'members' => $members,
        'chapters' => $chapters
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_group.css">
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
                <div class="sidebar-user"><img src="../images/default-user.png" class="image-sidebar-avatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item active" data-tab="groups">
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
        <!-- End Sidebar -->

        <!-- Start HEADER -->
        <div class="content-wrapper">
            <header class="blank-header">
                <div class="topbar-left"></div>
                <div class="topbar-right">
                    <button class="topbar-icon" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    <div class="user-info dropdown">
                        <img src="../images/default-user.png" alt="User Avatar" class="user-avatar" id="userAvatar" tabindex="0" />
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
            <!-- End HEADER -->

            <!-- Main Content -->
            <main class="main-content">
                <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-users"></i> Groups Overview</h1>
                    <p>Monitor and review thesis progress for BSCS and BSIS student groups.</p>
                </div>
                <!-- End of Page Title -->

                <!-- Groups Tab -->
                <div id="groups" class="tab-content active">
                    <div class="card">
                        <h3>CICT Thesis Groups Under Your Supervision</h3>
                        
                        <div class="groups-grid">
                            <?php if (empty($groups_data)): ?>
                                <div class="no-groups-message">
                                    <i class="fas fa-users-slash"></i>
                                    <p>You don't have any assigned groups yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($groups_data as $group): ?>
                                <?php 
                                    $group_info = $group['group_info'];
                                    $members = $group['members'];
                                    $chapters = $group['chapters'];
                                    
                                    // Determine course badge color
                                    $badge_class = ($group_info['course'] == 'BSCS') ? 'course-badge' : 'course-badge is-badge';
                                ?>
                                
                                <div class="group-card">
                                    <div class="group-header">
                                        <div class="group-title"><?php echo $group_info['title']; ?> - <?php echo $group_info['section']; ?></div>
                                        <div class="<?php echo $badge_class; ?>"><?php echo $group_info['course']; ?></div>
                                    </div>
                                    <div class="thesis-title"><?php echo htmlspecialchars($group_info['thesis_title']); ?></div>
                                    <div class="group-members">
                                        <h4>Group Members:</h4>
                                        <div class="members-list">
                                            <?php foreach ($members as $member): ?>
                                            <div>• <?php echo htmlspecialchars($member['first_name'] . ' ' . htmlspecialchars($member['last_name'])); ?>
                                                <?php if ($member['role_in_group'] == 'leader'): ?>
                                                <span class="leader-badge">(Leader)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="chapter-progress">
                                        <h4>Chapter Progress:</h4>
                                        <div class="chapter-indicators">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php 
                                                    $chapter_status = 'pending';
                                                    $chapter_title = 'Chapter ' . $i . ': Not Started';
                                                    $chapter_score = '';
                                                    
                                                    foreach ($chapters as $chapter) {
                                                        if ($chapter['chapter_number'] == $i) {
                                                            $chapter_status = str_replace('_', '-', $chapter['status']);
                                                            $chapter_title = 'Chapter ' . $i . ': ' . ucfirst(str_replace('_', ' ', $chapter['status']));
                                                            if ($chapter['unresolved_comments'] > 0) {
                                                                $chapter_title .= ' (' . $chapter['unresolved_comments'] . ' unresolved comments)';
                                                            }
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                <div class="chapter-indicator <?php echo $chapter_status; ?>" 
                                                     title="<?php echo $chapter_title; ?>"><?php echo $i; ?></div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="group-actions">
                                        <button class="btn-expand" onclick="toggleGroupDetails('group<?php echo $group_info['id']; ?>')">View Details</button>
                                        <?php 
                                            // Find the first chapter that needs review
                                            $chapter_to_review = null;
                                            foreach ($chapters as $chapter) {
                                                if ($chapter['status'] == 'under_review' || $chapter['status'] == 'needs_revision') {
                                                    $chapter_to_review = $chapter;
                                                    break;
                                                }
                                            }
                                            
                                            if ($chapter_to_review): 
                                        ?>
                                            <button class="btn-primary btn-small" 
                                                    onclick="reviewChapter('group<?php echo $group_info['id']; ?>', 'chapter<?php echo $chapter_to_review['chapter_number']; ?>')">
                                                Review Ch.<?php echo $chapter_to_review['chapter_number']; ?>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-secondary btn-small">No Pending Reviews</button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="group-details" id="group<?php echo $group_info['id']; ?>-details">
                                        <?php foreach ($chapters as $chapter): ?>
                                        <div class="chapter-detail">
                                            <div class="chapter-detail-header">
                                                <span class="chapter-name">Chapter <?php echo $chapter['chapter_number']; ?>: <?php echo htmlspecialchars($chapter['chapter_name']); ?></span>
                                                <span class="chapter-score 
                                                    <?php 
                                                        if ($chapter['status'] == 'approved') echo 'completed';
                                                        elseif ($chapter['status'] == 'needs_revision') echo 'needs-revision';
                                                        else echo 'in-progress';
                                                    ?>">
                                                    <?php 
                                                        echo ucfirst(str_replace('_', ' ', $chapter['status']));
                                                        if ($chapter['unresolved_comments'] > 0) {
                                                            echo ' (' . $chapter['unresolved_comments'] . ' comments)';
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                            <?php 
                                                // Get the latest comment for this chapter
                                                $comment_query = "SELECT cc.comment, cc.created_at, 
                                                                 CASE 
                                                                     WHEN cc.commenter_type = 'advisor' THEN CONCAT(a.first_name, ' ', a.last_name)
                                                                     WHEN cc.commenter_type = 'coordinator' THEN CONCAT(c.first_name, ' ', c.last_name)
                                                                     ELSE CONCAT(s.first_name, ' ', s.last_name)
                                                                 END as commenter_name,
                                                                 cc.commenter_type
                                                                 FROM chapter_comments cc
                                                                 LEFT JOIN advisors a ON cc.commenter_type = 'advisor' AND cc.commenter_id = a.id
                                                                 LEFT JOIN coordinators c ON cc.commenter_type = 'coordinator' AND cc.commenter_id = c.id
                                                                 LEFT JOIN students s ON cc.commenter_type = 'student' AND cc.commenter_id = s.id
                                                                 WHERE cc.chapter_id = ?
                                                                 ORDER BY cc.created_at DESC
                                                                 LIMIT 1";
                                                $stmt_comment = $conn->prepare($comment_query);
                                                $stmt_comment->bind_param("i", $chapter['id']);
                                                $stmt_comment->execute();
                                                $comment_result = $stmt_comment->get_result();
                                                $latest_comment = $comment_result->fetch_assoc();
                                            ?>
                                            <?php if ($latest_comment): ?>
                                            <div class="chapter-feedback">
                                                <strong><?php echo htmlspecialchars($latest_comment['commenter_name']); ?> (<?php echo ucfirst($latest_comment['commenter_type']); ?>):</strong>
                                                <?php echo htmlspecialchars($latest_comment['comment']); ?>
                                                <div class="comment-date"><?php echo date('M j, Y', strtotime($latest_comment['created_at'])); ?></div>
                                            </div>
                                            <?php else: ?>
                                            <div class="chapter-feedback">No feedback yet.</div>
                                            <?php endif; ?>
                                            <div class="chapter-actions">
                                                <button class="btn-secondary btn-small" 
                                                        onclick="viewChapterFile('group<?php echo $group_info['id']; ?>', 'chapter<?php echo $chapter['chapter_number']; ?>')">
                                                    View File
                                                </button>
                                                <button class="btn-secondary btn-small" 
                                                        onclick="editFeedback('group<?php echo $group_info['id']; ?>', 'chapter<?php echo $chapter['chapter_number']; ?>')">
                                                    <?php echo ($latest_comment) ? 'Edit Feedback' : 'Add Feedback'; ?>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
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
                            <div class="form-group">
                                <label for="scoreInput">Score (0-100):</label>
                                <input type="number" id="scoreInput" min="0" max="100" placeholder="Enter score">
                            </div>
                            <div class="form-group">
                                <label for="statusSelect">Status:</label>
                                <select id="statusSelect">
                                    <option value="approved">Approved</option>
                                    <option value="needs_revision">Needs Revision</option>
                                    <option value="in_progress">In Progress</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="feedbackText">Feedback:</label>
                                <textarea id="feedbackText" rows="6" placeholder="Provide detailed feedback..."></textarea>
                            </div>
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

    <script src="../JS/advisor_group.js"></script>
    
</body>
</html>