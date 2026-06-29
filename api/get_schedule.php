<?php
// api/get_schedule.php - ОБНОВЛЕННАЯ ВЕРСИЯ
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    $roomsStmt = $pdo->query("SELECT * FROM rooms ORDER BY id");
    $rooms = $roomsStmt->fetchAll();
    
    // Получаем брони с именем пользователя
    $bookingsStmt = $pdo->prepare("
        SELECT 
            b.*,
            u.full_name as user_name,
            u.email as user_email
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.booking_date = :date AND b.status = 'active'
        ORDER BY b.start_time
    ");
    $bookingsStmt->execute([':date' => $date]);
    $bookings = $bookingsStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'rooms' => $rooms,
        'bookings' => $bookings
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
?>