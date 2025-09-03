<?php
session_start();
require_once '../db/db.php';
// Optional: Prevent PHP from outputting warnings to the browser
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Check coordinator session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    header('Location: ../login.php');
    exit();
}


// ==================== V7 UPDATE 
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



// === Handle Advisor CRUD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_advisor':
            $first_name = sanitize($_POST['first_name']);
            $middle_name = sanitize($_POST['middle_name'] ?? '');
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email']);
            $specialization = sanitize($_POST['specialization']);
            $section = sanitize($_POST['section_handled']);

            if (!$first_name || !$last_name || !$email || !$specialization || !$section) {
                echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
                exit();
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
                exit();
            }

            // Check for duplicate email
            $stmt = $pdo->prepare("SELECT id FROM advisors WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists.']);
                exit();
            }

            try {
                $pdo->beginTransaction();

                $tempPassword = 'advisor' . rand(1000, 9999);
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                // Generate employee ID
                $stmt = $pdo->query("SELECT COUNT(*) AS count FROM advisors");
                $count = $stmt->fetch()['count'];
                $employeeId = 'EMP-' . date('Y') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

                $department = explode('-', $section)[0]; // Assumes "BSIT-1A" -> "BSIT"

                // Insert into advisors table
                $stmt = $pdo->prepare("
                    INSERT INTO advisors (
                        first_name, middle_name, last_name, email, password, employee_id,
                        status, requires_password_change, year_handled,
                        sections_handled, department, specialization
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 
                        'active', 1, ?,
                        ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $first_name, $middle_name, $last_name, $email, 
                    $hashedPassword, $employeeId,
                    substr($section, -1), // Extract year level from section (e.g., "4" from "BSCS-4A")
                    $section, $department, $specialization
                ]);

                $advisorId = $pdo->lastInsertId();

                // Insert into advisor_sections table
                $stmt = $pdo->prepare("
                    INSERT INTO advisor_sections (advisor_id, section, course)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$advisorId, $section, $department]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Advisor added successfully!',
                    'temp_password' => $tempPassword,
                    'employee_id' => $employeeId,
                    'email' => $email
                ]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit();

        case 'edit_advisor':
            $id = (int)$_POST['id'];
            $first_name = sanitize($_POST['first_name']);
            $middle_name = sanitize($_POST['middle_name'] ?? '');
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email']);
            $specialization = sanitize($_POST['specialization']);
            $section = sanitize($_POST['section_handled']);

            if (!$first_name || !$last_name || !$email || !$specialization || !$section) {
                echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
                exit();
            }

            // Check if email is used by another advisor
            $stmt = $pdo->prepare("SELECT id FROM advisors WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists for another advisor.']);
                exit();
            }

            try {
                $pdo->beginTransaction();
                $department = explode('-', $section)[0];
                $year_handled = substr($section, -1); // Extract year level from section

                // Update advisor table
                $stmt = $pdo->prepare("
                    UPDATE advisors 
                    SET first_name = ?, middle_name = ?, last_name = ?, email = ?, 
                        sections_handled = ?, department = ?, specialization = ?, year_handled = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([
                    $first_name, $middle_name, $last_name, $email,
                    $section, $department, $specialization, $year_handled, $id
                ]);

                // Check if section exists for this advisor
                $stmt = $pdo->prepare("SELECT id FROM advisor_sections WHERE advisor_id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->fetch()) {
                    // Update existing section
                    $stmt = $pdo->prepare("
                        UPDATE advisor_sections 
                        SET section = ?, course = ?
                        WHERE advisor_id = ?
                    ");
                    $stmt->execute([$section, $department, $id]);
                } else {
                    // Insert new section
                    $stmt = $pdo->prepare("
                        INSERT INTO advisor_sections (advisor_id, section, course)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$id, $section, $department]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Advisor updated successfully.']);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to update advisor: ' . $e->getMessage()]);
            }
            exit();

        case 'delete_advisor':
            $id = (int)$_POST['id'];

            try {
                $pdo->beginTransaction();

                // Check if advisor has any groups assigned
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_groups WHERE advisor_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete advisor assigned to groups.']);
                    exit();
                }

                // Check if advisor has any students assigned
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE advisor_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete advisor assigned to students.']);
                    exit();
                }

                // First delete from advisor_sections
                $stmt = $pdo->prepare("DELETE FROM advisor_sections WHERE advisor_id = ?");
                $stmt->execute([$id]);

                // Then delete from advisors
                $stmt = $pdo->prepare("DELETE FROM advisors WHERE id = ?");
                $stmt->execute([$id]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Advisor deleted successfully.']);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to delete advisor: ' . $e->getMessage()]);
            }
            exit();

        case 'get_advisor':
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("
                    SELECT a.*, asec.section, asec.course
                    FROM advisors a
                    LEFT JOIN advisor_sections asec ON a.id = asec.advisor_id
                    WHERE a.id = ?
                ");
                $stmt->execute([$id]);
                $advisor = $stmt->fetch();

                if ($advisor) {
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'first_name' => $advisor['first_name'],
                            'middle_name' => $advisor['middle_name'],
                            'last_name' => $advisor['last_name'],
                            'email' => $advisor['email'],
                            'specialization' => $advisor['specialization'],
                            'section_handled' => $advisor['section']
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Advisor not found.']);
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to fetch advisor data.']);
            }
            exit();

        case 'add_section':
            $advisor_id = (int)$_POST['advisor_id'];
            $section = sanitize($_POST['section']);
            $course = explode('-', $section)[0]; // Extract course from section (e.g., "BSCS" from "BSCS-3A")

            if (!$advisor_id || !$section) {
                echo json_encode(['success' => false, 'message' => 'Please provide all required fields.']);
                exit();
            }

            try {
                // Check if advisor exists
                $stmt = $pdo->prepare("SELECT id FROM advisors WHERE id = ?");
                $stmt->execute([$advisor_id]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Advisor not found.']);
                    exit();
                }

                // Check if section already assigned to this advisor
                $stmt = $pdo->prepare("SELECT id FROM advisor_sections WHERE advisor_id = ? AND section = ?");
                $stmt->execute([$advisor_id, $section]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'This section is already assigned to this advisor.']);
                    exit();
                }

                // Add new section
                $stmt = $pdo->prepare("
                    INSERT INTO advisor_sections (advisor_id, section, course)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$advisor_id, $section, $course]);

                // Update advisor's sections_handled field
                $stmt = $pdo->prepare("
                    UPDATE advisors 
                    SET sections_handled = CONCAT(IFNULL(sections_handled, ''), IF(sections_handled IS NULL, '', ', '), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$section, $advisor_id]);

                echo json_encode(['success' => true, 'message' => 'Section added successfully.']);
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to add section: ' . $e->getMessage()]);
            }
            exit();
    }
}

