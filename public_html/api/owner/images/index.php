<?php
// POST /api/owner/images?propertyId={id}
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$user = require_owner();

if (method() !== 'POST') { send(405, ['error' => 'Método no permitido.']); }

$propertyId = $_GET['propertyId'] ?? '';
if (!$propertyId) { send(400, ['error' => 'propertyId requerido.']); }

// Verificar que la propiedad pertenece al owner (o es admin)
if ($user['role'] !== 'admin') {
    $chk = db()->prepare('SELECT id FROM properties WHERE id = ? AND owner_id = ?');
    $chk->execute([$propertyId, $user['id']]);
    if (!$chk->fetch()) { send(403, ['error' => 'Sin permiso para esta propiedad.']); }
}

$b  = json_body();
$url = trim($b['url'] ?? '');
if (!$url) { send(400, ['error' => 'url requerida.']); }

$stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM property_images WHERE property_id = ?');
$stmt->execute([$propertyId]);
$sortOrder = (int) $stmt->fetchColumn();

$id  = uuid();
$now = date('Y-m-d H:i:s');

db()->prepare(
    'INSERT INTO property_images (id, property_id, url, alt_text, sort_order, created_at)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$id, $propertyId, $url, $b['alt_text'] ?? null, $sortOrder, $now]);

send(201, [
    'id'          => $id,
    'property_id' => $propertyId,
    'url'         => $url,
    'alt_text'    => $b['alt_text'] ?? null,
    'sort_order'  => $sortOrder,
]);
