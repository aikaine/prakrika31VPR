// ============================================================
// script.js - Финальная версия с авторизацией и отменой
// ============================================================

let rooms = [];
let bookings = [];
let currentUserId = null;
let currentDate = new Date();
let selectedSlot = null;

// --- Проверка авторизации ---
async function checkAuth() {
    try {
        const response = await fetch('api/check_session.php');
        const data = await response.json();
        
        if (!data.authenticated) {
            window.location.href = 'login.html';
            return false;
        }
        
        currentUserId = data.user.id;
        document.getElementById('userName').textContent = data.user.full_name;
        return true;
    } catch (error) {
        console.error('Ошибка проверки авторизации:', error);
        window.location.href = 'login.html';
        return false;
    }
}

// --- Выход ---
async function logout() {
    try {
        await fetch('api/logout.php', { method: 'POST' });
        window.location.href = 'login.html';
    } catch (error) {
        console.error('Ошибка выхода:', error);
    }
}

// --- Функции работы с датой ---
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addMinutes(timeStr, minutes) {
    const [hours, mins] = timeStr.split(':').map(Number);
    const totalMinutes = hours * 60 + mins + minutes;
    const newHours = Math.floor(totalMinutes / 60);
    const newMins = totalMinutes % 60;
    return `${String(newHours).padStart(2, '0')}:${String(newMins).padStart(2, '0')}`;
}

function compareTime(a, b) {
    const [h1, m1] = a.split(':').map(Number);
    const [h2, m2] = b.split(':').map(Number);
    if (h1 !== h2) return h1 - h2;
    return m1 - m2;
}

function buildOccupancyMap(bookings) {
    const map = {};
    bookings.forEach(booking => {
        let current = booking.start_time;
        while (compareTime(current, booking.end_time) < 0) {
            const key = `${booking.room_id}_${current}`;
            map[key] = booking;
            current = addMinutes(current, 30);
        }
    });
    return map;
}

