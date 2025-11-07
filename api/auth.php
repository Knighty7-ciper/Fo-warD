<?php
/**
 * Authentication API Endpoints
 * Forward LMS Authentication Handler
 */

require_once __DIR__ . '/../../config/api.php';

// POST /api/auth/login
$router->add('POST', '/auth/login', function($params, $db, $auth) {
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['email', 'password'])) {
        return;
    }
    
    try {
        $result = $auth->login($data['email'], $data['password']);
        $router->success($result, 'Login successful');
    } catch (Exception $e) {
        $router->error(401, $e->getMessage());
    }
});

// POST /api/auth/register
$router->add('POST', '/auth/register', function($params, $db, $auth) {
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['name', 'email', 'password'])) {
        return;
    }
    
    $role = $data['role'] ?? 'student';
    if (!in_array($role, ['student', 'teacher', 'admin'])) {
        $router->error(400, 'Invalid role specified');
        return;
    }
    
    try {
        $result = $auth->register($data['name'], $data['email'], $data['password'], $role);
        $router->success($result, 'Registration successful');
    } catch (Exception $e) {
        $router->error(400, $e->getMessage());
    }
});

// POST /api/auth/logout
$router->add('POST', '/auth/logout', function($params, $db, $auth) {
    try {
        $auth->logout();
        $router->success(null, 'Logout successful');
    } catch (Exception $e) {
        $router->error(500, $e->getMessage());
    }
});

// GET /api/auth/me
$router->add('GET', '/auth/me', function($params, $db, $auth) {
    if (!$auth->isLoggedIn()) {
        $router->error(401, 'Not authenticated');
        return;
    }
    
    $user = $auth->getCurrentUser();
    $router->success($user, 'User profile retrieved');
});

// POST /api/auth/refresh-token
$router->add('POST', '/auth/refresh-token', function($params, $db, $auth) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        $router->error(401, 'Authentication required');
        return;
    }
    
    // Get fresh user data from database
    $userData = $db->fetch(
        "SELECT id, name, email, role FROM users WHERE id = ? AND status = 'active'",
        [$user['id']]
    );
    
    if (!$userData) {
        $router->error(401, 'User not found or inactive');
        return;
    }
    
    $token = $auth->generateJWT($userData);
    $router->success(['token' => $token], 'Token refreshed');
});

// Handle the request
$router->handle();
?>