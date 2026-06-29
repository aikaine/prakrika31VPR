-- ============================================
-- 1. СОЗДАЕМ ТАБЛИЦЫ
-- ============================================

-- Таблица пользователей (для авторизации)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица комнат
CREATE TABLE IF NOT EXISTS rooms (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    floor INT NOT NULL,
    has_projector BOOLEAN DEFAULT FALSE,
    has_whiteboard BOOLEAN DEFAULT FALSE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица бронирований (с защитой от пересечений)
CREATE TABLE IF NOT EXISTS bookings (
    id SERIAL PRIMARY KEY,
    room_id INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    topic VARCHAR(255) NOT NULL,
    need_projector BOOLEAN DEFAULT FALSE,
    status VARCHAR(20) DEFAULT 'active', -- active, cancelled, completed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Защита от двойных броней (уникальность)
    UNIQUE(room_id, booking_date, start_time)
);

-- Индекс для быстрого поиска
CREATE INDEX idx_bookings_date_room ON bookings(booking_date, room_id);

-- ============================================
-- 2. ДОБАВЛЯЕМ ТЕСТОВЫЕ ДАННЫЕ
-- ============================================

-- Тестовый пользователь (пароль: 123456)
INSERT INTO users (email, password_hash, full_name, is_admin) VALUES 
('admin@example.com', '$2y$10$YourHashHere', 'Администратор', TRUE),
('user@example.com', '$2y$10$YourHashHere', 'Иван Петров', FALSE);

-- Тестовые комнаты
INSERT INTO rooms (name, capacity, floor, has_projector, has_whiteboard, description) VALUES 
('Переговорная А', 6, 2, TRUE, TRUE, 'Уютная комната с панорамным окном'),
('Переговорная Б', 10, 3, TRUE, FALSE, 'Большая комната для презентаций'),
('Стекляшка', 4, 1, FALSE, TRUE, 'Прозрачные стены, отличное освещение'),
('Конференц-зал', 20, 4, TRUE, TRUE, 'Для масштабных мероприятий'),
('Комната 301', 8, 3, FALSE, FALSE, 'Стандартная переговорная');

-- Тестовые бронирования
INSERT INTO bookings (room_id, user_id, booking_date, start_time, end_time, topic) VALUES 
(1, 2, CURRENT_DATE, '10:00', '11:00', 'Планерка команды'),
(1, 2, CURRENT_DATE, '14:00', '15:30', 'Созвон с партнерами'),
(2, 1, CURRENT_DATE, '10:00', '12:00', 'Собеседование'),
(2, 2, CURRENT_DATE, '15:00', '16:00', 'Обучение'),
(3, 1, CURRENT_DATE, '11:00', '13:00', 'Брейншторм'),
(4, 2, CURRENT_DATE, '09:00', '17:00', 'Конференция');