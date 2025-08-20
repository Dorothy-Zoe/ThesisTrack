<?php
session_start();
require_once 'db/db.php';

$error = '';
$success = '';

// ✅ Corrected role check
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'coordinator') {
    header('Location: Coordinator/coordinator_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Query from `coordinators` table
            $stmt = $pdo->prepare("SELECT * FROM coordinators WHERE email = ?");
            $stmt->execute([$email]);
            $coordinator = $stmt->fetch();

            if (!$coordinator) {
                $error = 'No user found with that email.';
            } else {

                if ($password === $coordinator['password']) {
                    // ✅ Set session variables
                    $_SESSION['user_id'] = $coordinator['id'];
                    $_SESSION['role'] = 'coordinator'; // consistent role
                    $_SESSION['coordinator_id'] = $coordinator['coordinator_id'];
                    $_SESSION['name'] = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
                    $_SESSION['email'] = $coordinator['email'];
                    $_SESSION['profile_picture'] = $coordinatorr['profile_picture'];

                    // ✅ Update last login
                    $updateStmt = $pdo->prepare("UPDATE coordinators SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$coordinatorr['id']]);

                    // ✅ Redirect to dashboard
                    header('Location: Coordinator/coordinator_dashboard.php');
                    exit();
                } else {
                    $error = 'Incorrect password.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again later.';
        }
    }
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
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="CSS/login.css">
    <title>Login - ThesisTrack</title>
    
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ThesisTrack</h1>
            <p>College of Information and Communication Technology</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
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

        <div class="demo-accounts">
            <!-- <h4><i class="fas fa-key"></i> Demo Accounts</h4>

            <div class="demo-account" onclick="fillCredentials('coordinator@cict.edu', 'coordinator123')">
                <strong>Coordinator:</strong> coordinator@cict.edu / coordinator123
            </div> -->
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
