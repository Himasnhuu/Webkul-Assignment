<?php
// Include config and user class
require_once 'config.php';
require_once 'includes/User.php';

// Redirect logged-in users to profile
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$error_message = '';
$success_message = '';

// Show success message after registration
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $success_message = 'Registration successful! Please log in.';
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = new User($conn);
    $email = $_POST['email'];
    $password = $_POST['password'];
    if ($user->login($email, $password)) {
        header("Location: profile.php");
        exit();
    } else {
        $error_message = "Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Network Login</title>
    <!-- You should link to your actual CSS file -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="form-container">
        <h1>Social Network Login</h1>
        <?php if (!empty($error_message)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="success-box"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary">Login</button>
        </form>
        <p class="form-link">Don’t have account? <a href="signup.php">Create Account</a></p>
    </div>
</body>
</html>
