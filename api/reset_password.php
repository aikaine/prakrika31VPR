<?php
// api/reset_password.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

// Проверяем наличие всех полей
if (!isset($data['email']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не все поля заполнены']);
    exit;
}

$email = $data['email'];
$newPassword = $data['new_password'];

// Проверка длины пароля
if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Пароль должен быть минимум 6 символов']);
    exit;
}

try {
    // Проверяем, существует ли пользователь
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email");
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }
    
    // Хешируем новый пароль
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Обновляем пароль в базе
    $updateStmt = $pdo->prepare("
        UPDATE users SET password_hash = :password_hash WHERE email = :email
    ");
    $updateStmt->execute([
        ':password_hash' => $passwordHash,
        ':email' => $email
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Пароль успешно изменён'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
?>