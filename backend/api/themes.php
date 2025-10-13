<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth();

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['active'])) {
                getActiveTheme($conn);
            } else {
                listThemes($conn);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['activate'])) {
                activateTheme($conn, $data['id']);
            } else {
                createTheme($conn, $data);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateTheme($conn, $data);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            deleteTheme($conn, $data['id']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listThemes($conn) {
    $stmt = $conn->query("SELECT * FROM themes ORDER BY is_active DESC, name ASC");
    $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'themes' => $themes]);
}

function getActiveTheme($conn) {
    $stmt = $conn->query("SELECT * FROM themes WHERE is_active = TRUE LIMIT 1");
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$theme) {
        $stmt = $conn->query("SELECT * FROM themes WHERE is_default = TRUE LIMIT 1");
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'theme' => $theme]);
}

function createTheme($conn, $data) {
    $stmt = $conn->prepare("
        INSERT INTO themes (name, slug, description, primary_color, secondary_color, accent_color, font_family)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $slug = strtolower(str_replace(' ', '-', $data['name']));
    
    $stmt->execute([
        $data['name'],
        $slug,
        $data['description'] ?? '',
        $data['primary_color'] ?? '#3b82f6',
        $data['secondary_color'] ?? '#10b981',
        $data['accent_color'] ?? '#f59e0b',
        $data['font_family'] ?? 'Inter'
    ]);
    
    $themeId = $conn->lastInsertId();
    
    echo json_encode(['success' => true, 'id' => $themeId, 'message' => 'Theme created successfully']);
}

function updateTheme($conn, $data) {
    $stmt = $conn->prepare("
        UPDATE themes 
        SET name = ?, description = ?, primary_color = ?, secondary_color = ?, 
            accent_color = ?, font_family = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['name'],
        $data['description'] ?? '',
        $data['primary_color'],
        $data['secondary_color'],
        $data['accent_color'],
        $data['font_family'],
        $data['id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Theme updated successfully']);
}

function activateTheme($conn, $id) {
    // Deactivate all themes
    $conn->query("UPDATE themes SET is_active = FALSE");
    
    // Activate selected theme
    $stmt = $conn->prepare("UPDATE themes SET is_active = TRUE WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Theme activated successfully']);
}

function deleteTheme($conn, $id) {
    // Don't allow deleting active or default theme
    $stmt = $conn->prepare("SELECT is_active, is_default FROM themes WHERE id = ?");
    $stmt->execute([$id]);
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($theme['is_active'] || $theme['is_default']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete active or default theme']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM themes WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Theme deleted successfully']);
}
