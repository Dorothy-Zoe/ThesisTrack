<?php
session_start();
require_once '../db/db.php';

if (isset($_POST['action']) && $_POST['action'] === 'delete_upload') {
    $chapter_number = $_POST['chapter_number'];
    $version = $_POST['version'];
    $group_id = $_POST['group_id'];
    
    try {
        // Get file path before deletion
        $stmt = $pdo->prepare("SELECT file_path FROM chapters WHERE group_id = ? AND chapter_number = ? AND version = ?");
        $stmt->execute([$group_id, $chapter_number, $version]);
        $file = $stmt->fetch();
        
        if ($file) {
            // Delete file from filesystem
            $file_path = dirname(__DIR__) . '/' . $file['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $deleteStmt = $pdo->prepare("DELETE FROM chapters WHERE group_id = ? AND chapter_number = ? AND version = ?");
            $deleteStmt->execute([$group_id, $chapter_number, $version]);
            
            // Update is_current flag for remaining versions
            $updateStmt = $pdo->prepare("
                UPDATE chapters 
                SET is_current = 1 
                WHERE group_id = ? AND chapter_number = ? AND version = (
                    SELECT MAX(version) FROM (
                        SELECT version FROM chapters WHERE group_id = ? AND chapter_number = ?
                    ) as temp
                )
            ");
            $updateStmt->execute([$group_id, $chapter_number, $group_id, $chapter_number]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../student_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_section = $_SESSION['section'];

// Get student's profile picture
$profile_picture = '../images/default-user.png';
try {
    $stmt = $pdo->prepare("SELECT profile_picture FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    if (!empty($student['profile_picture'])) {
        $relative_path = $student['profile_picture'];
        $absolute_path = dirname(__DIR__) . '/' . $relative_path;
        
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
        SELECT *, 
               (SELECT COUNT(*) FROM chapters c2 WHERE c2.group_id = chapters.group_id AND c2.chapter_number = chapters.chapter_number) as total_versions
        FROM chapters
        WHERE group_id = ? AND is_current = 1
        ORDER BY chapter_number
    ");
    $chaptersQuery->execute([$userGroup['id']]);
    $chapters = $chaptersQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Get upload history for each chapter
$uploadHistory = [];
if ($userGroup && isset($pdo)) {
    $historyQuery = $pdo->prepare("
        SELECT chapter_number, filename, original_filename, upload_date, version, file_path
        FROM chapters 
        WHERE group_id = ? 
        ORDER BY chapter_number, upload_date DESC
    ");
    $historyQuery->execute([$userGroup['id']]);
    $allUploads = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by chapter number
    foreach ($allUploads as $upload) {
        $uploadHistory[$upload['chapter_number']][] = $upload;
    }
}

// Chapter names
$chapterNames = [
    1 => 'Introduction',
    2 => 'Review of Related Literature',
    3 => 'Methodology', 
    4 => 'Results and Discussion',
    5 => 'Summary, Conclusion, and Recommendation'
];

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
    <link rel="stylesheet" href="../CSS/student_chap-upload.css">
</head>
<body>

    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <!-- Improved sidebar header typography and spacing -->
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"> 
                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                         class="sidebar-avatar" 
                         alt="Profile Picture"
                         id="sidebarProfileImage" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
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
        </nav>

                    <!-- Logout Confirmation Modal for SIDEBAR -->
            <div id="logoutModal" class="modal">
            <div class="logoutmodal-content">
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
        </div>
      </div>
    </div>
        </header>
        <!-- End Header -->

        <main class="main-content">
            <!-- Chapter Uploads Tab -->
            <div id="upload" class="tab-content">
                <!-- Enhanced main content card with better typography -->
                <div class="card main-card">
                    <div class="card-header">
                        <h2 class="card-title">Chapter-Based Thesis Uploads</h2>
                        <p class="card-description">Upload each chapter individually for evaluation and advisor review.</p>
                    </div>

                    <div class="chapter-uploads">
                        <?php
                        foreach ($chapterNames as $chapterNum => $title):
                            $currentChapterStatus = 'pending';
                            $currentChapterFile = null;
                            $currentChapterScore = null;
                            $currentChapterFeedback = null;
                            $currentChapterVersion = 1;
                            $totalVersions = 0;

                            // Override with actual data from DB if available
                            foreach ($chapters as $dbChapter) {
                                if ($dbChapter['chapter_number'] == $chapterNum) {
                                    $currentChapterStatus = $dbChapter['status'];
                                    $currentChapterFile = $dbChapter['original_filename'];
                                    $currentChapterScore = $dbChapter['score'];
                                    $currentChapterFeedback = $dbChapter['feedback'];
                                    $currentChapterVersion = $dbChapter['version'];
                                    $totalVersions = $dbChapter['total_versions'];
                                    break;
                                }
                            }

                            $displayScore = $currentChapterScore ?? null;
                            $displayIssues = $currentChapterFeedback ?? null;
                            $displayFile = $currentChapterFile ?? null;
                        ?>
                            <div class="chapter-card">
                                <div class="chapter-header">
                                    <div class="chapter-title">
                                        <span class="chapter-number">Chapter <?php echo $chapterNum; ?></span>
                                        <span class="chapter-name"><?php echo htmlspecialchars($title); ?></span>
                                        <?php if ($totalVersions > 1): ?>
                                            <span class="version-indicator">v<?php echo $currentChapterVersion; ?> (<?php echo $totalVersions; ?> uploads)</span>
                                        <?php elseif ($totalVersions == 1): ?>
                                            <span class="version-indicator">v<?php echo $currentChapterVersion; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $statusText = 'Pending';
                                    $statusClass = 'pending';
                                    
                                    switch ($currentChapterStatus) {
                                        case 'uploaded':
                                            $statusText = 'Uploaded';
                                            $statusClass = 'uploaded';
                                            break;
                                        case 'pending':
                                            $statusText = 'Pending';
                                            $statusClass = 'pending';
                                            break;
                                        case 'under_review':
                                            $statusText = 'Under Review';
                                            $statusClass = 'under_review';
                                            break;
                                        case 'approved':
                                            $statusText = 'Approved';
                                            $statusClass = 'approved';
                                            break;
                                        case 'needs_revision':
                                            $statusText = 'Needs Revision';
                                            $statusClass = 'needs_revision';
                                            break;
                                        case 'in_progress':
                                            $statusText = 'In Progress';
                                            $statusClass = 'in_progress';
                                            break;
                                        case 'not_submitted':
                                            $statusText = 'Not Submitted';
                                            $statusClass = 'not_submitted';
                                            break;
                                        default:
                                            $statusText = 'Pending';
                                            $statusClass = 'pending';
                                    }
                                    ?>
                                    <div class="chapter-status status-badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusText); ?></div>
                                </div>
                               <div class="upload-area" onclick="triggerFileUpload('chapter<?php echo $chapterNum; ?>')">
                                    <div class="upload-icon">
                                        <?php if ($displayFile): ?>
                                            <i class="fas fa-file-alt"></i> 
                                        <?php else: ?>
                                            <i class="fas fa-cloud-upload-alt"></i> 
                                        <?php endif; ?>
                                    </div>
                                    <div class="upload-text">
                                        <?php if ($displayFile): ?>
                                            <p class="file-name"><?php echo htmlspecialchars($displayFile); ?></p>
                                            <p class="upload-hint">Click to replace or drag new file</p>
                                        <?php else: ?>
                                            <p class="upload-prompt">Click to upload or drag and drop</p>
                                            <p class="upload-hint">PDF, DOC, DOCX files only (Max 10MB)</p>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" id="chapter<?php echo $chapterNum; ?>" accept=".pdf,.doc,.docx" style="display: none;">
                                    </div>

                                <?php if ($displayScore !== null || $displayIssues !== null): ?>
                                    <div class="ai-validation">
                                        <div class="validation-header">
                                            <i class="fas fa-robot"></i>
                                            <span>AI Evaluation Results</span>
                                        </div>
                                        <?php if ($displayScore !== null): ?>
                                            <div class="validation-score">
                                                <span class="score-label">Evaluation Score:</span>
                                                <span class="score-badge score-<?php echo ($displayScore >= 80) ? 'high' : (($displayScore >= 60) ? 'medium' : 'low'); ?>"><?php echo htmlspecialchars($displayScore); ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($displayIssues !== null): ?>
                                            <div class="validation-issues">
                                                <p><?php echo htmlspecialchars($displayIssues); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <button class="btn-secondary btn-small" onclick="viewValidationReport('chapter<?php echo $chapterNum; ?>')">
                                            <i class="fas fa-chart-line"></i> View Detailed Report
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Upload History Section -->
                <div class="history-section">
                    <div class="history-header">
                        <h2 class="section-title">
                            <i class="fas fa-history"></i>
                            Upload History
                        </h2>
                        <?php if (!empty($uploadHistory)): ?>
                        <div class="history-filters">
                            <div class="filter-group">
                                <label for="searchHistory">Search Files</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchHistory" class="filter-input" placeholder="Search files..." onkeyup="filterUploadHistory()">
                                </div>
                            </div>
                            <div class="filter-group">
                                <label for="chapterFilter">Filter by Chapter</label>
                                <div class="select-wrapper">
                                    <select id="chapterFilter" class="filter-select" onchange="filterUploadHistory()">
                                        <option value="all">All Chapters</option>
                                        <?php foreach ($chapterNames as $num => $name): ?>
                                            <?php if (isset($uploadHistory[$num])): ?>
                                                <option value="<?php echo $num; ?>">Chapter <?php echo $num; ?>: <?php echo htmlspecialchars($name); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($uploadHistory)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>No upload history available yet</h3>
                            <p>Upload your first chapter to see the history here.</p>
                        </div>
                    <?php else: ?>
                        <!-- Enhanced history summary stats -->
                        <div class="history-summary">
                            <div class="summary-stats">
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo array_sum(array_map('count', $uploadHistory)); ?></div>
                                        <div class="stat-label">Total Uploads</div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo count($uploadHistory); ?></div>
                                        <div class="stat-label">Chapters</div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo date('M Y'); ?></div>
                                        <div class="stat-label">Latest</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="history-timeline">
                            <?php foreach ($uploadHistory as $chapterNum => $uploads): ?>
                                <div class="chapter-history-group" data-chapter="<?php echo $chapterNum; ?>">
                                    <div class="chapter-group-header">
                                        <h3>Chapter <?php echo $chapterNum; ?>: <?php echo htmlspecialchars($chapterNames[$chapterNum]); ?></h3>
                                        <span class="upload-count"><?php echo count($uploads); ?> upload<?php echo count($uploads) > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <?php foreach ($uploads as $upload): ?>
                                        <div class="history-item" data-filename="<?php echo strtolower($upload['original_filename']); ?>">
                                            <div class="timeline-marker">
                                                <div class="chapter-badge">Ch<?php echo $chapterNum; ?></div>
                                            </div>
                                            <div class="file-meta">
                                                <div class="file-info">
                                                    <div class="file-name">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <span><?php echo htmlspecialchars($upload['original_filename']); ?></span>
                                                    </div>
                                                    <div class="file-details">
                                                        <span class="upload-date">
                                                            <i class="fas fa-clock"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($upload['upload_date'])); ?>
                                                        </span>
                                                        <span class="file-version">
                                                            <i class="fas fa-code-branch"></i>
                                                            Version <?php echo $upload['version']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="file-actions">
                                                <a href="../<?php echo htmlspecialchars($upload['file_path']); ?>" download class="action-btn download-btn" title="Download file">
                                                    <i class="fas fa-download"></i>
                                                    <span>Download</span>
                                                </a>
                                                <a href="../<?php echo htmlspecialchars($upload['file_path']); ?>" target="_blank" class="action-btn view-btn" title="View file">
                                                    <i class="fas fa-eye"></i>
                                                    <span>View</span>
                                                </a>
                                                <button class="action-btn delete-btn" 
                                                        onclick="showDeleteConfirmation(<?php echo $chapterNum; ?>, <?php echo $upload['version']; ?>, <?php echo $userGroup['id']; ?>, '<?php echo htmlspecialchars($upload['original_filename']); ?>')"
                                                        title="Delete file">
                                                    <i class="fas fa-trash"></i>
                                                    <span>Delete</span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Added professional delete confirmation modal -->
<div id="deleteConfirmationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Deletion</h3>
            <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="delete-confirmation">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="delete-message">
                    <p>Are you sure you want to delete this file?</p>
                    <div class="file-details">
                        <div><strong>File:</strong> <span id="deleteFileName"></span></div>
                        <div><strong>Chapter:</strong> <span id="deleteChapterInfo"></span></div>
                        <div><strong>Version:</strong> <span id="deleteVersionInfo"></span></div>
                    </div>
                    <p class="delete-warning">
                        <i class="fas fa-info-circle"></i>
                        This action cannot be undone. The file will be permanently removed from your upload history.
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-modal btn-cancel-delete" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-modal btn-confirm-delete" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Delete File
            </button>
        </div>
    </div>
</div>

<!-- Enhanced logout confirmation modal
<div id="logoutModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Logout</h3>
            <button class="close-modal" onclick="closeLogoutModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="logout-confirmation">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <p>Are you sure you want to logout from ThesisTrack?</p>
                <p class="logout-note">You will need to login again to access your dashboard.</p>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-modal btn-cancel" onclick="closeLogoutModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-modal btn-danger" onclick="confirmLogout()">
                <i class="fas fa-sign-out-alt"></i> Yes, Logout
            </button>
        </div>
    </div>
</div> -->

<script src="../JS/student_chap-upload.js"></script>
<script>
// Filter upload history function
function filterUploadHistory() {
    const chapterFilter = document.getElementById('chapterFilter')?.value || 'all';
    const searchFilter = document.getElementById('searchHistory')?.value.toLowerCase() || '';
    const historyGroups = document.querySelectorAll('.chapter-history-group');
    
    historyGroups.forEach(group => {
        const chapterNum = group.getAttribute('data-chapter');
        const chapterMatch = chapterFilter === 'all' || chapterFilter === chapterNum;
        
        if (chapterMatch) {
            const timelineItems = group.querySelectorAll('.history-item');
            let hasVisibleItems = false;
            
            timelineItems.forEach(item => {
                const filename = item.getAttribute('data-filename');
                const filenameMatch = searchFilter === '' || filename.includes(searchFilter);
                
                if (filenameMatch) {
                    item.style.display = 'block';
                    hasVisibleItems = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            group.style.display = hasVisibleItems ? 'block' : 'none';
        } else {
            group.style.display = 'none';
        }
    });
}

let deleteParams = {};

function showDeleteConfirmation(chapterNumber, version, groupId, fileName) {
    deleteParams = { chapterNumber, version, groupId };
    
    const modal = document.getElementById('deleteConfirmationModal');
    const fileNameSpan = document.getElementById('deleteFileName');
    const chapterInfoSpan = document.getElementById('deleteChapterInfo');
    const versionInfoSpan = document.getElementById('deleteVersionInfo');
    
    const chapterNames = {
        1: "Introduction",
        2: "Review of Related Literature", 
        3: "Methodology",
        4: "Results and Discussion",
        5: "Summary, Conclusion, and Recommendation"
    };
    
    fileNameSpan.textContent = fileName;
    chapterInfoSpan.textContent = `Chapter ${chapterNumber}: ${chapterNames[chapterNumber] || 'Unknown'}`;
    versionInfoSpan.textContent = `v${version}`;
    
    modal.classList.add('show');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteConfirmationModal');
    modal.classList.remove('show');
    deleteParams = {};
}

function confirmDelete() {
    const { chapterNumber, version, groupId } = deleteParams;
    
    const formData = new FormData();
    formData.append('action', 'delete_upload');
    formData.append('chapter_number', chapterNumber);
    formData.append('version', version);
    formData.append('group_id', groupId);
    
    // Show loading state
    const confirmBtn = document.querySelector('.btn-confirm-delete');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    confirmBtn.disabled = true;
    
    fetch('student_chap-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            showMessage('File deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage('Error deleting file: ' + (data.error || 'Unknown error'), 'error');
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deleting file. Please try again.', 'error');
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    });
}

function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.remove('show');
}

function confirmLogout() {
    window.location.href = '../logout.php';
}

function showMessage(text, type = 'info') {
    // Remove existing messages
    document.querySelectorAll('.message').forEach(msg => msg.remove());
    
    const message = document.createElement('div');
    message.className = `message message-${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'error' ? 'exclamation-circle' : 
                 type === 'warning' ? 'exclamation-triangle' : 'info-circle';
    
    message.innerHTML = `
        <div class="message-content">
            <i class="fas fa-${icon}"></i>
            <span>${text}</span>
        </div>
        <button class="message-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertAdjacentElement('afterbegin', message);
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (message.parentNode) {
            message.remove();
        }
    }, 5000);
}

// Close modals when clicking outside
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
    }
});
</script>
</body>
</html>
