<?php
// api/booking_delete.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

session_start();

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['booking_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID брони']);
    exit;
}

try {
    // Проверяем, что бронь принадлежит текущему пользователю
    $checkStmt = $pdo->prepare("
        SELECT user_id FROM bookings WHERE id = :id
    ");
    $checkStmt->execute([':id' => $data['booking_id']]);
    $booking = $checkStmt->fetch();
    
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Бронь не найдена']);
        exit;
    }
    
    if ($booking['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Нет прав для отмены этой брони']);
        exit;
    }
    
    // Отменяем бронь
    $stmt = $pdo->prepare("
        UPDATE bookings SET status = 'cancelled' WHERE id = :id
    ");
    $stmt->execute([':id' => $data['booking_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Бронирование отменено'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>