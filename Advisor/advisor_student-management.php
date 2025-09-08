<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header('Location: advisor_login.php');
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

// Get advisor's section and course
try {
    $stmt = $pdo->prepare("SELECT sections_handled, department FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor_info = $stmt->fetch();
    $advisor_section = $advisor_info['sections_handled'] ?? null;
    $advisor_course = $advisor_info['department'] ?? null;
    $available_sections = [];

    if (!empty($advisor_section)) {
        $available_sections = array_map('trim', explode(',', $advisor_section));
    }
} catch (PDOException $e) {
    $advisor_section = null;
    $advisor_course = null;
    $available_sections = [];
}

// ================== CSV IMPORT/EXPORT FUNCTIONALITY ================== //

// Handle CSV export (template or data)
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $type = $_GET['type'] ?? 'template';
    
    if ($type === 'template') {
        // Generate CSV template for import
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=student_import_template.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['first_name', 'last_name', 'middle_name', 'email', 'section']);
        fclose($output);
        exit();
    } elseif ($type === 'data') {
        // Export current student data
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=student_data_export.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['student_id', 'first_name', 'last_name', 'middle_name', 'email', 'section', 'status', 'group_assignment']);
        
        try {
            $stmt = $pdo->prepare("
                SELECT s.student_id, s.first_name, s.last_name, s.middle_name, s.email, s.section, s.status,
                       GROUP_CONCAT(g.title SEPARATOR ', ') as group_title
                FROM students s
                LEFT JOIN group_members gm ON s.id = gm.student_id
                LEFT JOIN groups g ON gm.group_id = g.id
                WHERE s.advisor_id = ?
                GROUP BY s.id
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$advisor_id]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['student_id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['middle_name'] ?? '',
                    $row['email'],
                    $row['section'],
                    $row['status'],
                    $row['group_title'] ?? 'Not Assigned'
                ]);
            }
        } catch (PDOException $e) {
            // Log error but continue with empty export
            error_log("Export error: " . $e->getMessage());
        }
        
        fclose($output);
        exit();
    }
}

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    header('Content-Type: application/json');
    
    if (!$advisor_section || !$advisor_course) {
        echo json_encode(['success' => false, 'message' => 'You must be assigned to a section and course.']);
        exit();
    }
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file.']);
        exit();
    }
    
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Failed to open the uploaded file.']);
        exit();
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    
    $imported = 0;
    $errors = [];
    $line = 1;
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        $line++;
        
        // Validate required fields
        if (count($data) < 5) {
            $errors[] = "Line $line: Insufficient data columns (need: first_name, last_name, middle_name, email, section)";
            continue;
        }
        
        $first_name = sanitize(trim($data[0]));
        $last_name = sanitize(trim($data[1]));
        $middle_name = sanitize(trim($data[2] ?? ''));
        $email = sanitize(trim($data[3]));
        $section = sanitize(trim($data[4]));
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
            $errors[] = "Line $line: Missing required fields (first name, last name, email, or section)";
            continue;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Line $line: Invalid email format '$email'";
            continue;
        }
        
        // Validate section is in available sections
        if (!in_array($section, $available_sections)) {
            $errors[] = "Line $line: Invalid section '$section'. Must be one of: " . implode(', ', $available_sections);
            continue;
        }
        
        try {
            // Check if student already exists by email
            $check_stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
            $check_stmt->execute([$email]);
            
            if ($check_stmt->fetch()) {
                $errors[] = "Line $line: Student with email '$email' already exists";
                continue;
            }
            
            // Generate student ID
            $year = date('Y');
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE course = ?");
            $count_stmt->execute([$advisor_course]);
            $count = $count_stmt->fetch()['count'];
            $student_id = $year . '-' . $advisor_course . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            // Fixed default password
            $temp_password = 'student1234';
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Insert student
            $insert_stmt = $pdo->prepare("
                INSERT INTO students 
                (first_name, middle_name, last_name, email, password, student_id, year_level, section, course, status, profile_picture, advisor_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 3, ?, ?, 'active', '', ?, NOW())
            ");
            
            $insert_stmt->execute([
                $first_name, $middle_name, $last_name, $email, $hashed_password,
                $student_id, $section, $advisor_course, $advisor_id
            ]);
            
            $imported++;
            
        } catch (PDOException $e) {
            $errors[] = "Line $line: Database error - " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    if ($imported > 0) {
        $message = "Successfully imported $imported students.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors occurred.";
        }
        echo json_encode(['success' => true, 'message' => $message, 'errors' => $errors]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No students were imported.', 'errors' => $errors]);
    }
    
    exit();
}

// ================== version 7 update here  ================== //

function getSortArrows($current_col, $sort_col, $sort_order) {
    if ($current_col == $sort_col) {
        // Active sorting - show caret up/down
        $arrow = $sort_order == 'ASC' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow"></i>';
    }
    // Neutral state - show sort icon
    return '<i class="fas fa-sort neutral-arrow"></i>';
}

// Sorting functionality
$sort_column = $_GET['sort'] ?? 'last_name';
$sort_order = $_GET['order'] ?? 'asc';
$search_term = $_GET['search'] ?? '';
$entries_per_page = $_GET['entries'] ?? 5; 

// Validate sort column and order
$valid_columns = ['student_id', 'first_name', 'last_name', 'email', 'section', 'group_count', 'status'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'last_name';
}
$sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

// ================== end of version 7 update here ================== //

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_student':
            if (!$advisor_section || !$advisor_course) {
                echo json_encode(['success' => false, 'message' => 'You must be assigned to a section and course.']);
                exit();
            }

            $first_name = sanitize($_POST['first_name']);
            $middle_name = sanitize($_POST['middle_name'] ?? '');
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email'] ?? '');
            $section = sanitize($_POST['section'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, email, and section are required.']);
                exit();
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
                exit();
            }

            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists.']);
                    exit();
                }
                
                $year = date('Y');
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE course = ?");
                $stmt->execute([$advisor_course]);
                $count = $stmt->fetch()['count'];
                $student_id = $year . '-' . $advisor_course . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                // Fixed default password
                $temp_password = 'student1234';
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO students 
                    (first_name, middle_name, last_name, email, password, student_id, year_level, section, course, status, profile_picture, advisor_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 3, ?, ?, 'active', '', ?, NOW())
                ");
                $stmt->execute([
                    $first_name, $middle_name, $last_name, $email, $hashed_password,
                    $student_id, $section, $advisor_course, $advisor_id
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Student added successfully!',
                    'student_data' => [
                        'name' => $first_name . ' ' . $middle_name . ' ' . $last_name,
                        'email' => $email,
                        'student_id' => $student_id,
                        'temp_password' => $temp_password
                    ]
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to add student.']);
            }
            exit();

        case 'edit_student':
            $student_id = (int)$_POST['student_id'];
            $first_name = sanitize($_POST['first_name']);
            $middle_name = sanitize($_POST['middle_name'] ?? '');
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email'] ?? '');
            $section = sanitize($_POST['section'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, email, and section are required.']);
                exit();
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
                exit();
            }

            try {
                // Check if email already exists for another student
                $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
                $stmt->execute([$email, $student_id]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists for another student.']);
                    exit();
                }

                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET first_name = ?, middle_name = ?, last_name = ?, email = ?, section = ?
                    WHERE id = ? AND advisor_id = ?
                ");
                $stmt->execute([$first_name, $middle_name, $last_name, $email, $section, $student_id, $advisor_id]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Student updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No changes made or permission denied.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Update failed.']);
            }
            exit();

        case 'delete_student':
            $student_id = (int)$_POST['student_id'];

            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM group_members WHERE student_id = ?");
                $stmt->execute([$student_id]);
                $group_count = $stmt->fetch()['count'];

                if ($group_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student is part of a group.']);
                    exit();
                }

                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND advisor_id = ?");
                $stmt->execute([$student_id, $advisor_id]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Student deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found or permission denied.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Deletion failed.']);
            }
            exit();
    }
}

// Fetch students assigned to this advisor with sorting and searching
try {
    $search_condition = '';
    $params = [$advisor_id];
    
    if (!empty($search_term)) {
        $search_condition = " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.section LIKE ?)";
        $search_param = "%$search_term%";
        array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param);
    }

    $query = "
        SELECT s.*, 
               CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) AS full_name,
               COALESCE(g.group_count, 0) as group_count,
               g.group_title
        FROM students s
        LEFT JOIN (
            SELECT gm.student_id, 
                   COUNT(gm.group_id) as group_count,
                   GROUP_CONCAT(gr.title SEPARATOR ', ') as group_title
            FROM group_members gm
            JOIN groups gr ON gm.group_id = gr.id
            GROUP BY gm.student_id
        ) g ON s.id = g.student_id
        WHERE s.advisor_id = ? $search_condition
        ORDER BY $sort_column $sort_order
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(); // Store all results in $all_students
    
    // Pagination
    $total_students = count($students);
    $total_pages = ceil($total_students / $entries_per_page);
    $current_page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
    $offset = ($current_page - 1) * $entries_per_page;
    $paginated_students = array_slice($students, $offset, $entries_per_page);
} catch (PDOException $e) {
    $students = [];
    $paginated_students = [];
    error_log("Database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_student-management.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">

    
        <!-- Sidebar -->
       <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($advisor_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item active" data-tab="students">
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


        <!-- Content Wrapper -->
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
            </header>
        <!-- End Header -->
            <!-- Main Content -->
            <main class="main-content">
                <!-- Message container -->
                <div id="messageContainer"></div>

                <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-user-graduate"></i> Student Management</h1>
                    <p>Manage students in your assigned section: <?php echo htmlspecialchars($advisor_section ?? 'Not Assigned'); ?></p>
                </div>
                <!-- End of Page Title -->

                <!-- Student Management Card -->
                <div class="card">
                    <h3><i class="fas fa-users"></i> List of Students</h3>
                    
                    <?php if (!$advisor_section): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Section Not Assigned:</strong> Please contact the coordinator to assign you to a section before adding students.
                        </div>
                    <?php else: ?>
                        <div class="action-section">
                           
                            
                            <!-- CSV Import/Export Buttons -->
                            <div class="csv-buttons">
                                <button class="btn-secondary" onclick="exportCSV('template')">
                                    <i class="fas fa-download"></i> Download Template
                                </button>
                                <button class="btn-secondary" onclick="showImportModal()">
                                    <i class="fas fa-upload"></i> Import CSV
                                </button>
                                <button class="btn-secondary" onclick="exportCSV('data')">
                                    <i class="fas fa-file-export"></i> Export Data
                                </button>
                            </div>
                            
                            <div class="section-info">
                                <span class="info-badge">Section: <?php echo htmlspecialchars($advisor_section); ?></span>
                                <span class="info-badge">Course: <?php echo htmlspecialchars($advisor_course); ?></span>
                                <span class="info-badge">Total Students: <?php echo count($students); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                     <!-- Show entries and Search-->
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
                                    
                                        <?php foreach ($_GET as $key => $value): ?>
                                            <?php if ($key !== 'search' && $key !== 'page'): ?>
                                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </form>
                            </div>

                    <!-- Students Table -->
                    <?php
                    $students_per_page = 5; // How many students per page
                    $total_students = count($students);
                    $total_pages = ceil($total_students / $students_per_page);
                    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $current_page = max(1, min($current_page, $total_pages)); 
                    $start_index = ($current_page - 1) * $students_per_page;
                    $paginated_students = array_slice($students, $start_index, $students_per_page);
                    ?>


                    <div class="table-container">
                    <table class="students-table">
                      <thead>
                        <tr>
                            <th>
                                <a href="?sort=student_id&order=<?= $sort_column == 'student_id' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Student ID <?= getSortArrows('student_id', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=last_name&order=<?= $sort_column == 'last_name' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Student Name <?= getSortArrows('last_name', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=email&order=<?= $sort_column == 'email' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Email <?= getSortArrows('email', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=section&order=<?= $sort_column == 'section' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Section <?= getSortArrows('section', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=group_count&order=<?= $sort_column == 'group_count' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Group Assignment <?= getSortArrows('group_count', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=status&order=<?= $sort_column == 'status' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Status <?= getSortArrows('status', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="7" class="no-data">
                                            <i class="fas fa-user-slash"></i>
                                            <p>No students found.</p>
                                            <?php if ($advisor_section): ?>
                                                <p>Click "Add New Student" to get started.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paginated_students as $student): ?>

                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo htmlspecialchars($student['section']); ?></td>
                                            <td>
                                                <?php if ($student['group_count'] > 0): ?>
                                                    <span class="group-badge assigned">
                                                        <i class="fas fa-users"></i> 
                                                        <?php echo htmlspecialchars($student['group_title']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="group-badge unassigned">
                                                         Not Assigned
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $student['status']; ?>">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-dropdown">
                                                    <button class="action-btn" onclick="toggleActionDropdown(<?php echo $student['id']; ?>)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="action-menu" id="actionMenu<?php echo $student['id']; ?>">
                                                        <a href="#" onclick="editStudent(<?php echo $student['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="#" onclick="deleteStudent(<?php echo $student['id']; ?>)" 
                                                           <?php echo $student['group_count'] > 0 ? 'class="disabled"' : ''; ?>>
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                     <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                            <a class="page-link <?= ($page == $current_page) ? 'active' : '' ?>" 
                            href="?page=<?= $page ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                            <?= $page ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                     <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    
<!-- Student Edit Modal -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="studentModalTitle">Edit Student</h3>
            <span class="close" onclick="closeStudentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="studentForm">
                <input type="hidden" id="studentId" name="student_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name *</label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middle_name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="lastName">Last Name *</label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="section">Section *</label>
                    <select id="section" name="section" required>
                        <option value="">Select Section</option>
                        <?php foreach ($available_sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-primary" onclick="saveStudent()">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button class="btn-secondary" onclick="closeStudentModal()">
                Cancel
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

    <!-- CSV Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Import Students from CSV</h3>
                <span class="close" onclick="closeImportModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <p><strong>CSV Format Requirements:</strong></p>
                    <ul>
                        <li>File is in CSV format</li>
                        <li>Sections must be one of: <?php echo implode(', ', $available_sections); ?></li>
                        
                    </ul>
                    <p><a href="javascript:void(0)" onclick="exportCSV('template')">Download template</a></p>
                </div>
                
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csvFile">Select CSV File</label>
                        <input type="file" id="csvFile" name="csv_file" accept=".csv" required>
                    </div>
                </form>
                
                <div id="importResults" style="display: none;">
                    <h4>Import Results</h4>
                    <div id="importSuccess" class="alert alert-success"></div>
                    <div id="importErrors" class="alert alert-error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="submitImport()">
                    <i class="fas fa-upload"></i> Import
                </button>
                <button class="btn-secondary" onclick="closeImportModal()">
                  Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="../JS/advisor_student-management.js"></script>
  
</body>
</html>