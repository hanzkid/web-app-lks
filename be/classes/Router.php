<?php
/**
 * Simple router class for API routing
 */
class Router {
    private $routes = [];
    private $middlewares = [];
    private $basePath = '';

    /**
     * Set base path for routes
     * 
     * @param string $basePath Base path to strip from request path
     */
    public function setBasePath($basePath) {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Add a route
     * 
     * @param string $method HTTP method
     * @param string $path Route path
     * @param callable $handler Route handler
     * @param bool $requireAuth Whether authentication is required
     */
    public function addRoute($method, $path, $handler, $requireAuth = false) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'requireAuth' => $requireAuth,
        ];
    }

    /**
     * Add GET route
     */
    public function get($path, $handler, $requireAuth = false) {
        $this->addRoute('GET', $path, $handler, $requireAuth);
    }

    /**
     * Add POST route
     */
    public function post($path, $handler, $requireAuth = false) {
        $this->addRoute('POST', $path, $handler, $requireAuth);
    }

    /**
     * Add PUT route
     */
    public function put($path, $handler, $requireAuth = false) {
        $this->addRoute('PUT', $path, $handler, $requireAuth);
    }

    /**
     * Add DELETE route
     */
    public function delete($path, $handler, $requireAuth = false) {
        $this->addRoute('DELETE', $path, $handler, $requireAuth);
    }

    /**
     * Add middleware
     * 
     * @param callable $middleware Middleware function
     */
    public function addMiddleware($middleware) {
        $this->middlewares[] = $middleware;
    }

    /**
     * Dispatch the request
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getCurrentPath();

        // Run middlewares
        foreach ($this->middlewares as $middleware) {
            $result = call_user_func($middleware);
            if ($result === false) {
                return; // Middleware stopped execution
            }
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                // Check authentication if required
                if ($route['requireAuth']) {
                    $auth = new Auth();
                    $user = $auth->authenticate();
                    
                    if (!$user) {
                        Response::unauthorized('Authentication required');
                    }
                    
                    // Add user to request context
                    $GLOBALS['current_user'] = $user;
                }

                // Extract route parameters
                $params = $this->extractParams($route['path'], $path);
                
                // Call handler
                try {
                    call_user_func($route['handler'], $params);
                } catch (Exception $e) {
                    if (DEBUG_MODE) {
                        $GLOBALS['last_error'] = $e->getMessage();
                    }
                    Response::error('Internal server error', 500);
                }
                return;
            }
        }

        // No route found
        Response::notFound('Route not found');
    }

    /**
     * Get current request path
     * 
     * @return string Current path
     */
    private function getCurrentPath() {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        // Remove base path if configured globally
        if (defined('BASE_PATH') && BASE_PATH !== '/') {
            $path = str_replace(BASE_PATH, '', $path);
        }

        // Remove router base path
        if ($this->basePath && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }

        return $path ?: '/';
    }

    /**
     * Match route path with request path
     * 
     * @param string $routePath Route pattern
     * @param string $requestPath Request path
     * @return bool True if matches
     */
    private function matchPath($routePath, $requestPath) {
        // Convert route pattern to regex
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        return preg_match($pattern, $requestPath);
    }

    /**
     * Extract parameters from path
     * 
     * @param string $routePath Route pattern
     * @param string $requestPath Request path
     * @return array Extracted parameters
     */
    private function extractParams($routePath, $requestPath) {
        $params = [];
        
        // Extract parameter names
        preg_match_all('/\{(\w+)\}/', $routePath, $paramNames);
        
        // Convert route pattern to regex and extract values
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $requestPath, $matches)) {
            array_shift($matches); // Remove full match
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }
        }
        
        return $params;
    }

    /**
     * Get request body as JSON
     * 
     * @return array|null Parsed JSON or null
     */
    public static function getRequestBody() {
        $body = file_get_contents('php://input');
        return json_decode($body, true);
    }

    /**
     * Get request query parameters
     * 
     * @return array Query parameters
     */
    public static function getQueryParams() {
        return $_GET;
    }
}
