<?php
// Include config and user class
require_once 'config.php';
require_once 'includes/User.php';

$error_message = '';
$success_message = '';

// Handle signup form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = new User($conn);
    $fullName = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $password_re = $_POST['password_re'];
    $dob = $_POST['date_of_birth'];
    $profilePicFile = $_FILES['profile_picture'];
    if ($password !== $password_re) {
        $error_message = "Passwords do not match.";
    } else {
        $result = $user->register($fullName, $email, $password, $dob, $profilePicFile);
        if ($result === true) {
            header("Location: login.php?status=success");
            exit();
        } else {
            $error_message = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Social Network</title>
    <!-- You should link to your actual CSS file -->
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <div class="form-container">
    <h1>Join Social Network</h1>
        
        <!-- Display error message if it exists -->
        <?php if (!empty($error_message)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- The 'enctype' is CRITICAL for file uploads -->
        <form id="signupForm" action="signup.php" method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_re">Re-enter Password</label>
                <input type="password" id="password_re" name="password_re" required>
            </div>

            <div class="form-group">
                <label for="profile_picture">Upload Profile Picture</label>
                <input type="file" id="profile_picture" name="profile_picture" required accept="image/*">
            </div>

            <button type="submit" class="btn-primary">Sign Up</button>
        </form>
    <p class="form-link">Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>