// === Sorting Configuration ===
$valid_sort_columns = [
    'advisor_name' => 'a.last_name',
    'email' => 'a.email',
    'employee_id' => 'a.employee_id',
    'assigned_section' => 'a.sections_handled',
    'specialization' => 'a.specialization',
    'total_groups' => 'total_groups',
    'total_students' => 'total_students',
    'status' => 'a.status'
];

// Get sorting parameters from URL
$current_sort = $_GET['sort'] ?? 'advisor_name';
$current_order = $_GET['order'] ?? 'asc';
$search_term = $_GET['search'] ?? '';
$current_page = $_GET['page'] ?? 1;

// Validate sorting parameters
$sort_column = $valid_sort_columns[$current_sort] ?? $valid_sort_columns['advisor_name'];
$sort_order = strtolower($current_order) === 'desc' ? 'DESC' : 'ASC';

// Function to generate sort URL
function getSortUrl($column, $current_sort, $current_order, $search_term, $current_page) {
    $order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    return '?sort=' . $column . 
           '&order=' . $order . 
           '&search=' . urlencode($search_term) . 
           '&page=' . $current_page;
}

// Function to display sort arrows
function getSortArrows($column, $current_sort, $current_order) {
    if ($column === $current_sort) {
        $arrow = $current_order === 'asc' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow" title="Sorted"></i>';
    }
    return '<i class="fas fa-sort neutral-arrow" title="Click to sort"></i>';
}

