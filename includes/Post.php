<?php
// Post class for creating and managing posts
class Post
{
    private $conn;
    private $table_name = "posts";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create a new post
    public function create($userId, $description, $imageFile)
    {
        try {
            if (empty($description) || $imageFile['error'] == UPLOAD_ERR_NO_FILE) {
                return "Post description and image are required.";
            }
            $uploadDirectory = "uploads/posts/";
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $fileName = uniqid() . '-' . basename($imageFile["name"]);
            $targetFilePath = $uploadDirectory . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
            if (!in_array($fileType, $allowTypes)) {
                return "Sorry, only JPG, JPEG, PNG & GIF files are allowed for posts.";
            }
            if (!move_uploaded_file($imageFile["tmp_name"], $targetFilePath)) {
                return "Sorry, there was an error uploading your post image.";
            }
            $dbFilePath = "uploads/posts/" . $fileName;
            $query = "INSERT INTO " . $this->table_name . " (user_id, description, image, likes, dislikes) VALUES (?, ?, ?, 0, 0)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iss", $userId, $description, $dbFilePath);
            if ($stmt->execute()) {
                $postId = $this->conn->insert_id;
                $stmt->close();
                
                // Return basic post data - NO complex queries
                return [
                    'id' => $postId,
                    'user_id' => $userId,
                    'description' => $description,
                    'image' => $dbFilePath,
                    'likes' => 0,
                    'dislikes' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'full_name' => 'Current User',
                    'profile_picture' => 'uploads/profile_pictures/default.jpg'
                ];
            } else {
                $stmt->close();
                return "Database error: " . $this->conn->error;
            }
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Gets posts for a user - SIMPLE
     */
    public function getPostsByUser($userId)
    {
        try {
            $query = "SELECT
                        p.id,
                        p.user_id,
                        p.description,
                        p.image,
                        p.likes,
                        p.dislikes,
                        p.created_at,
                        u.full_name,
                        u.profile_picture
                    FROM " . $this->table_name . " p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.user_id = ?
                    ORDER BY p.created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $posts = [];
            while ($row = $result->fetch_assoc()) {
                $posts[] = $row;
            }

            $stmt->close();
            return $posts;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Deletes a post
     */
    public function delete($postId, $userId)
    {
        try {
            // Get post details first
            $query = "SELECT user_id, image FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $postId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $post = $result->fetch_assoc();

                // Security check
                if ($post['user_id'] != $userId) {
                    $stmt->close();
                    return false;
                }

                // Delete image file
                $imagePath = $post['image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }

                $stmt->close();

                // Delete from database
                $deleteQuery = "DELETE FROM " . $this->table_name . " WHERE id = ?";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bind_param("i", $postId);
                
                if ($deleteStmt->execute()) {
                    $deleteStmt->close();
                    return true;
                }
            }
            
            if (isset($stmt)) $stmt->close();
            if (isset($deleteStmt)) $deleteStmt->close();
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Simple like/dislike - just increment for now
     */
    public function handleLikeDislike($postId, $userId, $action)
    {
        try {
            // Check if user already reacted
            $checkQuery = "SELECT reaction_type FROM user_reactions WHERE user_id = ? AND post_id = ?";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $userId, $postId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $existing = $result->fetch_assoc();
            $checkStmt->close();

            if ($existing) {
                if ($existing['reaction_type'] === $action) {
                    // Already reacted with same type, remove reaction (toggle off)
                    $deleteQuery = "DELETE FROM user_reactions WHERE user_id = ? AND post_id = ?";
                    $deleteStmt = $this->conn->prepare($deleteQuery);
                    $deleteStmt->bind_param("ii", $userId, $postId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                } else {
                    // Switch reaction type
                    $updateQuery = "UPDATE user_reactions SET reaction_type = ? WHERE user_id = ? AND post_id = ?";
                    $updateStmt = $this->conn->prepare($updateQuery);
                    $updateStmt->bind_param("sii", $action, $userId, $postId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            } else {
                // New reaction
                $insertQuery = "INSERT INTO user_reactions (user_id, post_id, reaction_type) VALUES (?, ?, ?)";
                $insertStmt = $this->conn->prepare($insertQuery);
                $insertStmt->bind_param("iis", $userId, $postId, $action);
                $insertStmt->execute();
                $insertStmt->close();
            }

            // Update like/dislike counts in posts table
            $countLikes = "SELECT COUNT(*) as cnt FROM user_reactions WHERE post_id = ? AND reaction_type = 'like'";
            $countDislikes = "SELECT COUNT(*) as cnt FROM user_reactions WHERE post_id = ? AND reaction_type = 'dislike'";
            $stmtLikes = $this->conn->prepare($countLikes);
            $stmtLikes->bind_param("i", $postId);
            $stmtLikes->execute();
            $likes = $stmtLikes->get_result()->fetch_assoc()['cnt'];
            $stmtLikes->close();
            $stmtDislikes = $this->conn->prepare($countDislikes);
            $stmtDislikes->bind_param("i", $postId);
            $stmtDislikes->execute();
            $dislikes = $stmtDislikes->get_result()->fetch_assoc()['cnt'];
            $stmtDislikes->close();

            $updatePost = "UPDATE posts SET likes = ?, dislikes = ? WHERE id = ?";
            $stmtUpdate = $this->conn->prepare($updatePost);
            $stmtUpdate->bind_param("iii", $likes, $dislikes, $postId);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            return ['likes' => $likes, 'dislikes' => $dislikes];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get user reaction - simple version
     */
    public function getUserReaction($postId, $userId)
    {
        // Return 'like', 'dislike', or false
        $query = "SELECT reaction_type FROM user_reactions WHERE user_id = ? AND post_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $userId, $postId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['reaction_type'];
        }
        $stmt->close();
        return false;
    }
}
?>
