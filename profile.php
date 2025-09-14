<?php
// Include config, user, and post classes
require_once 'config.php';
require_once 'includes/User.php';
require_once 'includes/Post.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_obj = new User($conn);
$post_obj = new Post($conn);

// Get user details and posts
$user_details = $user_obj->getUserDetails($user_id);
if (!$user_details) {
    header("Location: logout.php");
    exit();
}
$posts = $post_obj->getPostsByUser($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="main-header">
        <h1>Social Network</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <div class="profile-container">
        <!-- Left Sidebar: User Info -->
        <aside class="profile-sidebar">
            <div class="user-card">
                <div class="profile-pic-container">
                    <img src="<?php echo htmlspecialchars($user_details['profile_picture']); ?>" alt="Profile Picture" class="profile-pic">
                </div>
                <div class="profile-info">
                    <h3 class="editable-name" style="font-size:1.2rem;font-weight:bold;margin:10px 0 2px 0;"><?php echo htmlspecialchars($user_details['full_name']); ?></h3>
                    <p class="email-field" style="margin:0 0 6px 0;color:#555;font-size:0.98rem;"><?php echo htmlspecialchars($user_details['email']); ?></p>
                </div>
                <button class="btn-primary edit-profile-btn" id="editProfileBtn" style="width:140px;margin-top:10px;">Edit Profile</button>
            </div>
        </aside>

        <!-- Main Content: Posts Feed -->
        <main class="post-feed">
            <!-- Add Post Form -->
            <div class="post-card add-post-form">
                <form id="addPostForm" enctype="multipart/form-data">
                    <textarea name="description" placeholder="What's on your mind?" required></textarea>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" style="width:120px;">Post</button>
                        <label for="postImage" class="btn-secondary" style="width:120px;text-align:center;">Add Image</label>
                        <input type="file" id="postImage" name="image" accept="image/*" style="display:none;">
                    </div>
                </form>
            </div>

            <!-- Posts List -->
            <div id="postsContainer">
                <?php if (is_array($posts) && !empty($posts)): ?>
                    <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>" style="padding:18px 18px 12px 18px;">
                        <div class="post-header" style="display:flex;align-items:center;margin-bottom:0;">
                            <img src="<?php echo htmlspecialchars($post['profile_picture']); ?>" alt="User" class="post-author-pic" style="width:48px;height:48px;">
                            <div style="display:flex;flex-direction:column;justify-content:center;">
                                <strong style="font-size:1.08rem;font-weight:500;margin-bottom:2px;"><?php echo htmlspecialchars($post['full_name']); ?></strong>
                                <small style="color:#555;font-size:0.98rem;">Posted on - <?php echo date('d M Y', strtotime($post['created_at'])); ?></small>
                            </div>
                            <button class="delete-post-btn" title="Delete Post" style="margin-left:auto;font-size:22px;background:none;border:none;color:#888;cursor:pointer;">&times;</button>
                        </div>
                        <div style="font-size:1.08rem;margin:12px 0 10px 0;font-weight:400;color:#222;line-height:1.4;">
                            <?php echo htmlspecialchars($post['description']); ?>
                        </div>
                        <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post Image" class="post-image" style="border-radius:12px;margin-top:0;">
                        <div class="post-actions" style="display:flex;gap:24px;margin-top:18px;border-top:none;padding-top:0;">
                        <?php 
                        $userReaction = $post_obj->getUserReaction($post['id'], $user_id);
                        $likeClass = ($userReaction === 'like') ? 'like-btn active' : 'like-btn';
                        $dislikeClass = ($userReaction === 'dislike') ? 'dislike-btn active' : 'dislike-btn';
                        ?>
                        <button class="<?php echo $likeClass; ?>">&#128077; <span class="like-count"><?php echo $post['likes']; ?></span></button>
                        <button class="<?php echo $dislikeClass; ?>">&#128078; <span class="dislike-count"><?php echo $post['dislikes']; ?></span></button>
                    </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-posts-message">
                        <p>No posts yet. Create your first post above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Hidden Profile Edit Form -->
    <div id="profileEditModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Profile</h3>
            <form id="profileEditForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="edit_full_name">Full Name</label>
                    <input type="text" id="edit_full_name" name="full_name" value="<?php echo htmlspecialchars($user_details['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_date_of_birth">Date of Birth</label>
                    <input type="date" id="edit_date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($user_details['date_of_birth']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_profile_picture">New Profile Picture (optional)</label>
                    <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*">
                </div>
                <button type="submit" class="btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