// === Fetch Advisors List with Sorting ===
try {
    // Get all assigned sections
    $stmt = $pdo->query("SELECT section FROM advisor_sections");
    $assignedSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get advisors with sorting applied
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            CONCAT(a.first_name, ' ', IFNULL(a.middle_name, ''), ' ', a.last_name) AS full_name,
            COUNT(DISTINCT sg.id) AS total_groups,
            COUNT(DISTINCT s.id) AS total_students
        FROM advisors a
        LEFT JOIN student_groups sg ON a.id = sg.advisor_id
        LEFT JOIN students s ON a.id = s.advisor_id
        GROUP BY a.id
        ORDER BY $sort_column $sort_order, a.last_name, a.first_name
    ");
    $stmt->execute();
    $advisors = $stmt->fetchAll();

    // Get all sections for each advisor
    $stmt = $pdo->prepare("
        SELECT advisor_id, GROUP_CONCAT(section SEPARATOR ', ') AS sections, 
               GROUP_CONCAT(course SEPARATOR ', ') AS courses
        FROM advisor_sections
        GROUP BY advisor_id
    ");
    $stmt->execute();
    $advisorSections = [];
    while ($row = $stmt->fetch()) {
        $advisorSections[$row['advisor_id']] = $row;
    }

    // Merge section data into advisors array
    foreach ($advisors as &$advisor) {
        $advisor['sections'] = $advisorSections[$advisor['id']]['sections'] ?? 'Not Assigned';
        $advisor['courses'] = $advisorSections[$advisor['id']]['courses'] ?? '';
    }
    unset($advisor); // Break the reference

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $advisors = [];
    $assignedSections = [];
}

// === Pagination ===
$itemsPerPage = 5;
$totalAdvisors = count($advisors);
$totalPages = ceil($totalAdvisors / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$startIndex = ($currentPage - 1) * $itemsPerPage;
$paginatedAdvisors = array_slice($advisors, $startIndex, $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/coordinator_advisor-mngt.css">
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
                <div class="sidebar-user">
                     <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
         class="sidebar-avatar" 
         alt="User Avatar" 
         id="currentProfilePicture" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
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
                <a href="coordinator_thesis-groups.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Thesis Groups
                </a>
                <a href="coordinator_advisor-mngt.php" class="nav-item active" data-tab="advisors">
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
                            <button id="confirmLogout" class="logoutbtn">Yes, Logout</button>
                            <button id="cancelLogout" class="btn btn-secondary">Cancel</button>
                        </div>
                    </div>
                </div>
            </nav>
        </aside>
        <!-- End Sidebar -->

        <!-- Start Content Wrapper -->
        <div class="content-wrapper">
            <!-- Start HEADER -->
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
                </div>
            </header>
            <!-- End HEADER -->

            <!-- Main Content -->
            <main class="main-content">
                <!-- Message container for notifications -->
                <div id="messageContainer"></div>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-chalkboard-teacher"></i> CICT Advisor Management</h1>
                    <p>Manage advisor assignments and workloads across CICT programs</p>
                </div>

                <!-- Content Container -->
                <div class="content-container">
                    <!-- Advisors Tab -->
                    <div id="advisors" class="tab-content">
                        <div class="card">
                            <h3><i class="fas fa-users-cog"></i> Advisor Directory</h3>
                            
                            <div class="action-section">
                                <button class="btn-primary" onclick="addNewAdvisor()">
                                    <i class="fas fa-plus"></i> Add New Advisor
                                </button>
                            </div>
                            

                            <?php if (empty($advisors)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-plus"></i>
                                    <h3>No Advisors Found</h3>
                                    <p>Get started by adding your first advisor to the system.</p>
                                    <button class="btn-primary" onclick="addNewAdvisor()">
                                        <i class="fas fa-plus"></i> Add First Advisor
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-scroll">
                                    <table class="sections-table">


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

                                    <thead>
                                <tr>
                                <th>
                                    <a href="<?= getSortUrl('advisor_name', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Advisor Name <?= getSortArrows('advisor_name', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('email', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Email <?= getSortArrows('email', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('employee_id', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Employee ID <?= getSortArrows('employee_id', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('assigned_section', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Assigned Section <?= getSortArrows('assigned_section', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('specialization', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Specialization <?= getSortArrows('specialization', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('total_groups', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Total Groups <?= getSortArrows('total_groups', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('total_students', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Total Students <?= getSortArrows('total_students', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('status', $current_sort, $current_order, $search_term, $current_page) ?>">
                                        Status <?= getSortArrows('status', $current_sort, $current_order) ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                                    <tbody>
                                        <?php foreach ($paginatedAdvisors as $advisor): ?>

                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($advisor['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($advisor['email']); ?></td>
                                                <td><?php echo htmlspecialchars($advisor['employee_id'] ?? 'N/A'); ?></td>
                                            <td>
                                                    <div class="section-badges">
                                                        <?php 
                                                        if ($advisor['sections'] !== 'Not Assigned') {
                                                            $sections = explode(', ', $advisor['sections']);
                                                            $courses = explode(', ', $advisor['courses']);
                                                            foreach ($sections as $index => $section) {
                                                                $course = $courses[$index] ?? '';
                                                                echo '<span class="section-badge ' . strtolower($course) . '">' . 
                                                                    htmlspecialchars($section) . '</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="section-badge">Not Assigned</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($advisor['specialization'] ?? 'Not Specified'); ?></td>
                                                <td>
                                                    <span class="stat-number"><?php echo $advisor['total_groups']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="stat-number"><?php echo $advisor['total_students']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo ($advisor['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                                        <?php echo ucfirst($advisor['status'] ?? 'Active'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                <div class="advisor-dropdown" id="advisor-dropdown-<?php echo $advisor['id']; ?>">
                                                    <button class="advisor-dropdown-toggle" type="button" 
                                                            onclick="toggleAdvisorDropdown('advisor-dropdown-<?php echo $advisor['id']; ?>', event)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="advisor-dropdown-menu">
                                                    <a href="#" class="add-section" data-advisor-id="<?php echo $advisor['id']; ?>">
                                                    <i class="fas fa-add"></i> Add Section
                                                    </a>
                                                   <a href="#" class="edit-advisor" data-advisor-id="<?php echo $advisor['id']; ?>" 
                                                    onclick="editAdvisor(<?php echo $advisor['id']; ?>, event)">
                                                    <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="#" class="remove-advisor" data-advisor-id="<?php echo $advisor['id']; ?>" 
                                                    onclick="confirmRemoveAdvisor(<?php echo $advisor['id']; ?>, event)">
                                                    <i class="fas fa-trash"></i> Remove
                                                    </a>
                                                    </div>
                                                </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                        </div>    

                                <?php if ($totalPages > 1): ?>
                                    <div class="pagination">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <a class="page-link <?php echo $i == $currentPage ? 'active' : ''; ?>" 
                                            href="?page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>


                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Assign Additional Section -->
                    <div id="addSectionModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3><i class="fas fa-plus"></i> Assign Additional Section</h3>
                                <span class="close" onclick="closeAddSectionModal()">&times;</span>
                            </div>
                            <div class="modal-body">
                                <form id="addSectionForm">
                                    <input type="hidden" id="addSectionAdvisorId" name="advisor_id">
                                    <div class="form-group">
                                        <label for="newSection"><i class="fas fa-school"></i> Select Section:</label>
                                        <select id="newSection" name="section" required>
                                            <option value="">Select Section</option>
                                            <?php
                                            $allSections = [
                                                'BSCS-3A', 'BSCS-3B', 'BSCS-3C', 'BSCS-4A', 'BSCS-4B', 'BSCS-4C',
                                                'BSIS-3A', 'BSIS-3B', 'BSIS-3C', 'BSIS-4A', 'BSIS-4B', 'BSIS-4C'
                                            ];
                                            
                                            foreach ($allSections as $section) {
                                                $disabled = in_array($section, $assignedSections) ? 'disabled' : '';
                                                echo "<option value=\"$section\" $disabled>$section</option>";
                                            }
                                            ?>
                                        </select>
                                        <small class="form-text text-muted">
                                            Sections already assigned to advisors are disabled
                                        </small>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button class="btn-primary" onclick="saveNewSection()">
                                    <i class="fas fa-save"></i> Assign Section
                                </button>
                                <button class="btn-secondary" onclick="closeAddSectionModal()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                <!-- Add/Edit Advisor Modal -->
                <!-- Add/Edit Advisor Modal -->
                <div id="advisorModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="advisorModalTitle"><i class="fas fa-user-plus"></i> Add New Advisor</h3>
                            <span class="close" onclick="closeModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="advisorForm">
                                <input type="hidden" id="advisorId" name="id">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="advisorFirstName"><i class="fas fa-user"></i> First Name:</label>
                                        <input type="text" id="advisorFirstName" name="first_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="advisorMiddleName"><i class="fas fa-user"></i> Middle Name:</label>
                                        <input type="text" id="advisorMiddleName" name="middle_name">
                                    </div>
                                    <div class="form-group">
                                        <label for="advisorLastName"><i class="fas fa-user"></i> Last Name:</label>
                                        <input type="text" id="advisorLastName" name="last_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="advisorEmail"><i class="fas fa-envelope"></i> Email:</label>
                                        <input type="email" id="advisorEmail" name="email" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="advisorSpecialization"><i class="fas fa-graduation-cap"></i> Specialization:</label>
                                        <select id="advisorSpecialization" name="specialization" required>
                                            <option value="">Select Specialization</option>
                                            <option value="Information Systems">Information Systems</option>
                                            <option value="Computer Science">Computer Science</option>
                                            <option value="AI and Machine Learning">AI and Machine Learning</option>
                                            <option value="Software Engineering">Software Engineering</option>
                                            <option value="Cybersecurity">Cybersecurity</option>
                                            <option value="Data Science">Data Science</option>
                                            <option value="Business Analytics">Business Analytics</option>
                                            <option value="Web Development">Web Development</option>
                                            <option value="Mobile Development">Mobile Development</option>
                                            <option value="Database Management">Database Management</option>
                                            <option value="Network Administration">Network Administration</option>
                                            <option value="Human-Computer Interaction">Human-Computer Interaction</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="advisorSection"><i class="fas fa-school"></i> Assign to Section:</label>
                                        <select id="advisorSection" name="section_handled" required>
                                            <option value="">Select Section</option>
                                            <?php
                                        foreach ($allSections as $section) {
                                            // Allow currently assigned section but disable others that are assigned
                                            $disabled = (in_array($section, $assignedSections) && $section != ($advisor['section'] ?? '')) 
                                                ? 'disabled' 
                                                : '';
                                            echo "<option value=\"$section\" $disabled>$section</option>";
                                        }
                                        ?>
                                        </select>
                                    </div>
                                </div>

                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-primary" onclick="saveAdvisor()">
                                <i class="fas fa-save"></i> Save Advisor
                            </button>
                            <button class="btn-secondary" onclick="closeModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
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

                <!-- Password Display Modal -->
                <div id="passwordModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><i class="fas fa-check-circle" style="color: #48bb78;"></i> Advisor Account Created Successfully!</h3>
                            <span class="close" onclick="closePasswordModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div style="text-align: center; margin-bottom: 2rem;">
                                <i class="fas fa-check-circle" style="font-size: 4rem; color: #48bb78; margin-bottom: 1rem;"></i>
                                <p style="font-size: 1.2rem;"><strong>The advisor account has been created successfully!</strong></p>
                            </div>
                            
                            <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 2rem; border-radius: 12px; margin: 1rem 0; border-left: 4px solid #48bb78;">
                                <h4 style="margin-bottom: 1.5rem; color: #2d3748; font-size: 1.25rem;">
                                    <i class="fas fa-key"></i> Login Credentials:
                                </h4>
                                <div style="display: grid; gap: 1rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: white; border-radius: 8px;">
                                        <span style="font-weight: 600;"><i class="fas fa-envelope"></i> Email:</span>
                                        <span id="createdEmail" style="font-family: 'Courier New', monospace; background: #e2e8f0; padding: 0.5rem; border-radius: 6px; font-weight: 500;"></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: white; border-radius: 8px;">
                                        <span style="font-weight: 600;"><i class="fas fa-lock"></i> Temporary Password:</span>
                                        <span id="tempPassword" style="font-family: 'Courier New', monospace; background: #fed7d7; padding: 0.5rem; border-radius: 6px; font-weight: bold; color: #c53030;"></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: white; border-radius: 8px;">
                                        <span style="font-weight: 600;"><i class="fas fa-id-badge"></i> Employee ID:</span>
                                        <span id="employeeId" style="font-family: 'Courier New', monospace; background: #e2e8f0; padding: 0.5rem; border-radius: 6px; font-weight: 500;"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="background: linear-gradient(135deg, #fef5e7, #fed7aa); padding: 1.5rem; border-radius: 12px; border-left: 4px solid #f59e0b;">
                                <p style="color: #92400e; font-size: 1rem; margin: 0; line-height: 1.6;">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Important:</strong> Please share these credentials with the advisor. They will be required to change their password on first login for security purposes.
                                </p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-secondary" onclick="copyCredentials()">
                                <i class="fas fa-copy"></i> Copy Credentials
                            </button>
                            <button class="btn-primary" onclick="closePasswordModal()">
                                <i class="fas fa-check"></i> Got it
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../JS/coordinator_advisor-mngt.js"></script>
</body>
</html>
