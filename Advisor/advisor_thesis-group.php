<?php
session_start();
require_once '../db/db.php';

// Set content type to JSON for API responses
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['name'] ?? 'Advisor';

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


// Initialize sorting variables from URL parameters
$sort_col = $_GET['sort'] ?? 'title'; // Default sort column
$sort_order = $_GET['order'] ?? 'asc'; // Default sort order

// Validate sort column to prevent SQL injection
$valid_columns = ['id', 'title', 'section', 'status', 'thesis_title'];
if (!in_array($sort_col, $valid_columns)) {
    $sort_col = 'title';
}

// Validate sort order
$sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

// Function to generate sort arrows
function getSortArrows($current_col, $sort_col, $sort_order) {
    if ($current_col == $sort_col) {
        // Active state: Solid caret-style arrow
        $arrow = $sort_order == 'ASC' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow" title="Sorted"></i>';
    }
    // Neutral state: Standard sort icon
    return '<i class="fas fa-sort neutral-arrow" title="Click to sort"></i>';
}       

// Get advisor's sections and course with proper handling of multiple sections
try {
    $stmt = $pdo->prepare("SELECT sections_handled, department FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor_info = $stmt->fetch();
    
    // Clean and split sections
    $advisor_sections = array_filter(
        array_map('trim', 
            explode(',', $advisor_info['sections_handled'] ?? '')
        ),
        function($section) { return !empty($section); }
    );
    
    $advisor_course = $advisor_info['department'] ?? null;
    
    // If no sections found, set to empty array
    if (empty($advisor_sections)) {
        $advisor_sections = [];
    }
} catch (PDOException $e) {
    $advisor_sections = [];
    $advisor_course = null;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
           case 'create_group':
                $group_name = trim($_POST['group_name'] ?? '');
                $thesis_title = trim($_POST['thesis_title'] ?? '');
                $section = $_POST['section'] ?? '';
                $student_ids = $_POST['student_ids'] ?? [];
                $student_roles = $_POST['student_roles'] ?? [];

                // Validate inputs
                if (empty($group_name)) {
                    throw new Exception('Group name is required');
                }

                if (empty($thesis_title)) {
                    throw new Exception('Thesis title is required');
                }

                // Validate section belongs to advisor
                if (!in_array($section, $advisor_sections)) {
                    throw new Exception('Invalid section selected');
                }

                // Validate member count
                if (count($student_ids) === 0) {
                    throw new Exception('Please select at least one student');
                }

                if (count($student_ids) > 4) {
                    throw new Exception('A group can have maximum 4 members');
                }

                // Validate exactly 1 leader
                $leaderCount = array_count_values($student_roles)['leader'] ?? 0;
                if ($leaderCount !== 1) {
                    throw new Exception('Each group must have exactly 1 Leader');
                }

                // Validate all students are from the same section as the group
                if (!empty($student_ids)) {
                    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT section) as section_count 
                        FROM students 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($student_ids);
                    $result = $stmt->fetch();
                    
                    if ($result['section_count'] > 1) {
                        throw new Exception('All students must be from the same section');
                    }
                    
                    // Verify the students' section matches the group section
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as match_count
                        FROM students
                        WHERE id IN ($placeholders) AND section = ?
                    ");
                    $params = array_merge($student_ids, [$section]);
                    $stmt->execute($params);
                    $result = $stmt->fetch();
                    
                    if ($result['match_count'] != count($student_ids)) {
                        throw new Exception('All students must be from the selected section: ' . $section);
                    }
                }

                $pdo->beginTransaction();
                
                // 1. Create the main group record
                $stmt = $pdo->prepare("INSERT INTO groups (title, advisor_id, section, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$group_name, $advisor_id, $section]);
                $group_id = $pdo->lastInsertId();
                
                // 2. Create the student_group record with group_id reference
                $stmt = $pdo->prepare("INSERT INTO student_groups (group_id, group_name, thesis_title, advisor_id, section, course, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$group_id, $group_name, $thesis_title, $advisor_id, $section, $advisor_course]);
                $student_group_id = $pdo->lastInsertId();
                
                // 3. Add members
                foreach ($student_ids as $index => $student_id) {
                    $role = $student_roles[$index] ?? 'member';
                    
                    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, student_id, role_in_group) VALUES (?, ?, ?)");
                    $stmt->execute([$group_id, $student_id, $role]);
                    
                    // Update student's advisor
                    $stmt = $pdo->prepare("UPDATE students SET advisor_id = ? WHERE id = ?");
                    $stmt->execute([$advisor_id, $student_id]);
                }
                
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Group created successfully',
                    'group_id' => $group_id,
                    'student_group_id' => $student_group_id
                ]);
                exit();

            case 'update_group':
                $group_id = $_POST['group_id'] ?? 0;
                $student_group_id = $_POST['student_group_id'] ?? 0;
                $group_name = trim($_POST['group_name'] ?? '');
                $thesis_title = trim($_POST['thesis_title'] ?? '');
                $student_ids = $_POST['student_ids'] ?? [];
                $student_roles = $_POST['student_roles'] ?? [];

                // Validate inputs
                if (empty($group_name)) {
                    throw new Exception('Group name is required');
                }

                if (empty($thesis_title)) {
                    throw new Exception('Thesis title is required');
                }

                // Validate member count
                if (count($student_ids) === 0) {
                    throw new Exception('Please select at least one student');
                }

                if (count($student_ids) > 4) {
                    throw new Exception('A group can have maximum 4 members');
                }

                // Validate exactly 1 leader
                $leaderCount = array_count_values($student_roles)['leader'] ?? 0;
                if ($leaderCount !== 1) {
                    throw new Exception('Each group must have exactly 1 Leader');
                }

                $pdo->beginTransaction();
                
                // Verify group belongs to this advisor and get current section
                $stmt = $pdo->prepare("SELECT id, section FROM groups WHERE id = ? AND advisor_id = ?");
                $stmt->execute([$group_id, $advisor_id]);
                $group_info = $stmt->fetch();
                
                if (!$group_info) {
                    throw new Exception('Group not found or access denied');
                }

                // Validate if same section
                // If trying to change section, validate first
                if ($new_section && $new_section !== $current_section) {
                    // Check if all current members are from the new section
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as match_count
                        FROM group_members gm
                        JOIN students s ON gm.student_id = s.id
                        WHERE gm.group_id = ? AND s.section = ?
                    ");
                    $stmt->execute([$group_id, $new_section]);
                    $result = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM group_members WHERE group_id = ?");
                    $stmt->execute([$group_id]);
                    $total_members = $stmt->fetchColumn();
                    
                    if ($result['match_count'] != $total_members) {
                        throw new Exception('Cannot change section - existing members are from different sections');
                    }
                    
                    // Update section in both tables if validation passes
                    $stmt = $pdo->prepare("UPDATE groups SET section = ? WHERE id = ?");
                    $stmt->execute([$new_section, $group_id]);
                    
                    $stmt = $pdo->prepare("UPDATE student_groups SET section = ? WHERE group_id = ?");
                    $stmt->execute([$new_section, $group_id]);
                    
                    $current_section = $new_section;
                }
                
                // Validate all new students are from the group's current section
                if (!empty($student_ids)) {
                    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as match_count
                        FROM students
                        WHERE id IN ($placeholders) AND section = ?
                    ");
                    $params = array_merge($student_ids, [$current_section]);
                    $stmt->execute($params);
                    $result = $stmt->fetch();
                    
                    if ($result['match_count'] != count($student_ids)) {
                        throw new Exception('All students must be from the group\'s current section: ' . $current_section);
                    }
                }

                // Update group name in groups table
                $stmt = $pdo->prepare("UPDATE groups SET title = ? WHERE id = ?");
                $stmt->execute([$group_name, $group_id]);
                
                // Update group info in student_groups table
                $stmt = $pdo->prepare("UPDATE student_groups SET group_name = ?, thesis_title = ? WHERE id = ?");
                $stmt->execute([$group_name, $thesis_title, $student_group_id]);
                
                // Remove all current members
                $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmt->execute([$group_id]);
                
                // Add all members (existing and new) with their roles
                foreach ($student_ids as $index => $student_id) {
                    $role = $student_roles[$index] ?? 'member';
                    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, student_id, role_in_group) VALUES (?, ?, ?)");
                    $stmt->execute([$group_id, $student_id, $role]);
                    
                    // Update student's advisor
                    $stmt = $pdo->prepare("UPDATE students SET advisor_id = ? WHERE id = ?");
                    $stmt->execute([$advisor_id, $student_id]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Group updated successfully']);
                exit();

            case 'get_group_data':
                $group_id = $_POST['group_id'] ?? 0;
                
                // Get basic group info
                $stmt = $pdo->prepare("
                    SELECT g.id, g.title as group_name, sg.id as student_group_id, sg.thesis_title, g.section
                    FROM groups g
                    JOIN student_groups sg ON g.id = sg.group_id
                    WHERE g.id = ? AND g.advisor_id = ?
                ");
                $stmt->execute([$group_id, $advisor_id]);
                $group_info = $stmt->fetch();
                
                if (!$group_info) {
                    throw new Exception('Group not found or access denied');
                }
                
                // Get current members
                $stmt = $pdo->prepare("
                    SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, gm.role_in_group as role
                    FROM group_members gm
                    JOIN students s ON gm.student_id = s.id
                    WHERE gm.group_id = ?
                    ORDER BY 
                    CASE gm.role_in_group WHEN 'leader' THEN 0 ELSE 1 END,
                    s.last_name, s.first_name
                ");
                $stmt->execute([$group_id]);
                $members = $stmt->fetchAll();
                
                // Get available students (ungrouped in same section)
                $stmt = $pdo->prepare("
                    SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.section
                    FROM students s
                    LEFT JOIN group_members gm ON s.id = gm.student_id
                    WHERE (s.advisor_id IS NULL OR s.advisor_id = ?)
                    AND s.section = ?
                    AND gm.student_id IS NULL
                    ORDER BY s.last_name, s.first_name
                ");
                $stmt->execute([$advisor_id, $group_info['section']]);
                $available_students = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => $group_info,
                    'members' => $members,
                    'available_students' => $available_students
                ]);
                exit();

            case 'delete_group':
                $group_id = $_POST['group_id'] ?? 0;
                $student_group_id = $_POST['student_group_id'] ?? 0;
                
                $pdo->beginTransaction();
                
                // Verify group belongs to this advisor
                $stmt = $pdo->prepare("SELECT id, section FROM groups WHERE id = ? AND advisor_id = ?");
                $stmt->execute([$group_id, $advisor_id]);
                $group_info = $stmt->fetch();
                
                if (!$group_info) {
                    throw new Exception('Group not found or access denied');
                }
                
                // Get member IDs to update their advisor_id to NULL
                $stmt = $pdo->prepare("SELECT student_id FROM group_members WHERE group_id = ?");
                $stmt->execute([$group_id]);
                $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Delete group members
                $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmt->execute([$group_id]);
                
                // Delete the group from groups table
                $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
                $stmt->execute([$group_id]);
                
                // Delete the corresponding student_group
                $stmt = $pdo->prepare("DELETE FROM student_groups WHERE id = ?");
                $stmt->execute([$student_group_id]);
                
                // Update students' advisor_id to NULL
                if (!empty($member_ids)) {
                    $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE students SET advisor_id = NULL WHERE id IN ($placeholders)");
                    $stmt->execute($member_ids);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
                exit();

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_info' => isset($stmt) ? $stmt->errorInfo() : null
        ]);
        exit();
    }
}

