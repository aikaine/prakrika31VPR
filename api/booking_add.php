<?php
// api/booking_add.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

// Запускаем сессию для получения ID пользователя
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

// ID пользователя из сессии
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['room_id']) || !isset($data['booking_date']) || 
    !isset($data['start_time']) || !isset($data['end_time']) || !isset($data['topic'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не все поля заполнены']);
    exit;
}

try {
    // Проверяем, не занято ли время
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bookings 
        WHERE room_id = :room_id 
          AND booking_date = :booking_date 
          AND status = 'active'
          AND (
              (start_time <= :start_time AND end_time > :start_time) OR
              (start_time < :end_time AND end_time >= :end_time) OR
              (start_time >= :start_time AND end_time <= :end_time)
          )
    ");
    $checkStmt->execute([
        ':room_id' => $data['room_id'],
        ':booking_date' => $data['booking_date'],
        ':start_time' => $data['start_time'],
        ':end_time' => $data['end_time']
    ]);
    $result = $checkStmt->fetch();
    
    if ($result['count'] > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'error' => 'Это время уже занято!'
        ]);
        exit;
    }
    
    // Сохраняем бронирование с user_id из сессии
    $insertStmt = $pdo->prepare("
        INSERT INTO bookings (
            room_id, user_id, booking_date, start_time, end_time, topic, need_projector
        ) VALUES (
            :room_id, :user_id, :booking_date, :start_time, :end_time, :topic, :need_projector
        )
    ");
    
    $insertStmt->execute([
        ':room_id' => $data['room_id'],
        ':user_id' => $user_id,  // ← Теперь берётся из сессии!
        ':booking_date' => $data['booking_date'],
        ':start_time' => $data['start_time'],
        ':end_time' => $data['end_time'],
        ':topic' => $data['topic'],
        ':need_projector' => isset($data['need_projector']) ? $data['need_projector'] : 0
    ]);
    
    echo json_encode([
        'success' => true,
        'booking_id' => $pdo->lastInsertId(),
        'message' => 'Бронирование успешно создано!'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
?>