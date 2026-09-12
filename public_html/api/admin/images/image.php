<?php
// PUT    /api/admin/images/{imageId} — actualizar sort_order / alt_text
// DELETE /api/admin/images/{imageId} — eliminar imagen
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
require_admin();

$imageId = $_GET['imageId'] ?? '';
if (!$imageId) { send(400, ['error' => 'imageId requerido.']); }

// ── PUT ──────────────────────────────────────────────────────
if (method() === 'PUT') {
    $b    = json_body();
    $sets = [];
    $params = [];

    if (array_key_exists('sort_order', $b)) {
        $sets[] = 'sort_order = :sort_order';
        $params[':sort_order'] = (int) $b['sort_order'];
    }
    if (array_key_exists('alt_text', $b)) {
        $sets[] = 'alt_text = :alt_text';
        $params[':alt_text'] = $b['alt_text'];
    }
    if (array_key_exists('url', $b)) {
        $sets[] = 'url = :url';
        $params[':url'] = $b['url'];
    }

    if (empty($sets)) { send(400, ['error' => 'Nada para actualizar.']); }

    $params[':id'] = $imageId;
    db()->prepare('UPDATE property_images SET ' . implode(', ', $sets) . ' WHERE id = :id')
        ->execute($params);

    $stmt = db()->prepare('SELECT * FROM property_images WHERE id = ?');
    $stmt->execute([$imageId]);
    send(200, $stmt->fetch() ?: ['error' => 'Imagen no encontrada.']);
}

// ── DELETE ───────────────────────────────────────────────────
if (method() === 'DELETE') {
    $stmt = db()->prepare('DELETE FROM property_images WHERE id = ?');
    $stmt->execute([$imageId]);
    if ($stmt->rowCount() === 0) { send(404, ['error' => 'Imagen no encontrada.']); }
    send(200, ['success' => true]);
}

send(405, ['error' => 'Método no permitido.']);