// --- Загрузка данных ---
async function fetchData() {
    const date = document.getElementById('datePicker').value;
    
    try {
        const response = await fetch(`api/get_schedule.php?date=${date}`);
        const data = await response.json();
        
        if (data.success) {
            rooms = data.rooms;
            bookings = data.bookings;
            return data;
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Ошибка загрузки:', error);
        alert('Не удалось загрузить расписание.');
        throw error;
    }
}

// --- Отрисовка таблицы ---
function renderSchedule(rooms, bookings, date) {
    const thead = document.querySelector('#scheduleTable thead tr');
    const tbody = document.getElementById('scheduleBody');
    
    thead.innerHTML = '';
    tbody.innerHTML = '';
    
    // Шапка
    const timeTh = document.createElement('th');
    timeTh.className = 'time-header';
    timeTh.textContent = 'Время';
    thead.appendChild(timeTh);
    
    rooms.forEach(room => {
        const th = document.createElement('th');
        th.className = 'room-header';
        th.dataset.roomId = room.id;
        
        let badges = '';
        if (room.has_projector) badges += ' 📽️';
        if (room.has_whiteboard) badges += ' ✏️';
        
        th.innerHTML = `
            ${room.name}
            <small>${room.capacity} чел. · ${room.floor} этаж</small>
            <span class="room-badge">${badges || 'Стандарт'}</span>
        `;
        thead.appendChild(th);
    });
    
    const occupancyMap = buildOccupancyMap(bookings);
    
    for (let hour = 9; hour <= 21; hour++) {
        for (let minute = 0; minute < 60; minute += 30) {
            const timeStr = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
            
            const tr = document.createElement('tr');
            tr.dataset.time = timeStr;
            
            const timeTd = document.createElement('td');
            timeTd.className = 'time-label';
            timeTd.textContent = timeStr;
            tr.appendChild(timeTd);
            
            rooms.forEach(room => {
                const td = document.createElement('td');
                td.className = 'slot';
                td.dataset.roomId = room.id;
                td.dataset.time = timeStr;
                
                const key = `${room.id}_${timeStr}`;
                const booking = occupancyMap[key];
                
                if (booking) {
                    td.classList.add('occupied');
                    
                    // Проверяем, принадлежит ли бронь текущему пользователю
                    if (booking.user_id === currentUserId) {
                        td.classList.add('my-booking');
                        td.textContent = '⭐';
                        td.title = `Ваша бронь: ${booking.topic}`;
                        
                        // Добавляем кнопку отмены при клике
                        td.style.cursor = 'pointer';
                        td.onclick = function(e) {
                            e.stopPropagation();
                            cancelBooking(booking.id);
                        };
                    } else {
                        td.textContent = '🔴';
                        td.title = `Занято: ${booking.topic} (${booking.user_name || 'Пользователь'})`;
                        td.style.cursor = 'default';
                    }
                } else {
                    td.classList.add('free');
                    td.textContent = '⬜';
                    td.onclick = function() {
                        openBookingModal(room.id, room.name, formatDate(date), timeStr);
                    };
                }
                
                tr.appendChild(td);
            });
            
            tbody.appendChild(tr);
        }
    }
    
    updateStats(rooms, bookings);
}

// --- Отмена бронирования ---
async function cancelBooking(bookingId) {
    if (!confirm('Вы уверены, что хотите отменить эту бронь?')) {
        return;
    }
    
    try {
        const response = await fetch('api/booking_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: bookingId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Бронирование отменено');
            await refreshSchedule();
        } else {
            alert('❌ Ошибка: ' + (result.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка отмены:', error);
        alert('❌ Ошибка подключения к серверу');
    }
}

// --- Обновление статистики ---
function updateStats(rooms, bookings) {
    document.getElementById('totalRooms').textContent = rooms.length;
    
    const now = new Date();
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    let freeNow = 0;
    rooms.forEach(room => {
        const map = buildOccupancyMap(bookings);
        if (!map[`${room.id}_${currentTime}`]) freeNow++;
    });
    document.getElementById('freeNow').textContent = freeNow;
    
    const myBookings = bookings.filter(b => b.user_id === currentUserId);
    document.getElementById('myBookingsToday').textContent = myBookings.length;
}

// --- Обновление расписания ---
async function refreshSchedule() {
    try {
        const data = await fetchData();
        const dateObj = new Date(data.date + 'T00:00:00');
        renderSchedule(data.rooms, data.bookings, dateObj);
    } catch (error) {
        document.getElementById('scheduleBody').innerHTML = `
            <tr>
                <td colspan="10" style="padding: 40px; text-align: center; color: #999;">
                    ⚠️ Не удалось загрузить данные.
                </td>
            </tr>
        `;
    }
}

// --- Модальное окно ---
function openBookingModal(roomId, roomName, date, time) {
    selectedSlot = { roomId, roomName, date, time };
    
    document.getElementById('modalRoomName').textContent = roomName;
    document.getElementById('modalDate').textContent = date;
    document.getElementById('modalTime').textContent = time;
    document.getElementById('topicInput').value = '';
    document.getElementById('durationInput').value = '1';
    document.getElementById('needProjector').checked = false;
    
    document.getElementById('bookingModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('bookingModal').classList.remove('active');
    document.body.style.overflow = '';
    selectedSlot = null;
}

async function confirmBooking() {
    if (!selectedSlot) {
        alert('Ошибка: не выбрана ячейка');
        return;
    }
    
    const topic = document.getElementById('topicInput').value.trim();
    if (!topic) {
        alert('Введите тему встречи');
        return;
    }
    
    const duration = parseFloat(document.getElementById('durationInput').value);
    if (isNaN(duration) || duration <= 0) {
        alert('Укажите корректную длительность');
        return;
    }
    
    const startTime = selectedSlot.time;
    const endTime = addMinutes(startTime, duration * 60);
    
    if (compareTime(endTime, '22:00') > 0) {
        alert('Бронирование не может заканчиваться позже 22:00');
        return;
    }
    
    const bookingData = {
        room_id: selectedSlot.roomId,
        booking_date: selectedSlot.date,
        start_time: startTime,
        end_time: endTime,
        topic: topic,
        need_projector: document.getElementById('needProjector').checked ? 1 : 0
    };
    
    try {
        const response = await fetch('api/booking_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bookingData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal();
            await refreshSchedule();
            alert('✅ Бронирование успешно создано!');
        } else {
            alert('❌ Ошибка: ' + (result.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка бронирования:', error);
        alert('❌ Ошибка подключения к серверу');
    }
}

// --- Фильтры ---
function applyFilters() {
    const capacityFilter = parseInt(document.getElementById('capacityFilter').value);
    const projectorFilter = document.getElementById('projectorFilter').checked;
    const whiteboardFilter = document.getElementById('whiteboardFilter').checked;
    
    let filteredRooms = rooms.filter(room => {
        if (capacityFilter > 0 && room.capacity < capacityFilter) return false;
        if (projectorFilter && !room.has_projector) return false;
        if (whiteboardFilter && !room.has_whiteboard) return false;
        return true;
    });
    
    if (filteredRooms.length === 0) {
        alert('Нет комнат по фильтрам. Показаны все.');
        filteredRooms = rooms;
        document.getElementById('capacityFilter').value = '0';
        document.getElementById('projectorFilter').checked = false;
        document.getElementById('whiteboardFilter').checked = false;
    }
    
    renderSchedule(filteredRooms, bookings, currentDate);
}

function goToMyBookings() {
    const myBookings = bookings.filter(b => b.user_id === currentUserId);
    if (myBookings.length === 0) {
        alert('У вас нет активных броней.');
        return;
    }
    
    let message = '📋 Ваши активные брони:\n\n';
    myBookings.forEach((b, i) => {
        const room = rooms.find(r => r.id === b.room_id);
        message += `${i+1}. ${room ? room.name : 'Комната ' + b.room_id}\n`;
        message += `   📅 ${b.booking_date}\n`;
        message += `   ⏰ ${b.start_time} - ${b.end_time}\n`;
        message += `   📝 ${b.topic}\n\n`;
    });
    alert(message);
}

// --- События ---
document.addEventListener('DOMContentLoaded', async function() {
    // Проверяем авторизацию
    const auth = await checkAuth();
    if (!auth) return;
    
    // Устанавливаем дату
    document.getElementById('datePicker').value = formatDate(new Date());
    
    // Загружаем данные
    await refreshSchedule();
});

document.getElementById('bookingModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

document.getElementById('datePicker').addEventListener('change', refreshSchedule);

console.log('✅ Проект запущен!');