<?php
/**
 * API Router
 * Forward LMS API Request Handler
 */

class APIRouter {
    private $db;
    private $auth;
    private $routes = [];
    private $middlewares = [];
    
    public function __construct($database, $auth) {
        $this->db = $database;
        $this->auth = $auth;
    }
    
    /**
     * Add route
     */
    public function add($method, $path, $handler, $middlewares = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }
    
    /**
     * Add middleware
     */
    public function middleware($callback) {
        $this->middlewares[] = $callback;
    }
    
    /**
     * Handle request
     */
    public function handle() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove base path if needed
        $basePath = '/api';
        if (strpos($requestPath, $basePath) === 0) {
            $requestPath = substr($requestPath, strlen($basePath));
        }
        
        // Execute global middlewares
        foreach ($this->middlewares as $middleware) {
            $result = call_user_func($middleware);
            if ($result === false) {
                return;
            }
        }
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $requestMethod, $requestPath)) {
                $this->executeRoute($route, $requestPath);
                return;
            }
        }
        
        // No route found
        $this->error(404, 'Route not found');
    }
    
    /**
     * Match route
     */
    private function matchRoute($route, $method, $path) {
        if ($route['method'] !== $method) {
            return false;
        }
        
        $routePath = $route['path'];
        
        // Convert route path to regex
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([^/]+)', $routePath);
        $regex = '#^' . $regex . '$#';
        
        return preg_match($regex, $path);
    }
    
    /**
     * Execute route
     */
    private function executeRoute($route, $requestPath) {
        // Execute route middlewares
        foreach ($route['middlewares'] as $middleware) {
            $result = call_user_func($middleware, $this->db, $this->auth);
            if ($result === false) {
                return;
            }
        }
        
        // Extract parameters
        $params = $this->extractParams($route['path'], $requestPath);
        
        // Call handler
        $response = call_user_func($route['handler'], $params, $this->db, $this->auth);
        
        if ($response !== null) {
            $this->json($response);
        }
    }
    
    /**
     * Extract parameters from URL
     */
    private function extractParams($routePath, $requestPath) {
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([^/]+)', $routePath);
        $regex = '#^' . $regex . '$#';
        
        preg_match($regex, $requestPath, $matches);
        array_shift($matches); // Remove full match
        
        // Extract parameter names
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $routePath, $paramNames);
        $paramNames = $paramNames[1];
        
        $params = [];
        foreach ($paramNames as $index => $name) {
            $params[$name] = $matches[$index] ?? null;
        }
        
        return $params;
    }
    
    /**
     * Send JSON response
     */
    public function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Send success response
     */
    public function success($data = null, $message = 'Success') {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->json($response);
    }
    
    /**
     * Send error response
     */
    public function error($statusCode, $message, $errors = null) {
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $statusCode);
    }
    
    /**
     * Get request data
     */
    public function getRequestData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = $_POST;
        }
        
        return $data ?: [];
    }
    
    /**
     * Validate required fields
     */
    public function validateRequired($data, $required) {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->error(400, 'Missing required fields', ['missing' => $missing]);
            return false;
        }
        
        return true;
    }
}

// Initialize API router
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

$router = new APIRouter($db, $auth);

// Global middleware for CORS
$router->middleware(function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
});

// Global middleware for API authentication
$router->middleware(function($db, $auth) {
    $user = $auth->authenticateWithJWT();
    if ($user) {
        $_SESSION['api_user'] = $user;
        return true;
    }
    
    // Allow some public endpoints
    $publicEndpoints = [
        '/auth/login',
        '/auth/register',
        '/courses' // Allow public course browsing
    ];
    
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($requestPath, '/api') === 0) {
        $requestPath = substr($requestPath, 4);
    }
    
    if (in_array($requestPath, $publicEndpoints)) {
        return true;
    }
    
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    return false;
});
?>