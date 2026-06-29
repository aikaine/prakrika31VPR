<?php
// config/database.php - ОБНОВЛЕННАЯ ВЕРСИЯ

$dbDir = __DIR__ . '/../database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

$dbFile = $dbDir . '/booking.db';

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // --- СОЗДАЁМ ТАБЛИЦЫ ---
    
    // Таблица пользователей (НОВАЯ)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            is_admin BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Таблица комнат
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            capacity INTEGER NOT NULL,
            floor INTEGER NOT NULL,
            has_projector BOOLEAN DEFAULT 0,
            has_whiteboard BOOLEAN DEFAULT 0,
            description TEXT
        )
    ");
    
    // Таблица бронирований (добавляем поле user_id для связи)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            booking_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            topic VARCHAR(255) NOT NULL,
            need_projector BOOLEAN DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // --- ДОБАВЛЯЕМ ТЕСТОВОГО ПОЛЬЗОВАТЕЛЯ (если нет) ---
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Пароль: 123456 (хешируем)
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (email, password_hash, full_name, is_admin) VALUES 
            ('admin@example.com', '$passwordHash', 'Администратор', 1),
            ('user@example.com', '$passwordHash', 'Иван Петров', 0)
        ");
    }
    
    // --- ОСТАЛЬНЫЕ ТАБЛИЦЫ И ДАННЫЕ ---
    // (код для rooms и bookings остаётся без изменений)
    
    // Проверяем, есть ли комнаты
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO rooms (name, capacity, floor, has_projector, has_whiteboard, description) VALUES 
            ('Переговорная А', 6, 2, 1, 1, 'Уютная комната с панорамным окном'),
            ('Переговорная Б', 10, 3, 1, 0, 'Большая комната для презентаций'),
            ('Стекляшка', 4, 1, 0, 1, 'Прозрачные стены, отличное освещение'),
            ('Конференц-зал', 20, 4, 1, 1, 'Для масштабных мероприятий'),
            ('Комната 301', 8, 3, 0, 0, 'Стандартная переговорная')
        ");
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка подключения к SQLite: ' . $e->getMessage()
    ]);
    exit;
}
?>