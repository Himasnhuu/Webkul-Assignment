<?php
// User class for registration, login, and profile management
class User
{
    private $conn;
    private $table_name = "users";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Register a new user
    public function register($fullName, $email, $password, $dob, $profilePicFile)
    {
        if (empty($fullName) || empty($email) || empty($password) || empty($dob) || $profilePicFile['error'] == UPLOAD_ERR_NO_FILE) {
            return "All fields are required.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format.";
        }
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            return "An account with this email already exists.";
        }
        $stmt->close();

        // --- 3. Handle Profile Picture Upload ---
        $uploadDirectory = "uploads/profile_pictures/"; // Relative to the project root
        $fileName = uniqid() . basename($profilePicFile["name"]);
        $targetFilePath = $uploadDirectory . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        // Allow certain file formats
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (!in_array(strtolower($fileType), $allowTypes)) {
            return "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        // Upload file to server
        if (!move_uploaded_file($profilePicFile["tmp_name"], $targetFilePath)) {
            return "Sorry, there was an error uploading your file.";
        }
        $dbFilePath = "uploads/profile_pictures/" . $fileName; // Path to store in DB

        // --- 4. Hash Password ---
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // --- 5. Insert User into Database ---
        $query = "INSERT INTO " . $this->table_name . " (full_name, email, password, date_of_birth, profile_picture) VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        // Bind parameters to the prepared statement
        $stmt->bind_param("sssss", $fullName, $email, $hashed_password, $dob, $dbFilePath);

        // Execute the statement
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            // In a real app, you might want to log the specific error: $this->conn->error
            return "An error occurred during registration. Please try again.";
        }
    }

    /**
     * Logs a user in.
     *
     * Finds the user by email, verifies the password, and starts a session.
     *
     * @param string $email The user's email.
     * @param string $password The user's plain-text password.
     * @return bool Returns true on successful login, false otherwise.
     */
    public function login($email, $password)
    {
        $query = "SELECT id, password FROM " . $this->table_name . " WHERE email = ? LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // Verify the password against the stored hash
            if (password_verify($password, $user['password'])) {
                // Password is correct, so start a new session
                $_SESSION['user_id'] = $user['id'];
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
        return false;
    }

    /**
     * Fetches details for a specific user.
     *
     * @param int $userId The ID of the user to fetch.
     * @return array|false Returns an associative array of user data or false if not found.
     */
    public function getUserDetails($userId)
    {
        $query = "SELECT id, full_name, email, date_of_birth, profile_picture, created_at FROM " . $this->table_name . " WHERE id = ? LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $userDetails = $result->fetch_assoc();
            
            // Calculate age from date of birth
            if ($userDetails['date_of_birth']) {
                $dob = new DateTime($userDetails['date_of_birth']);
                $today = new DateTime();
                $userDetails['age'] = $today->diff($dob)->y;
            } else {
                $userDetails['age'] = null;
            }
            
            $stmt->close();
            return $userDetails;
        }
        $stmt->close();
        return false;
    }

    /**
     * Updates a user's profile information.
     *
     * @param int $userId The ID of the user to update.
     * @param array $data An associative array of data to update (e.g., ['full_name' => 'New Name']).
     * @param array|null $profilePicFile The new profile picture file from $_FILES, or null if not changed.
     * @return bool Returns true on success, false on failure.
     */
    public function updateProfile($userId, $data, $profilePicFile = null)
    {
        // Dynamically build the query
        $queryParts = [];
        $params = [];
        $types = "";

        if (isset($data['full_name'])) {
            $queryParts[] = "full_name = ?";
            $params[] = $data['full_name'];
            $types .= "s";
        }
        if (isset($data['date_of_birth'])) {
            $queryParts[] = "date_of_birth = ?";
            $params[] = $data['date_of_birth'];
            $types .= "s";
        }
        
        // Handle new profile picture upload
        if ($profilePicFile && $profilePicFile['error'] == UPLOAD_ERR_OK) {
            $uploadDirectory = "uploads/profile_pictures/";
            $fileName = uniqid() . basename($profilePicFile["name"]);
            $targetFilePath = $uploadDirectory . $fileName;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

            // Allow certain file formats
            $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
            if (!in_array(strtolower($fileType), $allowTypes)) {
                return false; // Invalid file type
            }

            // Upload file to server
            if (move_uploaded_file($profilePicFile["tmp_name"], $targetFilePath)) {
                $dbFilePath = "uploads/profile_pictures/" . $fileName;
                $queryParts[] = "profile_picture = ?";
                $params[] = $dbFilePath;
                $types .= "s";
            }
        }
        
        if (count($queryParts) == 0) {
            return false; // Nothing to update
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $queryParts) . " WHERE id = ?";
        $types .= "i";
        $params[] = $userId;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        return false;
    }

    /**
     * Gets user data for editing (excluding sensitive fields like password).
     *
     * @param int $userId The ID of the user to fetch.
     * @return array|false Returns an associative array of editable user data or false if not found.
     */
    public function getUserDataForEdit($userId)
    {
        $query = "SELECT full_name, email, date_of_birth, profile_picture FROM " . $this->table_name . " WHERE id = ? LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $userData = $result->fetch_assoc();
            $stmt->close();
            return $userData;
        }
        $stmt->close();
        return false;
    }
}
?>
