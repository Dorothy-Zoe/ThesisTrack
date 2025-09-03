<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the 'coordinator' role
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


// Fetch thesis groups data from database
try {
    // Main query to get thesis groups with their details - now correctly joined through groups table
    $groupsQuery = "
        SELECT 
            sg.id AS student_group_id,
            g.id AS group_id,
            sg.group_name,
            sg.thesis_title,
            sg.section,
            sg.course,
            sg.status AS group_status,
            CONCAT(a.first_name, ' ', a.last_name) AS advisor_name,
            COUNT(DISTINCT gm.student_id) AS member_count,
            COALESCE(approved_chapters.cnt, 0) AS approved_chapters,
            COALESCE(total_chapters.cnt, 0) AS total_chapters
        FROM student_groups sg
        LEFT JOIN groups g ON sg.group_id = g.id
        LEFT JOIN advisors a ON sg.advisor_id = a.id
        LEFT JOIN group_members gm ON g.id = gm.group_id
        LEFT JOIN (
            SELECT group_id, COUNT(*) AS cnt 
            FROM chapters 
            WHERE status = 'approved'
            GROUP BY group_id
        ) approved_chapters ON sg.id = approved_chapters.group_id
        LEFT JOIN (
            SELECT group_id, COUNT(*) AS cnt 
            FROM chapters 
            GROUP BY group_id
        ) total_chapters ON sg.id = total_chapters.group_id
        GROUP BY sg.id, g.id, a.first_name, a.last_name, approved_chapters.cnt, total_chapters.cnt
        ORDER BY sg.course, sg.section, sg.group_name
    ";
    $groupsStmt = $pdo->prepare($groupsQuery);
    $groupsStmt->execute();
    $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch distinct sections for filter dropdown
    $sectionsQuery = "SELECT DISTINCT section FROM student_groups WHERE section IS NOT NULL ORDER BY section";
    $sectionsStmt = $pdo->query($sectionsQuery);
    $sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Fetch distinct courses for filter dropdown
    $coursesQuery = "SELECT DISTINCT course FROM student_groups WHERE course IS NOT NULL ORDER BY course";
    $coursesStmt = $pdo->query($coursesQuery);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Fetch active advisors for filter dropdown
    $advisorsQuery = "
        SELECT DISTINCT a.id, CONCAT(a.first_name, ' ', a.last_name) AS advisor_name
        FROM advisors a
        JOIN student_groups sg ON a.id = sg.advisor_id
        WHERE a.status = 'active'
        ORDER BY advisor_name
    ";
    $advisorsStmt = $pdo->query($advisorsQuery);
    $advisors = $advisorsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in coordinator_thesis-groups.php: " . $e->getMessage());
    $groups = [];
    $sections = [];
    $courses = [];
    $advisors = [];
    $error_message = "Unable to load thesis groups data. Please try again later.";
}


// Sorting functionality
$sort_column = $_GET['sort'] ?? 'group_name';
$sort_order = $_GET['order'] ?? 'asc';

// Search functionality
$search_term = $_GET['search'] ?? '';

