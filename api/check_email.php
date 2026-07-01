<?php
// api/check_email.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['exists' => false, 'error' => 'Email не указан']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'exists' => $count > 0,
        'email' => $data['email']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>