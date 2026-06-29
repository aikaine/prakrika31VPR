<?php
// api/register.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

// Валидация
if (!isset($data['email']) || !isset($data['password']) || !isset($data['full_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Все поля обязательны']);
    exit;
}

// Проверка email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный формат email']);
    exit;
}

// Проверка пароля (минимум 6 символов)
if (strlen($data['password']) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Пароль должен быть минимум 6 символов']);
    exit;
}

try {
    // Проверяем, не занят ли email
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email");
    $checkStmt->execute([':email' => $data['email']]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Этот email уже зарегистрирован']);
        exit;
    }
    
    // Хешируем пароль
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Сохраняем пользователя
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, full_name) 
        VALUES (:email, :password_hash, :full_name)
    ");
    $stmt->execute([
        ':email' => $data['email'],
        ':password_hash' => $passwordHash,
        ':full_name' => $data['full_name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Регистрация успешна! Теперь войдите в систему.'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>