<?php
// GET  /api/admin/users — listar propietarios
// POST /api/admin/users — crear propietario
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
require_admin();

// ── GET ──────────────────────────────────────────────────────
if (method() === 'GET') {
    $stmt = db()->query(
        "SELECT id, email, role, full_name, created_at
         FROM users
         WHERE role = 'owner'
         ORDER BY created_at DESC"
    );
    send(200, $stmt->fetchAll());
}

// ── POST ─────────────────────────────────────────────────────
if (method() === 'POST') {
    $b         = json_body();
    $email     = trim($b['email']     ?? '');
    $full_name = trim($b['full_name'] ?? '');
    $password  = $b['password'] ?? '';

    if (!$email) { send(400, ['error' => 'El email es requerido.']); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send(400, ['error' => 'Formato de email inválido.']);
    }

    // Verificar que no exista
    $chk = db()->prepare('SELECT id FROM users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) { send(409, ['error' => 'Ya existe un usuario con ese email.']); }

    // Si no viene password, generamos uno temporal
    if (!$password) {
        $password = bin2hex(random_bytes(8));
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $id   = uuid();
    $now  = date('Y-m-d H:i:s');

    db()->prepare(
        'INSERT INTO users (id, email, password_hash, role, full_name, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$id, $email, $hash, 'owner', $full_name, $now, $now]);

    send(201, [
        'success'  => true,
        'userId'   => $id,
        'email'    => $email,
        'password' => $password, // Mostrar solo en creación para que admin lo comunique
    ]);
}

send(405, ['error' => 'Método no permitido.']);
