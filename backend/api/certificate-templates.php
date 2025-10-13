<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    $db = getDBConnection();
    
    switch ($method) {
        case 'GET':
            handleGet($db, $user_id, $user_role);
            break;
        case 'POST':
            handlePost($db, $user_id, $user_role);
            break;
        case 'PUT':
            handlePut($db, $user_id, $user_role);
            break;
        case 'DELETE':
            handleDelete($db, $user_id, $user_role);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($db, $user_id, $user_role) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getTemplates($db, $user_role);
            break;
        case 'get':
            getTemplate($db, $_GET['id'] ?? null);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getTemplates($db, $user_role) {
    $query = "SELECT t.*, u.name as created_by_name
              FROM certificate_templates t
              LEFT JOIN users u ON t.created_by = u.id
              WHERE t.is_active = TRUE
              ORDER BY t.is_default DESC, t.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($templates as &$template) {
        $template['signature_fields'] = json_decode($template['signature_fields'], true);
    }
    
    echo json_encode(['templates' => $templates]);
}

function getTemplate($db, $template_id) {
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Template ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM certificate_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
        return;
    }
    
    $template['signature_fields'] = json_decode($template['signature_fields'], true);
    
    echo json_encode(['template' => $template]);
}

function handlePost($db, $user_id, $user_role) {
    if ($user_role !== 'admin' && $user_role !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $query = "INSERT INTO certificate_templates 
              (name, description, orientation, background_color, background_image,
               border_style, border_color, title_text, title_font_size, title_color,
               body_template, signature_fields, logo_url, is_default, created_by)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['name'],
        $data['description'] ?? null,
        $data['orientation'] ?? 'landscape',
        $data['background_color'] ?? '#FFFFFF',
        $data['background_image'] ?? null,
        $data['border_style'] ?? 'elegant',
        $data['border_color'] ?? '#000000',
        $data['title_text'] ?? 'Certificate of Completion',
        $data['title_font_size'] ?? 36,
        $data['title_color'] ?? '#000000',
        $data['body_template'],
        json_encode($data['signature_fields'] ?? []),
        $data['logo_url'] ?? null,
        $data['is_default'] ?? false,
        $user_id
    ]);
    
    $template_id = $db->lastInsertId();
    
    // If set as default, unset other defaults
    if ($data['is_default'] ?? false) {
        $stmt = $db->prepare("UPDATE certificate_templates SET is_default = FALSE WHERE id != ?");
        $stmt->execute([$template_id]);
    }
    
    echo json_encode([
        'success' => true,
        'template_id' => $template_id,
        'message' => 'Template created successfully'
    ]);
}

function handlePut($db, $user_id, $user_role) {
    if ($user_role !== 'admin' && $user_role !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $template_id = $data['id'] ?? null;
    
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Template ID required']);
        return;
    }
    
    $query = "UPDATE certificate_templates 
              SET name = ?, description = ?, orientation = ?, background_color = ?,
                  background_image = ?, border_style = ?, border_color = ?,
                  title_text = ?, title_font_size = ?, title_color = ?,
                  body_template = ?, signature_fields = ?, logo_url = ?,
                  is_default = ?, updated_at = NOW()
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['name'],
        $data['description'] ?? null,
        $data['orientation'],
        $data['background_color'],
        $data['background_image'] ?? null,
        $data['border_style'],
        $data['border_color'],
        $data['title_text'],
        $data['title_font_size'],
        $data['title_color'],
        $data['body_template'],
        json_encode($data['signature_fields']),
        $data['logo_url'] ?? null,
        $data['is_default'] ?? false,
        $template_id
    ]);
    
    if ($data['is_default'] ?? false) {
        $stmt = $db->prepare("UPDATE certificate_templates SET is_default = FALSE WHERE id != ?");
        $stmt->execute([$template_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Template updated successfully'
    ]);
}

function handleDelete($db, $user_id, $user_role) {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $template_id = $_GET['id'] ?? null;
    
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Template ID required']);
        return;
    }
    
    // Don't delete if it's the default template
    $stmt = $db->prepare("SELECT is_default FROM certificate_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template && $template['is_default']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete default template']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE certificate_templates SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$template_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Template deleted successfully'
    ]);
}
