<?php
// DELETE /api/admin/users/{id}
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$admin = require_admin();

if (method() !== 'DELETE') { send(405, ['error' => 'Método no permitido.']); }

$id = $_GET['id'] ?? '';
if (!$id) { send(400, ['error' => 'ID requerido.']); }
if ($id === $admin['id']) { send(400, ['error' => 'No podés eliminar tu propio usuario.']); }

$stmt = db()->prepare('DELETE FROM users WHERE id = ? AND role = ?');
$stmt->execute([$id, 'owner']);

if ($stmt->rowCount() === 0) { send(404, ['error' => 'Usuario no encontrado.']); }
send(200, ['success' => true]);
