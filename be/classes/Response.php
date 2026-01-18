<?php
/**
 * Response helper class for API responses
 */
class Response {
    /**
     * Send JSON response
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     */
    public static function json($data, $statusCode = 200, $headers = []) {
        http_response_code($statusCode);
        
        // Set default headers
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS headers
        self::setCorsHeaders();
        
        // Set custom headers
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = []) {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if (DEBUG_MODE && isset($GLOBALS['last_error'])) {
            $response['debug'] = $GLOBALS['last_error'];
        }

        self::json($response, $statusCode);
    }

    /**
     * Send unauthorized response
     * 
     * @param string $message Error message
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     * 
     * @param string $message Error message
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    /**
     * Send not found response
     * 
     * @param string $message Error message
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Send validation error response
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, $errors);
    }

    /**
     * Set CORS headers
     */
    private static function setCorsHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, ALLOWED_ORIGINS)) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Access-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
