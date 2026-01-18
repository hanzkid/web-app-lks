<?php
/**
 * Configuration file for the API
 * Loads configuration from .env file
 */

// Load environment variables from .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false) {
        return $default;
    }
    
    if (strtolower($value) === 'true') {
        return true;
    }
    if (strtolower($value) === 'false') {
        return false;
    }
    
    if (is_numeric($value)) {
        return strpos($value, '.') !== false ? (float)$value : (int)$value;
    }
    
    return $value;
}

// Database Configuration
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'lks_db'));
define('DB_USER', env('DB_USER', 'lks_user'));
define('DB_PASS', env('DB_PASS', 'lks_password'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// S3-Compatible Storage Configuration
define('AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID', ''));
define('AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY', ''));
define('AWS_REGION', env('AWS_REGION', 'us-east-1'));
define('AWS_S3_BUCKET', env('AWS_S3_BUCKET', ''));
define('S3_ENDPOINT', env('S3_ENDPOINT', ''));
define('S3_USE_PATH_STYLE', env('S3_USE_PATH_STYLE', false));

// JWT/Auth Configuration
define('JWT_SECRET', env('JWT_SECRET', 'your_jwt_secret_key_change_this_in_production'));
define('JWT_ALGORITHM', env('JWT_ALGORITHM', 'HS256'));
define('TOKEN_EXPIRY', env('TOKEN_EXPIRY', 3600));

// CORS Configuration
$allowedOrigins = env('ALLOWED_ORIGINS', '*');
if ($allowedOrigins === '*') {
    define('ALLOWED_ORIGINS', ['*']);
} else {
    define('ALLOWED_ORIGINS', array_map('trim', explode(',', $allowedOrigins)));
}

// Debug Mode
define('DEBUG_MODE', env('DEBUG_MODE', false));

// Timezone
$timezone = env('TIMEZONE', 'UTC');
date_default_timezone_set($timezone);
