<?php
/**
 * Login Page - Franken UI Login-01 Design
 */

// Load required classes
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';

session_start();

// Check if user is already logged in
if (isset($_SESSION['access_token'])) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Use Auth class directly
        $auth = new Auth();
        $result = $auth->login($email, $password);

        if ($result && isset($result['token'])) {
            $_SESSION['access_token'] = $result['token'];
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['email'] = $email;
            header('Location: /be/galleries.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/franken-ui@2.1.2/dist/css/core.min.css"
    />
    <title>Login - Mari Berkarya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: hsl(0, 0%, 96%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 28rem;
        }

        .login-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            padding: 2rem;
        }

        .login-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: hsl(0, 0%, 9%);
            margin-bottom: 0.5rem;
        }

        .login-description {
            font-size: 0.875rem;
            color: hsl(0, 0%, 45%);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(0, 0%, 9%);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid hsl(0, 0%, 80%);
            border-radius: 0.375rem;
            background: white;
            color: hsl(0, 0%, 9%);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-input:focus {
            outline: none;
            border-color: hsl(221, 83%, 53%);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: hsl(0, 0%, 60%);
        }

        .btn {
            width: 100%;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }

        .btn-primary {
            background-color: hsl(0, 0%, 9%);
            color: white;
        }

        .btn-primary:hover {
            background-color: hsl(0, 0%, 15%);
        }

        .btn-primary:disabled {
            background-color: hsl(0, 0%, 70%);
            cursor: not-allowed;
        }

        .form-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: hsl(0, 0%, 45%);
        }

        .form-footer a {
            color: hsl(221, 83%, 53%);
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background-color: hsl(0, 84%, 60%);
            color: white;
        }

        .alert-success {
            background-color: hsl(142, 71%, 45%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Login to your account</h1>
                <p class="login-description">Enter your email and password to access your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="you@example.com"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">Login</button>

                <div class="form-footer">
                    Don't have an account? <a href="register.php">Sign up</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';
        });
    </script>
</body>
</html>
