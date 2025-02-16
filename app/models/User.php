<?php

declare(strict_types=1);

class User
{
    private PDO $db;

    // Constants for validation and business rules
    private const PASSWORD_MIN_LENGTH = 8;
    private const PASSWORD_MAX_LENGTH = 72; // bcrypt limit
    private const NAME_MIN_LENGTH = 2;
    private const NAME_MAX_LENGTH = 50;
    private const PHONE_LENGTH = 10;
    private const LOGIN_ATTEMPTS_LIMIT = 5;
    private const LOGIN_LOCKOUT_DURATION = 900; // 15 minutes in seconds

    // User status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending';

    /**
     * Initialize User model with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new user
     * 
     * @param array $data User data
     * @return int|false User ID on success, false on failure
     * @throws Exception If validation fails
     */
    public function create(array $data): int|false
    {
        try {
            $this->validateUserData($data);
            $this->validatePassword($data['password']);

            error_log("Starting user creation process for email: " . $data['email']);

            // Remove transaction management from here
            $sql = "INSERT INTO users (
                        name, 
                        email, 
                        phone, 
                        password, 
                        status, 
                        created_at, 
                        updated_at
                    ) VALUES (
                        :name, 
                        :email, 
                        :phone, 
                        :password, 
                        :status, 
                        CURRENT_TIMESTAMP, 
                        CURRENT_TIMESTAMP
                    )";

            $stmt = $this->db->prepare($sql);

            $params = [
                ':name' => trim($data['name']),
                ':email' => strtolower(trim($data['email'])),
                ':phone' => preg_replace('/[^0-9]/', '', $data['phone']),
                ':password' => $data['password'],
                ':status' => self::STATUS_ACTIVE
            ];

            if (!$stmt->execute($params)) {
                $error = $stmt->errorInfo();
                error_log("User creation failed: " . print_r($error, true));
                throw new Exception('Failed to create user account: ' . $error[2]);
            }

            $userId = (int)$this->db->lastInsertId();
            error_log("User created with ID: " . $userId);

            // Create verification token
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $sql = "INSERT INTO email_verifications (
                        user_id, 
                        token, 
                        expires_at, 
                        created_at
                    ) VALUES (
                        :user_id, 
                        :token, 
                        :expires_at, 
                        NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $hashedToken,
                ':expires_at' => $expiry
            ]);

            return $userId;
        } catch (Exception $e) {
            error_log("User creation failed: " . $e->getMessage());
            throw new Exception('Failed to create user account: ' . $e->getMessage());
        }
    }



    /**
     * Validate user data
     * 
     * @param array $data User data to validate
     * @throws Exception If validation fails
     */
    private function validateUserData(array $data): void
    {
        // Validate name
        if (
            empty($data['name']) ||
            strlen($data['name']) < self::NAME_MIN_LENGTH ||
            strlen($data['name']) > self::NAME_MAX_LENGTH
        ) {
            throw new Exception('Name must be between ' . self::NAME_MIN_LENGTH .
                ' and ' . self::NAME_MAX_LENGTH . ' characters');
        }

        // Validate email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }

        // Validate phone
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        if (strlen($phone) !== self::PHONE_LENGTH) {
            throw new Exception('Phone number must be ' . self::PHONE_LENGTH . ' digits');
        }
    }

    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @throws Exception If validation fails
     */
    private function validatePassword(string $password): void
    {
        $errors = [];

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors[] = "Password must be at least " . self::PASSWORD_MIN_LENGTH . " characters";
        }

        if (strlen($password) > self::PASSWORD_MAX_LENGTH) {
            $errors[] = "Password must be less than " . self::PASSWORD_MAX_LENGTH . " characters";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        if (!empty($errors)) {
            throw new Exception("Password validation failed: " . implode(", ", $errors));
        }
    }

    /**
     * Check if email exists
     * 
     * @param string $email Email to check
     * @return bool True if exists, false otherwise
     */
    public function emailExists(string $email): bool
    {
        try {
            $sql = "SELECT 1 FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => strtolower(trim($email))]);

            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database error checking email: " . $e->getMessage());
            throw new Exception('Error checking email availability');
        }
    }

    /**
     * Authenticate user login
     * 
     * @param string $email User email
     * @param string $password User password
     * @return int|false User ID on success, false on failure
     * @throws Exception If account is locked or inactive
     */
    public function login(string $email, string $password): int|false
    {
        try {
            $sql = "SELECT id, password, status, login_attempts, last_login_attempt 
                   FROM users WHERE email = :email";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => strtolower(trim($email))]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_log("Login attempt with non-existent email: {$email}");
                return false;
            }

            // Check account status
            if ($user['status'] !== self::STATUS_ACTIVE) {
                error_log("Login attempt on inactive account: ID={$user['id']}, Status={$user['status']}");
                throw new Exception('Account is not active');
            }

            // Check for account lockout
            if ($this->isAccountLocked($user)) {
                throw new Exception('Account is temporarily locked. Please try again later');
            }

            if (password_verify($password, $user['password'])) {
                // Reset login attempts on successful login
                $this->resetLoginAttempts($user['id']);

                // Update last login timestamp
                $this->updateLastLogin($user['id']);

                error_log("Successful login: User ID={$user['id']}");

                return (int)$user['id'];
            }

            // Increment failed login attempts
            $this->incrementLoginAttempts($user['id']);

            error_log("Failed login attempt: User ID={$user['id']}");

            return false;
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            throw new Exception('Login error occurred');
        }
    }

    /**
     * Check if account is locked due to too many failed attempts
     * 
     * @param array $user User data
     * @return bool True if locked, false otherwise
     */
    private function isAccountLocked(array $user): bool
    {
        if ($user['login_attempts'] >= self::LOGIN_ATTEMPTS_LIMIT) {
            $lockoutTime = strtotime($user['last_login_attempt']) + self::LOGIN_LOCKOUT_DURATION;
            if (time() < $lockoutTime) {
                return true;
            }
            // Reset attempts if lockout period has expired
            $this->resetLoginAttempts($user['id']);
        }
        return false;
    }

    /**
     * Increment failed login attempts
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    private function incrementLoginAttempts(int $userId): bool
    {
        try {
            $sql = "UPDATE users SET 
                    login_attempts = login_attempts + 1,
                    last_login_attempt = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error incrementing login attempts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset login attempts counter
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    private function resetLoginAttempts(int $userId): bool
    {
        try {
            $sql = "UPDATE users SET 
                    login_attempts = 0,
                    last_login_attempt = NULL
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error resetting login attempts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login timestamp
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    private function updateLastLogin(int $userId): bool
    {
        try {
            $sql = "UPDATE users SET 
                    last_login = NOW(),
                    updated_at = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error updating last login: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user status
     * 
     * @param int $userId User ID
     * @param string $status New status
     * @return bool Success status
     * @throws Exception If status is invalid
     */
    public function updateStatus(int $userId, string $status): bool
    {
        try {
            if (!in_array($status, [
                self::STATUS_ACTIVE,
                self::STATUS_INACTIVE,
                self::STATUS_SUSPENDED,
                self::STATUS_PENDING
            ])) {
                throw new Exception('Invalid status');
            }

            $sql = "UPDATE users SET 
                    status = :status,
                    updated_at = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);

            $result = $stmt->execute([
                ':status' => $status,
                ':user_id' => $userId
            ]);

            if ($result) {
                error_log("User status updated: ID={$userId}, NewStatus={$status}");
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Database error updating status: " . $e->getMessage());
            throw new Exception('Failed to update user status');
        }
    }

    /**
     * Get user details by ID
     * 
     * @param int $userId User ID
     * @return array|null User details or null if not found
     */
    public function getUserById(int $userId): ?array
    {
        try {
            $sql = "SELECT id, name, email, phone, status, email_verified,
              created_at, updated_at, last_login 
       FROM users WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                error_log("No user found with ID: {$userId}");
                return null;
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Database error getting user: " . $e->getMessage());
            throw new Exception('Error retrieving user details');
        }
    }

    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $data Updated user data
     * @return bool Success status
     */
    public function updateProfile(int $userId, array $data): bool
    {
        try {
            $this->validateUserData($data);

            $sql = "UPDATE users SET 
                    name = :name,
                    phone = :phone,
                    updated_at = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);

            $params = [
                ':name' => trim($data['name']),
                ':phone' => preg_replace('/[^0-9]/', '', $data['phone']),
                ':user_id' => $userId
            ];

            $result = $stmt->execute($params);

            if ($result) {
                error_log("User profile updated: ID={$userId}");
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Database error updating profile: " . $e->getMessage());
            throw new Exception('Failed to update profile');
        }
    }

    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        try {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }

            // Validate and update new password
            $this->validatePassword($newPassword);

            // Hash new password
            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT,
                ['cost' => 12]
            );

            // Update password in database
            $sql = "UPDATE users SET 
                    password = :password,
                    updated_at = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);

            $result = $stmt->execute([
                ':password' => $hashedPassword,
                ':user_id' => $userId
            ]);

            if ($result) {
                error_log("User password changed: ID={$userId}");
                // Reset login attempts after successful password change
                $this->resetLoginAttempts($userId);
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Database error changing password: " . $e->getMessage());
            throw new Exception('Failed to change password');
        }
    }

    /**
     * Create password reset token
     * 
     * @param string $email User email
     * @return string|false Reset token or false on failure
     */
    public function createPasswordResetToken(string $email): string|false
    {
        try {
            $sql = "SELECT id FROM users WHERE email = :email AND status = :status";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':email' => strtolower(trim($email)),
                ':status' => self::STATUS_ACTIVE
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return false;
            }

            // Generate token
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $sql = "INSERT INTO password_resets (user_id, token, expires_at, created_at)
                   VALUES (:user_id, :token, :expires_at, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $user['id'],
                ':token' => $hashedToken,
                ':expires_at' => $expiry
            ]);

            error_log("Password reset token created: User ID={$user['id']}");

            return $token;
        } catch (PDOException $e) {
            error_log("Database error creating reset token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify and process password reset
     * 
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        try {
            $hashedToken = hash('sha256', $token);

            // Start transaction
            $this->db->beginTransaction();

            // Get valid token
            $sql = "SELECT user_id FROM password_resets 
                   WHERE token = :token AND expires_at > NOW() 
                   AND used = 0
                   ORDER BY created_at DESC LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':token' => $hashedToken]);

            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                throw new Exception('Invalid or expired reset token');
            }

            // Validate new password
            $this->validatePassword($newPassword);

            // Update password
            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT,
                ['cost' => 12]
            );

            $sql = "UPDATE users SET 
                    password = :password,
                    updated_at = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':password' => $hashedPassword,
                ':user_id' => $reset['user_id']
            ]);

            // Mark token as used
            $sql = "UPDATE password_resets SET 
                    used = 1,
                    used_at = NOW()
                   WHERE token = :token";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':token' => $hashedToken]);

            // Reset login attempts
            $this->resetLoginAttempts($reset['user_id']);

            $this->db->commit();

            error_log("Password reset successful: User ID={$reset['user_id']}");

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error resetting password: " . $e->getMessage());
            throw new Exception('Failed to reset password');
        }
    }

    /**
     * Create email verification token
     * 
     * @param int $userId User ID
     * @return string|false Verification token or false on failure
     */
    private function createEmailVerificationToken(int $userId): string|false
    {
        try {
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $sql = "INSERT INTO email_verifications (
                        user_id, 
                        token, 
                        expires_at, 
                        created_at
                    ) VALUES (
                        :user_id, 
                        :token, 
                        :expires_at, 
                        NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $hashedToken,
                ':expires_at' => $expiry
            ]);

            return $token;
        } catch (PDOException $e) {
            error_log("Error creating verification token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify email address
     * 
     * @param string $token Verification token
     * @return bool Success status
     */
    public function verifyEmail(string $token): bool
    {
        try {
            $hashedToken = hash('sha256', $token);

            $this->db->beginTransaction();

            $sql = "SELECT user_id FROM email_verifications 
                   WHERE token = :token AND expires_at > NOW() 
                   AND used = 0
                   ORDER BY created_at DESC LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':token' => $hashedToken]);

            $verification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verification) {
                throw new Exception('Invalid or expired verification token');
            }

            // Update user email verification status
            $sql = "UPDATE users SET 
                    email_verified = 1,
                    status = :status,
                    updated_at = NOW()
                   WHERE id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':status' => self::STATUS_ACTIVE,
                ':user_id' => $verification['user_id']
            ]);

            // Mark token as used
            $sql = "UPDATE email_verifications SET 
                    used = 1,
                    used_at = NOW()
                   WHERE token = :token";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':token' => $hashedToken]);

            $this->db->commit();

            error_log("Email verification successful: User ID={$verification['user_id']}");

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error verifying email: " . $e->getMessage());
            throw new Exception('Failed to verify email');
        }
    }
}
