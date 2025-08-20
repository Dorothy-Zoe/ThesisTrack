<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header('Location: advisor_login.php');
    exit();
}

$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['name'] ?? 'Advisor';

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
            $section = sanitize($_POST['section'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, and section are required.']);
                exit();
            }

            try {
                $year = date('Y');
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE course = ?");
                $stmt->execute([$advisor_course]);
                $count = $stmt->fetch()['count'];
                $student_id = $year . '-' . $advisor_course . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                $email_name = strtolower(str_replace(' ', '.', $first_name . '.' . $last_name));
                $email = $email_name . '@student.cict.edu';

                $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $counter = 1;
                    do {
                        $email = $email_name . $counter . '@student.cict.edu';
                        $stmt->execute([$email]);
                        $counter++;
                    } while ($stmt->fetch());
                }

                $temp_password = 'student' . rand(1000, 9999);
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
            $section = sanitize($_POST['section'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, and section are required.']);
                exit();
            }

            try {
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET first_name = ?, middle_name = ?, last_name = ?, section = ?
                    WHERE id = ? AND advisor_id = ?
                ");
                $stmt->execute([$first_name, $middle_name, $last_name, $section, $student_id, $advisor_id]);

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

// Fetch students assigned to this advisor
try {
    $stmt = $pdo->prepare("
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
        WHERE s.advisor_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$advisor_id]);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
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
                <div class="sidebar-user"><img src="../images/default-user.png" class="image-sidebar-avatar" />
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
                                <img src="../images/default-user.png" alt="Avatar" class="headerAvatar" id="headerAvatar" tabindex="0" />
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
                            <button class="btn-primary" onclick="addNewStudent()" <?php echo !$advisor_section ? 'disabled' : ''; ?>>
                                <i class="fas fa-plus"></i> Add New Student
                            </button>
                            <div class="section-info">
                                <span class="info-badge">Section: <?php echo htmlspecialchars($advisor_section); ?></span>
                                <span class="info-badge">Course: <?php echo htmlspecialchars($advisor_course); ?></span>
                                <span class="info-badge">Total Students: <?php echo count($students); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Students Table -->

                    <?php
                    $students_per_page = 4; // How many students per page
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
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Section</th>
                                    <th>Group Assignment</th>
                                    <th>Status</th>
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

                         <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                                    <a class="page-link <?php echo ($page == $current_page) ? 'active' : ''; ?>" 
                                    href="?page=<?php echo $page; ?>">
                                    <?php echo $page; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Add/Edit Student Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="studentModalTitle">Add New Student</h3>
                <span class="close" onclick="closeStudentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="studentForm">
                    <input type="hidden" id="studentId" name="student_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middle_name">
                        </div>
                        <div class="form-group">
                            <label>Section *</label>
                            <div class="form-group">

                            <select name="section" id="sectionSelect" required>
                                <option value="">Select Section</option>
                                <?php foreach ($available_sections as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="saveStudent()">
                    <i class="fas fa-save"></i> Save Student
                </button>
                <button class="btn-secondary" onclick="closeStudentModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Student Credentials Modal -->
    <div id="credentialsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle text-success"></i> Student Account Created!</h3>
                <span class="close" onclick="closeCredentialsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="success-message">
                    <i class="fas fa-graduation-cap"></i>
                    <p>The student account has been created successfully!</p>
                </div>
                
                <div class="credentials-box">
                    <h4><i class="fas fa-key"></i> Login Credentials</h4>
                    <div class="credential-item">
                        <label>Student Name:</label>
                        <span id="credentialName"></span>
                    </div>
                    <div class="credential-item">
                        <label>Student ID:</label>
                        <span id="credentialStudentId"></span>
                    </div>
                    <div class="credential-item">
                        <label>Email:</label>
                        <span id="credentialEmail"></span>
                    </div>
                    <div class="credential-item">
                        <label>Temporary Password:</label>
                        <span id="credentialPassword" class="password-highlight"></span>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important:</strong> Please share these credentials with the student. They will be required to change their password on first login.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="copyCredentials()">
                    <i class="fas fa-copy"></i> Copy Credentials
                </button>
                <button class="btn-primary" onclick="closeCredentialsModal()">
                    <i class="fas fa-check"></i> Got it
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

    <script src="../JS/advisor_student-management.js"></script>
</body>
</html>
