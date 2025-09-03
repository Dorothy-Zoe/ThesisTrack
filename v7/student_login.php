<?php
session_start();
require_once 'db/db.php';

$error = '';
$success = '';

// ✅ Handle new password change if required
if (isset($_SESSION['requires_password_change']) && $_SESSION['requires_password_change'] == 1 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE students SET password = ?, requires_password_change = 0 WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
            $_SESSION['requires_password_change'] = 0;
            header("Location: Student/student_dashboard.php");
            exit();
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}

// ✅ Handle login
if (!isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if (!$student) {
                $error = 'No user found with that email.';
            } elseif (password_verify($password, $student['password'])) {
                $_SESSION['user_id'] = $student['id'];
                $_SESSION['role'] = 'student';
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['email'] = $student['email'];
                $_SESSION['course'] = $student['course'];
                $_SESSION['section'] = $student['section'];
                $_SESSION['year_level'] = $student['year_level'];
                $_SESSION['profile_picture'] = $student['profile_picture'];
                $_SESSION['requires_password_change'] = $student['requires_password_change'];

                $updateStmt = $pdo->prepare("UPDATE students SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$student['id']]);

                if ($student['requires_password_change']) {
                    // Stay on this page to show password change form
                } else {
                    header("Location: Student/student_dashboard.php");
                    exit();
                }
            } else {
                $error = 'Incorrect password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again later.';
        }
    }
}

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student' && (!isset($_SESSION['requires_password_change']) || $_SESSION['requires_password_change'] == 0)) {
    header('Location: Student/student_dashboard.php');
    exit();
}

if (isset($_GET['success']) && $_GET['success'] === 'logout') {
    $success = 'You have been logged out successfully.';
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ThesisTrack</title>
    <link rel="stylesheet" href="CSS/login.css">
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ThesisTrack</h1>
            <p>College of Information and Communication Technology</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- ✅ Change Password Form -->
        <?php if (isset($_SESSION['requires_password_change']) && $_SESSION['requires_password_change'] == 1): ?>
            <form method="POST" action="">
                <h3>Change Your Temporary Password</h3>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>

                <button type="submit" class="btn-login">Update Password</button>
            </form>

        <!-- ✅ Regular Login Form -->
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required>
                        <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>
        <?php endif; ?>

        <div class="demo-accounts">
            <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                <strong>Note:</strong> Professor and Student accounts are now created through the system by the Coordinator and Professors respectively.
            </p>
        </div>

        <div class="links">
            <a href="portal.php">← Back to Home</a>
        </div>
    </div>

    <script src="JS/login.js"></script>
</body>
</html>
