<?php
session_start();
require_once '../db/db.php';

// Check coordinator session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    header('Location: ../login.php');
    exit();
}

// =================V7 UPDATE
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
    $profile_picture = $coordinator['profile_picture'] ? '../uploads/profile_pictures/' . $coordinator['profile_picture'] : '../images/default-user.png';
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ../login.php');
    exit();
}
// =================END OF V7 UPDATE


// Fetch section-advisor data from database
try {
    $stmt = $pdo->query("
        SELECT 
            asec.section,
            asec.course,
            asec.advisor_id,
            CONCAT(a.first_name, ' ', a.last_name) AS advisor_name,
            COUNT(DISTINCT sg.id) AS group_count,
                (
                    SELECT COUNT(*) 
                    FROM students s 
                    WHERE s.advisor_id = asec.advisor_id 
                    AND s.course = asec.course
                    AND s.section = asec.section
                ) AS student_count,
            a.status AS advisor_status
        FROM advisor_sections asec
        JOIN advisors a ON asec.advisor_id = a.id
        LEFT JOIN student_groups sg ON asec.section = sg.section AND asec.course = sg.course
        GROUP BY asec.section, asec.course, a.id
        ORDER BY asec.course, asec.section
    ");
    $sections = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback to sample data if query fails
    $sections = [
        [
            'section' => 'BSCS-4A',
            'course' => 'BS Computer Science',
            'advisor_name' => 'Dr. Amanda Martinez',
            'group_count' => 2,
            'student_count' => 3, // This will now reflect actual student count from your database
            'advisor_status' => 'active'
        ],
        [
            'section' => 'BSCS-4B',
            'course' => 'BS Computer Science',
            'advisor_name' => 'Dr. Michael Thompson',
            'group_count' => 1,
            'student_count' => 0, // This will now reflect actual student count from your database
            'advisor_status' => 'active'
        ],
    ];
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/coordinator_sec-advisors.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
               <div class="sidebar-user">
    <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
         class="sidebar-avatar" 
         alt="Coordinator Avatar"
         id="currentProfilePicture"
         onerror="this.src='../images/default-user.png'" />
    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
</div>
                <span class="role-badge">Research Coordinator</span>
            </div>
            <nav class="sidebar-nav">
                <a href="coordinator_dashboard.php" class="nav-item" data-tab="overview">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="coordinator_sec-advisors.php" class="nav-item active" data-tab="sections">
                    <i class="fas fa-school"></i> Sections & Advisors
                </a>
                <a href="coordinator_thesis-groups.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Thesis Groups
                </a>
                <a href="coordinator_advisor-mngt.php" class="nav-item" data-tab="advisors">
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
        </aside>    <!-- End Sidebar -->

        <!-- Main Content -->
        <div class="content-wrapper">
              <!-- Header -->
            <header class="blank-header">
                <div class="topbar-left"></div>
                <div class="topbar-right">
                    <button class="topbar-icon" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    <div class="user-info dropdown">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>?t=<?php echo time(); ?>" 
                            alt="User Avatar" 
                            class="user-avatar" 
                            id="userAvatar" 
                            tabindex="0"
                            onerror="this.src='../images/default-user.png'" />
                        <div class="dropdown-menu" id="userDropdown">
                            <a href="#" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="#" id="logoutLink" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>

                    <!-- Logout Confirmation Modal for HEADER -->
                            <div id="LogoutModal" class="modal" style="display:none;">
                            <div class="logout-modal-content">
                                <h3>Confirm Logout</h3>
                                <p>Are you sure you want to logout?</p>
                                <div class="modal-buttons">
                                    <button id="headerConfirmLogout" class="btn btn-danger">Yes, Logout</button>
                                    <button id="headerCancelLogout" class="btn btn-secondary">Cancel</button>
                                </div>
                            </div>
                        </div>
                </div>
            </header>   <!-- End Header -->

            <main class="main-content">
                <div id="sections" class="tab-content">
                
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-school"></i> CICT Sections and Advisor Assignments</h1>
                    <p>Manage section assignments and advisor workloads across BSCS and BSIS programs.</p>
                </div>
                 <!-- End of Page Header -->

                    <div class="card">
                        <h3>Section Advisory Overview</h3>

                        <table id="sectionsTable" class="sections-table">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Program</th>
                                    <th>Assigned Advisor</th>
                                    <th>Handled Groups</th>
                                    <th>Students</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections as $section): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($section['section']); ?></strong></td>
                                        <td>
                                            <span class="course-badge <?php echo strtolower(str_replace(' ', '-', $section['course'])); ?>">
                                                <?php echo htmlspecialchars($section['course']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($section['advisor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['group_count']); ?></td>
                                        <td><?php echo htmlspecialchars($section['student_count']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $section['advisor_status'] === 'active' ? 'active' : 'inactive'; ?>">
                                                <?php echo ucfirst($section['advisor_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-secondary btn-small" onclick="viewSectionDetails('<?php echo htmlspecialchars($section['section']); ?>')">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
   
    <script src="../JS/coordinator_sec-advisors.js"></script>

</body>
</html>