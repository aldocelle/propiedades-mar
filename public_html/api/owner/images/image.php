<?php
// DELETE /api/owner/images/{imageId}
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$user = require_owner();

if (method() !== 'DELETE') { send(405, ['error' => 'Método no permitido.']); }

$imageId = $_GET['imageId'] ?? '';
if (!$imageId) { send(400, ['error' => 'imageId requerido.']); }

// Verificar pertenencia si es owner
if ($user['role'] !== 'admin') {
    $stmt = db()->prepare(
        'SELECT pi.id FROM property_images pi
         JOIN properties p ON p.id = pi.property_id
         WHERE pi.id = ? AND p.owner_id = ?'
    );
    $stmt->execute([$imageId, $user['id']]);
    if (!$stmt->fetch()) { send(403, ['error' => 'Sin permiso para esta imagen.']); }
}

$stmt = db()->prepare('DELETE FROM property_images WHERE id = ?');
$stmt->execute([$imageId]);
if ($stmt->rowCount() === 0) { send(404, ['error' => 'Imagen no encontrada.']); }
send(200, ['success' => true]);
