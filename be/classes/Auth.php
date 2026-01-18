<?php
/**
 * Authentication class for access token management
 */
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Generate a secure access token
     * 
     * @param int $userId User ID
     * @param array $payload Additional payload data
     * @return string Access token
     */
    public function generateToken($userId, $payload = []) {
        $tokenData = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + TOKEN_EXPIRY,
        ];

        $tokenData = array_merge($tokenData, $payload);

        // Encode token (simple base64 encoding - for production, use JWT library)
        $token = base64_encode(json_encode($tokenData));
        
        // Store token in database
        $this->storeToken($userId, $token, $tokenData['exp']);

        return $token;
    }

    /**
     * Store token in database
     * 
     * @param int $userId User ID
     * @param string $token Access token
     * @param int $expiresAt Expiration timestamp
     */
    private function storeToken($userId, $token, $expiresAt) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO access_tokens (user_id, token, expires_at, created_at) 
                VALUES (:user_id, :token, FROM_UNIXTIME(:expires_at), NOW())
                ON DUPLICATE KEY UPDATE 
                    token = VALUES(token), 
                    expires_at = VALUES(expires_at),
                    created_at = NOW()
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':token' => hash('sha256', $token), // Store hashed version
                ':expires_at' => $expiresAt,
            ]);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Token storage error: " . $e->getMessage());
            }
        }
    }

    /**
     * Validate access token
     * 
     * @param string $token Access token
     * @return array|false Token data if valid, false otherwise
     */
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }

        try {
            // Decode token
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded || !isset($decoded['user_id']) || !isset($decoded['exp'])) {
                return false;
            }

            // Check expiration
            if ($decoded['exp'] < time()) {
                $this->revokeToken($token);
                return false;
            }

            // Verify token exists in database
            $hashedToken = hash('sha256', $token);
            $stmt = $this->db->prepare("
                SELECT user_id, expires_at 
                FROM access_tokens 
                WHERE token = :token 
                AND expires_at > NOW()
            ");

            $stmt->execute([':token' => $hashedToken]);
            $storedToken = $stmt->fetch();

            if (!$storedToken) {
                return false;
            }

            return $decoded;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Token validation error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Revoke a token
     * 
     * @param string $token Access token
     */
    public function revokeToken($token) {
        try {
            $hashedToken = hash('sha256', $token);
            $stmt = $this->db->prepare("
                DELETE FROM access_tokens 
                WHERE token = :token
            ");

            $stmt->execute([':token' => $hashedToken]);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Token revocation error: " . $e->getMessage());
            }
        }
    }

    /**
     * Get token from request headers
     * 
     * @return string|null Token or null if not found
     */
    public function getTokenFromRequest() {
        $headers = getallheaders();
        
        // Check Authorization header
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Check X-Access-Token header
        if (isset($headers['X-Access-Token'])) {
            return $headers['X-Access-Token'];
        }

        return null;
    }

    /**
     * Authenticate request and return user data
     * 
     * @return array|false User data if authenticated, false otherwise
     */
    public function authenticate() {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            return false;
        }

        $tokenData = $this->validateToken($token);
        
        if (!$tokenData) {
            return false;
        }

        return $tokenData;
    }

    /**
     * Register a new user
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array|false User data if successful, false otherwise
     */
    public function register($email, $password) {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("
                SELECT id FROM users WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
            
            if ($stmt->fetch()) {
                return false; // User already exists
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, created_at) 
                VALUES (:email, :password_hash, NOW())
            ");

            $stmt->execute([
                ':email' => $email,
                ':password_hash' => $passwordHash,
            ]);

            $userId = $this->db->lastInsertId();

            // Generate token for new user
            $token = $this->generateToken($userId, [
                'email' => $email,
            ]);

            return [
                'token' => $token,
                'user_id' => $userId,
                'expires_in' => TOKEN_EXPIRY,
            ];
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Registration error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Login user and return access token
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array|false Token data if successful, false otherwise
     */
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, email, password_hash 
                FROM users 
                WHERE email = :email
            ");

            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                return false;
            }

            $token = $this->generateToken($user['id'], [
                'email' => $user['email'],
            ]);

            return [
                'token' => $token,
                'user_id' => $user['id'],
                'expires_in' => TOKEN_EXPIRY,
            ];
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Login error: " . $e->getMessage());
            }
            return false;
        }
    }
}
