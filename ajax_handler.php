<?php
// Include config, user, and post classes
require_once 'config.php';
require_once 'includes/User.php';
require_once 'includes/Post.php';

// Only allow actions for logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_obj = new User($conn);
$post_obj = new Post($conn);
$action = isset($_POST['action']) ? $_POST['action'] : '';

header('Content-Type: application/json');

switch ($action) {
    case 'add_post':
        try {
            $description = isset($_POST['description']) ? $_POST['description'] : '';
            $imageFile = isset($_FILES['image']) ? $_FILES['image'] : null;

            // Validate inputs
            if (empty($description)) {
                error_log('Post creation failed: Description missing');
                echo json_encode(['error' => 'Post description is required.']);
                break;
            }

            if (!$imageFile || $imageFile['error'] !== UPLOAD_ERR_OK) {
                error_log('Post creation failed: Image upload error ' . ($imageFile ? $imageFile['error'] : 'No file'));
                echo json_encode(['error' => 'Please select a valid image file.']);
                break;
            }

            $newPost = $post_obj->create($user_id, $description, $imageFile);

            if (is_array($newPost)) {
                // Get user details to complete the post data
                $userDetails = $user_obj->getUserDetails($user_id);
                if ($userDetails) {
                    $newPost['full_name'] = $userDetails['full_name'];
                    $newPost['profile_picture'] = $userDetails['profile_picture'];
                } else {
                    $newPost['full_name'] = 'User';
                    $newPost['profile_picture'] = 'uploads/profile_pictures/default.jpg';
                }

                echo json_encode(['success' => true, 'post' => $newPost]);
            } else {
                error_log('Post creation failed: ' . $newPost);
                echo json_encode(['error' => 'Failed to create post: ' . $newPost]);
            }
        } catch (Exception $e) {
            error_log('System error during post creation: ' . $e->getMessage());
            echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
        }
        break;

    case 'update_profile':
        $fullName = $_POST['full_name'];
        $dob = $_POST['date_of_birth'];
        $profilePicFile = isset($_FILES['profile_picture']) ? $_FILES['profile_picture'] : null;
        
        $updateData = [
            'full_name' => $fullName,
            'date_of_birth' => $dob
        ];
        
        if ($user_obj->updateProfile($user_id, $updateData, $profilePicFile)) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['error' => 'Failed to update profile.']);
        }
        break;

    case 'delete_post':
        $postId = $_POST['post_id'];
        if ($post_obj->delete($postId, $user_id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to delete post or unauthorized.']);
        }
        break;

    case 'like_dislike':
        $postId = $_POST['post_id'];
        $type = $_POST['type']; // 'like' or 'dislike'
        $counts = $post_obj->handleLikeDislike($postId, $user_id, $type);
        if ($counts) {
            echo json_encode(['success' => true, 'likes' => $counts['likes'], 'dislikes' => $counts['dislikes']]);
        } else {
            echo json_encode(['error' => 'Failed to update counts.']);
        }
        break;

    default:
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid action.']);
        break;
}
?>
