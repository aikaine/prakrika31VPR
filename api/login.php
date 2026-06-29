<?php
// api/login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

// Запускаем сессию
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email и пароль обязательны']);
    exit;
}

try {
    // Ищем пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Неверный email или пароль']);
        exit;
    }
    
    // Проверяем пароль
    if (!password_verify($data['password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Неверный email или пароль']);
        exit;
    }
    
    // Сохраняем в сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['is_admin'] = $user['is_admin'];
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'is_admin' => $user['is_admin']
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>