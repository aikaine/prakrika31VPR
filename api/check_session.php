<?php
// api/check_session.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'full_name' => $_SESSION['user_name'],
            'is_admin' => $_SESSION['is_admin']
        ]
    ]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>