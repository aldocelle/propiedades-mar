<?php
require_once dirname(__DIR__) . '/_config.php';
cors_headers();

if (method() !== 'POST') { send(405, ['error' => 'Método no permitido.']); }

// Rate limiting básico por IP (en memoria de sesión PHP no persiste entre requests,
// usar un campo en DB o APCu si Ferozo lo tiene disponible)
$body  = json_body();
$email = trim($body['email'] ?? '');
$pass  = $body['password'] ?? '';

if (!$email || !$pass) {
    send(400, ['error' => 'Email y contraseña son requeridos.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send(400, ['error' => 'Formato de email inválido.']);
}

$stmt = db()->prepare('SELECT id, email, password_hash, role, full_name FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    // Mismo mensaje para ambos casos (no revelar si el email existe)
    send(401, ['error' => 'Email o contraseña incorrectos.']);
}

start_session();
session_regenerate_id(true); // Previene session fixation
$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];

send(200, [
    'success' => true,
    'user'    => [
        'id'        => $user['id'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'full_name' => $user['full_name'],
    ],
]);