// If not an AJAX request, output HTML
header('Content-Type: text/html');

// Fetch groups for this advisor with combined data from both tables
try {
    $stmt = $pdo->prepare("
        SELECT g.id, g.title as group_name, g.section, g.status, 
               sg.thesis_title, sg.id as student_group_id
        FROM groups g
        JOIN student_groups sg ON g.id = sg.group_id
        WHERE g.advisor_id = ?
        ORDER BY $sort_col $sort_order, g.section, g.title
    ");
    $stmt->execute([$advisor_id]);
    $groups = $stmt->fetchAll();


    // Get members for each group
    foreach ($groups as &$group) {
        $stmt = $pdo->prepare("
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, gm.role_in_group as role
            FROM group_members gm
            JOIN students s ON gm.student_id = s.id
            WHERE gm.group_id = ?
            ORDER BY 
            CASE gm.role_in_group WHEN 'leader' THEN 0 ELSE 1 END,
            s.last_name, s.first_name
        ");
        $stmt->execute([$group['id']]);
        $group['members'] = $stmt->fetchAll();
    }
    unset($group); // Break the reference

} catch (PDOException $e) {
    $groups = [];
}

// Fetch ungrouped students for this advisor's sections
try {
    if (empty($advisor_sections)) {
        $ungrouped_students = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($advisor_sections), '?'));
        $query = "
            SELECT s.id, s.first_name, s.last_name, s.section
            FROM students s
            LEFT JOIN group_members gm ON s.id = gm.student_id
            WHERE (s.advisor_id IS NULL OR s.advisor_id = ?)
            AND s.section IN ($placeholders)
            AND gm.student_id IS NULL
            ORDER BY s.section, s.last_name, s.first_name
        ";
        
        $stmt = $pdo->prepare($query);
        $params = array_merge([$advisor_id], $advisor_sections);
        $stmt->execute($params);
        $ungrouped_students = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $ungrouped_students = [];
}

// Pagination settings
$per_page = 5; // Number of items per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1; // Current page
$offset = ($page - 1) * $per_page;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_thesis-group.css">
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
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user">
                   <img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($advisor_name); ?></div>
                </div>
                <span class="role-badge">Subject Advisor</span>
            </div>
            <nav class="sidebar-nav">
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item" data-tab="students">
                    <i class="fas fa-user-graduate"></i> Student Management
                </a>
                <a href="advisor_thesis-group.php" class="nav-item active" data-tab="students">
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
                                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar" class="user-avatar" id="userAvatar" tabindex="0" />
                                <div class="dropdown-menu" id="userDropdown">
                                    <a href="#" class="dropdown-item">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                    <a href="#" id="headerLogoutLink" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </div>
                            </div>

                            <!-- Logout Confirmation Modal for HEADER -->
                            <div id="headerLogoutModal" class="modal" style="display:none;">
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
            </header>
        <!-- End Header -->

            <main class="main-content">
                <!-- Message container -->
                <div id="messageContainer"></div>
               <!-- Hidden by default
                <div id="messageContainer" style="display: none;">
                <div class="message" id="messageBox">
                    <button class="close-btn" onclick="closeMessage()">&times;</button>
                    <h3 id="messageTitle"></h3>
                    <p id="messageText"></p>
                </div>
                </div> -->


                <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-users-rectangle"></i> Thesis Group Management</h1>
                    <p>Manage thesis groups in your assigned sections: <?php echo htmlspecialchars(implode(', ', $advisor_sections)); ?></p>
                </div>

                <!-- Group Management Card -->
                <div class="card">
                    <h3><i class="fas fa-users-cog"></i> Thesis Groups</h3>
                    
                    <div class="action-section">
                        <button class="btn-primary" onclick="showCreateGroupModal()">
                            <i class="fas fa-plus"></i> Create New Group
                        </button>
                        <div class="section-info">
                            <span class="info-badge">Total Groups: <?php echo count($groups); ?></span>
                            <span class="info-badge">Ungrouped Students: <?php echo count($ungrouped_students); ?></span>
                        </div>
                    </div>

                   <!-- Show entries and Search -->
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
            
            <!-- Preserve all GET parameters except search and page -->
            <input type="hidden" name="sort" value="<?= htmlspecialchars($_GET['sort'] ?? '') ?>">
            <input type="hidden" name="order" value="<?= htmlspecialchars($_GET['order'] ?? '') ?>">
            <input type="hidden" name="entries" value="<?= htmlspecialchars($_GET['entries'] ?? '') ?>">
        </div>
    </form>
</div>
    
                    <!-- Groups Table -->
                    <div class="table-container">
                        <table id="groupsTable" class="students-table">
                         <thead>
    <tr>
        <th><a href="?sort=id&order=<?= $sort_col == 'id' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Group ID <?= getSortArrows('id', $sort_col, $sort_order) ?></a></th>
        <th><a href="?sort=title&order=<?= $sort_col == 'title' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Group Name <?= getSortArrows('title', $sort_col, $sort_order) ?></a></th>
        <th><a href="?sort=thesis_title&order=<?= $sort_col == 'thesis_title' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Thesis Title <?= getSortArrows('thesis_title', $sort_col, $sort_order) ?></a></th>
        <th>Members</th>
        <th><a href="?sort=section&order=<?= $sort_col == 'section' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Section <?= getSortArrows('section', $sort_col, $sort_order) ?></a></th>
        <th>Advisor</th>
        <th><a href="?sort=status&order=<?= $sort_col == 'status' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Status <?= getSortArrows('status', $sort_col, $sort_order) ?></a></th>
        <th>Actions</th>
    </tr>
</thead>
                            <tbody>
                                <?php if (empty($groups)): ?>
                                    <tr>
                                        <td colspan="8" class="no-data">
                                            <i class="fas fa-users-slash"></i>
                                            <p>No groups found.</p>
                                            <p>Click "Create New Group" to get started.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($groups as $group): ?>
                                        <tr data-group-id="<?php echo $group['id']; ?>">
                                            <td><strong>GRP-<?php echo str_pad($group['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                            <td data-student-group-id="<?php echo $group['student_group_id']; ?>"><?php echo htmlspecialchars($group['thesis_title'] ?? 'Not set'); ?></td>
                                            <td>
                                                <ul class="member-list">
                                                    <?php foreach ($group['members'] as $member): ?>
                                                    <li class="member-item">
                                                        <span class="member-name"><?php echo htmlspecialchars($member['name']); ?></span>
                                                        <span class="<?php echo $member['role'] === 'leader' ? 'leader-role' : 'member-role'; ?>">
                                                            (<?php echo $member['role'] === 'leader' ? 'Leader' : 'Member'; ?>)
                                                        </span>
                                                    </li>

                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td><?php echo htmlspecialchars($group['section']); ?></td>
                                            <td><?php echo htmlspecialchars($advisor_name); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $group['status']; ?>">
                                                    <?php echo ucfirst($group['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-dropdown">
                                                    <button class="action-btn" onclick="toggleActionDropdown(<?php echo $group['id']; ?>)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="action-menu" id="actionMenu<?php echo $group['id']; ?>">
                                                        <a href="#" onclick="editGroup(<?php echo $group['id']; ?>, <?php echo $group['student_group_id']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="#" onclick="deleteGroup(<?php echo $group['id']; ?>, <?php echo $group['student_group_id']; ?>)">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                        <!-- <a href="#" onclick="viewGroupDetails(<?php echo $group['id']; ?>, <?php echo $group['student_group_id']; ?>)">
                                                            <i class="fas fa-eye"></i> View
                                                        </a> -->
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

     <!-- Create Group Modal -->
    <div id="createGroupModal" class="groupmodal">
        
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-users-cog"></i> Create New Group</h3>
                <span class="close" onclick="closeCreateGroupModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createGroupForm">
                    <div class="form-group">
                        <label for="groupName">Group Name *</label>
                        <input type="text" id="groupName" name="group_name" required>
                    </div>
                    <div class="form-group">
                        <label for="thesisTitle">Thesis Title *</label>
                        <input type="text" id="thesisTitle" name="thesis_title" required>
                    </div>
                    <div class="form-group">
                        <label for="groupSection">Section *</label>
                        <select id="groupSection" name="section" required>
                            <?php foreach ($advisor_sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Members (check to include)</label>
                        <div class="student-selector" id="studentSelector">
                            <?php if (empty($ungrouped_students)): ?>
                                <p>No ungrouped students available.</p>
                            <?php else: ?>
                                <?php foreach ($ungrouped_students as $student): ?>
                                    <div class="student-select-item">
                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" 
                                               id="student_<?php echo $student['id']; ?>" class="student-checkbox">
                                        <label for="student_<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            (<?php echo htmlspecialchars($student['section']); ?>)
                                        </label>
                                        <select name="student_roles[]" class="role-selector" disabled>
                                            <option value="member">Member</option>
                                            <option value="leader">Leader</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="createGroup()" <?php echo empty($ungrouped_students) ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> Create Group
                </button>
                <button class="btn-secondary" onclick="closeCreateGroupModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div id="editGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Group</h3>
                <span class="close" onclick="closeEditGroupModal()">&times;</span>
            </div>
            <div class="modal-body" id="editGroupModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="confirmModalTitle">Confirm Action</h3>
                <span class="close" onclick="closeConfirmModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-danger" id="confirmActionBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button class="btn-secondary" onclick="closeConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="../JS/advisor_thesis-group.js"></script>
   
</body>
</html>