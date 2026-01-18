<?php
/**
 * API Entry Point
 */

// Load Composer autoloader (if available)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load configuration
require_once __DIR__ . '/config.php';

// Load classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Response.php';
require_once __DIR__ . '/classes/Router.php';
require_once __DIR__ . '/classes/S3Service.php';

// Error handling
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (DEBUG_MODE) {
        $GLOBALS['last_error'] = [
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];
    }
    return false;
});

// Set exception handler
set_exception_handler(function($exception) {
    if (DEBUG_MODE) {
        $GLOBALS['last_error'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
    Response::error('Internal server error', 500);
});

// Initialize router
$router = new Router();

// Health check endpoint
$router->get('/health', function() {
    Response::success([
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => API_VERSION ?? 'v1',
    ]);
});

// API routes
$requestPath = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestPath, PHP_URL_PATH);

// Remove base path if configured
if (defined('BASE_PATH') && BASE_PATH !== '/') {
    $path = str_replace(BASE_PATH, '', $path);
}

// Route to appropriate API module
if (str_contains($path, '/auth')) {
    require_once __DIR__ . '/api/auth.php';
} elseif (str_contains($path, '/galleries')) {
    require_once __DIR__ . '/api/galleries.php';
} else {
    // Default router dispatch for health check and other routes
    $router->dispatch();
}