// Filter functionality
$program_filter = $_GET['program'] ?? '';
$section_filter = $_GET['section'] ?? '';
$advisor_filter = $_GET['advisor'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Apply filters and search
$filtered_groups = array_filter($groups, function($group) use ($program_filter, $section_filter, $advisor_filter, $status_filter, $search_term) {
    $matches = true;
    
    if ($program_filter && $group['course'] != $program_filter) {
        $matches = false;
    }
    
    if ($section_filter && $group['section'] != $section_filter) {
        $matches = false;
    }
    
    if ($advisor_filter && $group['advisor_name'] != $advisor_filter) {
        $matches = false;
    }
    
    if ($status_filter && $group['group_status'] != $status_filter) {
        $matches = false;
    }
    
    if ($search_term) {
        $search_match = false;
        $search_fields = ['group_name', 'thesis_title', 'section', 'course', 'advisor_name'];
        foreach ($search_fields as $field) {
            if (stripos($group[$field], $search_term) !== false) {
                $search_match = true;
                break;
            }
        }
        $matches = $matches && $search_match;
    }
    
    return $matches;
});

// Sorting
usort($filtered_groups, function($a, $b) use ($sort_column, $sort_order) {
    $val1 = $a[$sort_column];
    $val2 = $b[$sort_column];
    
    if ($sort_order == 'asc') {
        return $val1 <=> $val2;
    } else {
        return $val2 <=> $val1;
    }
});

// ================== Start of Version 7 update ================== //

// Pagination
$per_page = 5;
$page = $_GET['page'] ?? 1;
$total_groups = count($filtered_groups);
$total_pages = ceil($total_groups / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_groups = array_slice($filtered_groups, $offset, $per_page);

// Function to generate sort arrows with distinct icons
function getSortArrows($current_col, $sort_col, $sort_order) {
    if ($current_col == $sort_col) {
        // Active state: Solid caret-style arrow
        $arrow = $sort_order == 'asc' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow" title="Sorted"></i>';
    }
    // Neutral state: Standard sort icon
    return '<i class="fas fa-sort neutral-arrow" title="Click to sort"></i>';
}

$entries_per_page = $_GET['entries'] ?? 5;
$total_pages = ceil(count($filtered_groups) / $entries_per_page);

// ================== End of Version 7 update ================== //

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/coordinator_thesis-groups.css">
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
                    <div class="sidebar-username"><?php echo $user_name; ?></div>
                </div>
                <span class="role-badge">Research Coordinator</span>
            </div>
            <nav class="sidebar-nav">
                <a href="coordinator_dashboard.php" class="nav-item" data-tab="overview">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="coordinator_sec-advisors.php" class="nav-item" data-tab="sections">
                    <i class="fas fa-school"></i> Sections & Advisors
                </a>
                <a href="coordinator_thesis-groups.php" class="nav-item active" data-tab="groups">
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
            </header>         <!-- End Header -->

            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-users"></i> All CICT Thesis Groups</h1>
                    <p>Monitor and manage all thesis groups across BSCS and BSIS programs.</p>
                </div>

                <!-- Error message display -->
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Groups Tab -->
                <div id="groups" class="tab-content">
                    <div class="groups-container">
                        <h3>Thesis Groups Overview</h3>

                        <div class="filters-section">
                            <div class="filter-group">
                                <label for="programFilter">Program:</label>
                                <select id="programFilter" class="filter-select">
                                    <option value="">All Programs</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course); ?>">
                                            <?php echo htmlspecialchars($course); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="sectionFilter">Section:</label>
                                <select id="sectionFilter" class="filter-select">
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo htmlspecialchars($section); ?>">
                                            <?php echo htmlspecialchars($section); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="advisorFilter">Advisor:</label>
                                <select id="advisorFilter" class="filter-select">
                                    <option value="">All Advisors</option>
                                    <?php foreach ($advisors as $advisor): ?>
                                        <option value="<?php echo htmlspecialchars($advisor['advisor_name']); ?>">
                                            <?php echo htmlspecialchars($advisor['advisor_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="statusFilter">Status:</label>
                                <select id="statusFilter" class="filter-select">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <?php if (empty($groups)): ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i> No thesis groups found.
                            </div>
                        <?php else: ?>

                        <div class="table-scroll-wrapper"> <!-- VERSION 7 UPDATE 7/21 -->

                        <!-- show entries -->
                            <div class="table-controls-row">
                                <div class="entries-selector">
                                    <span>Show</span>
                                    <select name="entries" onchange="this.form.submit()" class="entries-select">
                                        <?php
                                        $entries_options = [5, 10, 25, 50];
                                        $selected_entries = $_GET['entries'] ?? 5;
                                        
                                        foreach ($entries_options as $option) {
                                            $selected = ($option == $selected_entries) ? 'selected' : '';
                                            echo "<option value='$option' $selected>$option</option>";
                                        }
                                        ?>
                                    </select>
                                    <span>entries</span>
                                </div>

                                <form class="modern-search" method="GET" action="">
                                    <div class="search-container">
                                        <i class="fas fa-search"></i>
                                        <input type="text" name="search" placeholder="Search here..." class="search-input" 
                                            value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES) ?>">
                                        <!-- Preserve other GET parameters -->
                                        <?php foreach ($_GET as $key => $value): ?>
                                            <?php if ($key !== 'search' && $key !== 'page'): ?>
                                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </form>
                            </div>
    
                    <table id="groupsTable" class="groups-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?sort=group_name&order=<?= $sort_column == 'group_name' && $sort_order == 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>">
                                        Group <?= getSortArrows('group_name', $sort_column, $sort_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=section&order=<?= $sort_column == 'section' && $sort_order == 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>">
                                        Section <?= getSortArrows('section', $sort_column, $sort_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=course&order=<?= $sort_column == 'course' && $sort_order == 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>">
                                        Program <?= getSortArrows('course', $sort_column, $sort_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=thesis_title&order=<?= $sort_column == 'thesis_title' && $sort_order == 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>">
                                        Thesis Title <?= getSortArrows('thesis_title', $sort_column, $sort_order) ?>
                                    </a>
                                </th>
                                <th>Members</th>
                                <th>
                                    <a href="?sort=advisor_name&order=<?= $sort_column == 'advisor_name' && $sort_order == 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>">
                                        Advisor <?= getSortArrows('advisor_name', $sort_column, $sort_order) ?>
                                    </a>
                                </th>
                                <th>Progress</th>
                                <th>
                                    <a href="?sort=group_status&order=<?= $sort_column == 'group_status' && $sort_order == 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>">
                                        Status <?= getSortArrows('group_status', $sort_column, $sort_order) ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    <tbody>
                <?php foreach ($paginated_groups as $group): 
                                        // Calculate progress percentage
                                        $progress = $group['total_chapters'] > 0 
                                            ? round(($group['approved_chapters'] / $group['total_chapters']) * 100) 
                                            : 0;
                                        
                                        // Get course abbreviation for badge class
                                        $course_abbr = strtoupper(substr($group['course'], 0, 4));
                                        $group_status = strtolower($group['group_status']);
                                    ?>
                                    <tr data-program="<?php echo htmlspecialchars($group['course']); ?>" 
                                        data-section="<?php echo htmlspecialchars($group['section']); ?>"
                                        data-advisor="<?php echo htmlspecialchars($group['advisor_name']); ?>"
                                        data-status="<?php echo $group_status; ?>"
                                        data-progress="<?php echo $progress; ?>">
                                        <td><strong><?php echo htmlspecialchars($group['group_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($group['section']); ?></td>
                                        <td><span class="course-badge <?php echo strtolower($course_abbr); ?>"><?php echo $course_abbr; ?></span></td>
                                        <td class="thesis-title-cell" title="<?php echo htmlspecialchars($group['thesis_title']); ?>">
                                            <?php 
                                                echo strlen($group['thesis_title']) > 30 
                                                    ? htmlspecialchars(substr($group['thesis_title'], 0, 30)) . '...' 
                                                    : htmlspecialchars($group['thesis_title']); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                // Fetch group members with error handling
                                                 try {
                                                    // Modified query to ensure leader appears first
                                                    $membersQuery = "
                                                        SELECT s.first_name, s.last_name, gm.role_in_group
                                                        FROM group_members gm
                                                        JOIN students s ON gm.student_id = s.id
                                                        WHERE gm.group_id = ?
                                                        ORDER BY 
                                                            CASE WHEN gm.role_in_group = 'leader' THEN 0 ELSE 1 END,  -- Leaders first
                                                            s.last_name ASC  -- Then sort others by last name
                                                    ";
                                                    $membersStmt = $pdo->prepare($membersQuery);
                                                    $membersStmt->execute([$group['group_id']]);
                                                    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    if (empty($members)) {
                                                        echo "No members";
                                                    } else {
                                                        // First display the leader (if exists)
                                                        $leaderFound = false;
                                                        foreach ($members as $member) {
                                                            if ($member['role_in_group'] === 'leader') {
                                                                $name = htmlspecialchars($member['first_name']) . ' ' . htmlspecialchars($member['last_name']);
                                                                echo "<strong>$name (Leader)</strong><br>";
                                                                $leaderFound = true;
                                                            }
                                                        }
                                                        
                                                        // Then display other members
                                                        foreach ($members as $member) {
                                                            if ($member['role_in_group'] !== 'leader') {
                                                                $name = htmlspecialchars($member['first_name']) . ' ' . htmlspecialchars($member['last_name']);
                                                                echo "$name<br>";
                                                            }
                                                        }
                                                        
                                                        // If no leader found but members exist
                                                        if (!$leaderFound && !empty($members)) {
                                                            echo "<em>No leader assigned</em><br>";
                                                        }
                                                    }
                                                } catch (PDOException $e) {
                                                    error_log("Error fetching members for group {$group['group_id']}: " . $e->getMessage());
                                                    echo "Error loading members";
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo !empty($group['advisor_name']) ? htmlspecialchars($group['advisor_name']) : 'Not assigned'; ?></td>
                                        <td>
                                            <div class="progress-container">
                                                <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                                    <span><?php echo $progress; ?>%</span>
                                                </div>
                                            </div>
                                            <div class="chapter-progress-cell">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php 
                                                        $status = '';
                                                        if ($i <= $group['approved_chapters']) {
                                                            $status = 'completed';
                                                        } elseif ($i == $group['approved_chapters'] + 1 && $group['approved_chapters'] < $group['total_chapters']) {
                                                            $status = 'in-progress';
                                                        } else {
                                                            $status = 'pending';
                                                        }
                                                    ?>
                                                    <div class="chapter-indicator <?php echo $status; ?>" title="Chapter <?php echo $i; ?> - <?php echo ucfirst($status); ?>">
                                                        <?php echo $i; ?>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $group_status; ?>">
                                                <?php echo ucfirst($group_status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-secondary btn-small view-group-btn" 
                                                    data-group-id="<?php echo $group['student_group_id']; ?>"
                                                    onclick="viewGroupDetails(<?php echo $group['student_group_id']; ?>)">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                             <!-- V7 Update: Pagination (7/21) -->
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>&search=<?= urlencode($search_term) ?>">&laquo; Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?= $i ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>&search=<?= urlencode($search_term) ?>" <?= $i == $page ? 'class="active"' : '' ?>>
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page+1 ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>&search=<?= urlencode($search_term) ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>

                            </div>
                          </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    

    <script src="../JS/coordinator_thesis-groups.js"></script>
</body>
</html>