<?php
/**
 * Authentication API endpoints
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Router.php';

$router = new Router();
$router->setBasePath('/be/auth');

// Registration endpoint
$router->post('/register', function() {
    $data = Router::getRequestBody();
    
    if (empty($data['email']) || empty($data['password'])) {
        Response::validationError([
            'email' => 'Email is required',
            'password' => 'Password is required',
        ]);
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        Response::validationError([
            'email' => 'Invalid email format',
        ]);
    }

    // Validate password length
    if (strlen($data['password']) < 6) {
        Response::validationError([
            'password' => 'Password must be at least 6 characters',
        ]);
    }

    $auth = new Auth();
    $result = $auth->register($data['email'], $data['password']);

    if ($result) {
        Response::success($result, 'Registration successful');
    } else {
        Response::error('Registration failed. Email may already be in use.', 400);
    }
});

// Login endpoint
$router->post('/login', function() {
    $data = Router::getRequestBody();
    
    if (empty($data['email']) || empty($data['password'])) {
        Response::validationError([
            'email' => 'Email is required',
            'password' => 'Password is required',
        ]);
    }

    $auth = new Auth();
    $result = $auth->login($data['email'], $data['password']);

    if ($result) {
        Response::success($result, 'Login successful');
    } else {
        Response::error('Invalid credentials', 401);
    }
});

// Logout endpoint (requires authentication)
$router->post('/logout', function() {
    $auth = new Auth();
    $token = $auth->getTokenFromRequest();
    
    if ($token) {
        $auth->revokeToken($token);
    }

    Response::success(null, 'Logged out successfully');
}, true);

// Get current user info (requires authentication)
$router->get('/me', function() {
    $user = $GLOBALS['current_user'] ?? null;
    
    if ($user) {
        Response::success([
            'user_id' => $user['user_id'],
            'email' => $user['email'] ?? null,
        ]);
    } else {
        Response::unauthorized();
    }
}, true);

$router->dispatch();
