<?php
// PUT    /api/owner/properties/{id}
// DELETE /api/owner/properties/{id}
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$user = require_owner();

$id = $_GET['id'] ?? '';
if (!$id) { send(400, ['error' => 'ID requerido.']); }

// Auto-migración silenciosa
foreach (['created_by_id', 'updated_by_id'] as $_col) {
    try { db()->exec("ALTER TABLE properties ADD COLUMN $_col TEXT REFERENCES users(id) ON DELETE SET NULL"); }
    catch (PDOException $_e) { /* ya existe */ }
}

// Verificar pertenencia
$stmt = db()->prepare('SELECT id, owner_id FROM properties WHERE id = ?');
$stmt->execute([$id]);
$prop = $stmt->fetch();
if (!$prop) { send(404, ['error' => 'Propiedad no encontrada.']); }
if ($user['role'] !== 'admin' && $prop['owner_id'] !== $user['id']) {
    send(403, ['error' => 'No tenés permiso para modificar esta propiedad.']);
}

// ── PUT ──────────────────────────────────────────────────────
if (method() === 'PUT') {
    $b = json_body();
    $allowed = [
        'title','slug','description','address','city',
        'price','price_type','bedrooms','bathrooms','area_sq_m',
        'property_type','operation_type','status','is_featured','published',
        'amenities','amoblado','mascotas','estacionamientos','bodegas_count',
        'superficie_util','superficie_terraza','gastos_comunes',
        'orientacion','numero_piso','cantidad_pisos','antiguedad',
    ];
    $sets = []; $params = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $b)) continue;
        $val = $b[$col];
        if (in_array($col, ['price','gastos_comunes'], true))
            $val = $val !== null ? (float) $val : null;
        elseif (in_array($col, ['bedrooms','bathrooms','area_sq_m','superficie_util',
                'superficie_terraza','estacionamientos','bodegas_count',
                'numero_piso','cantidad_pisos','antiguedad'], true))
            $val = $val !== null && $val !== '' ? (int) $val : null;
        elseif (in_array($col, ['is_featured','published'], true))
            $val = (int)(bool) $val;
        elseif ($col === 'amenities')
            $val = json_encode(is_array($val) ? $val : []);
        elseif ($col === 'price_type')
            $val = in_array($val, ['CLP','UF']) ? $val : 'CLP';
        $sets[] = "`$col` = :$col";
        $params[":$col"] = $val;
    }
    if (empty($sets)) { send(400, ['error' => 'Nada para actualizar.']); }
    $sets[] = '`updated_at` = :updated_at';
    $sets[] = '`updated_by_id` = :updated_by_id';
    $params[':updated_at']    = date('Y-m-d H:i:s');
    $params[':updated_by_id'] = $user['id'];
    $params[':id']            = $id;
    db()->prepare('UPDATE properties SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    send(200, parse_property($stmt->fetch()));
}

// ── DELETE ───────────────────────────────────────────────────
if (method() === 'DELETE') {
    db()->prepare('DELETE FROM properties WHERE id = ?')->execute([$id]);
    send(200, ['success' => true]);
}

send(405, ['error' => 'Método no permitido.']);
