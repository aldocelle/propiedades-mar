<?php
// GET    /api/admin/properties/{id}
// PUT    /api/admin/properties/{id}
// DELETE /api/admin/properties/{id}
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$admin = require_admin();

$id = $_GET['id'] ?? '';
if (!$id) { send(400, ['error' => 'ID requerido.']); }

// Auto-migración silenciosa
foreach (['created_by_id', 'updated_by_id'] as $_col) {
    try { db()->exec("ALTER TABLE properties ADD COLUMN $_col TEXT REFERENCES users(id) ON DELETE SET NULL"); }
    catch (PDOException $_e) { /* ya existe */ }
}
try { db()->exec("ALTER TABLE properties ADD COLUMN publish_state TEXT NOT NULL DEFAULT 'PUBLICADA'"); }
catch (PDOException $_e) { /* ya existe */ }

if (!function_exists('valid_publish_state')) {
    function valid_publish_state(mixed $v): string {
        return in_array($v, ['PUBLICADA', 'NO_PUBLICADA', 'DESACTIVADA', 'BORRADA'], true)
            ? $v : 'PUBLICADA';
    }
}

// ── GET ──────────────────────────────────────────────────────
if (method() === 'GET') {
    $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    $prop = $stmt->fetch();
    if (!$prop) { send(404, ['error' => 'Propiedad no encontrada.']); }
    $prop = parse_property($prop);
    [$prop] = attach_images([$prop]);
    send(200, $prop);
}

// ── PUT ──────────────────────────────────────────────────────
if (method() === 'PUT') {
    $b = json_body();

    // Construir SET dinámico solo con los campos enviados
    $allowed = [
        'title', 'slug', 'description', 'address', 'city',
        'price', 'price_type', 'bedrooms', 'bathrooms', 'area_sq_m',
        'superficie_util', 'superficie_terraza', 'estacionamientos', 'bodegas_count',
        'property_type', 'operation_type', 'status', 'publish_state',
        'is_featured', 'published', 'amenities',
        'orientacion', 'amoblado', 'mascotas',
        'numero_piso', 'departamentos_por_piso', 'cantidad_pisos', 'antiguedad',
        'tipo_departamento', 'numero_departamento', 'max_habitantes',
        'gastos_comunes', 'owner_id',
    ];

    $sets   = [];
    $params = [];

    foreach ($allowed as $col) {
        if (!array_key_exists($col, $b)) continue;
        $val = $b[$col];

        // Casteos según columna
        if (in_array($col, ['price', 'gastos_comunes'], true)) {
            $val = $val !== null ? (float) $val : null;
        } elseif (in_array($col, ['bedrooms','bathrooms','area_sq_m','superficie_util',
                'superficie_terraza','estacionamientos','bodegas_count','numero_piso',
                'departamentos_por_piso','cantidad_pisos','antiguedad','max_habitantes'], true)) {
            $val = $val !== null && $val !== '' ? (int) $val : null;
        } elseif (in_array($col, ['is_featured','published'], true)) {
            $val = (int) (bool) $val;
            // Sincroniza publish_state cuando cambia el bool published
            if ($col === 'published' && !isset($b['publish_state'])) {
                $sets[] = "`publish_state` = :publish_state_sync";
                $params[':publish_state_sync'] = $val ? 'PUBLICADA' : 'NO_PUBLICADA';
            }
        } elseif ($col === 'amenities') {
            $val = json_encode(is_array($val) ? $val : []);
        } elseif ($col === 'price_type') {
            $val = in_array($val, ['CLP','UF']) ? $val : 'CLP';
        } elseif ($col === 'publish_state') {
            // Estado de publicación con whitelist + sincroniza el bool published
            $val = valid_publish_state($val);
            $b['published'] = ($val === 'PUBLICADA');
        }

        $sets[]          = "`$col` = :$col";
        $params[":$col"] = $val;
    }

    if (empty($sets)) { send(400, ['error' => 'No hay campos para actualizar.']); }

    $sets[]      = '`updated_at` = :updated_at';
    $sets[]      = '`updated_by_id` = :updated_by_id';
    $params[':updated_at']    = date('Y-m-d H:i:s');
    $params[':updated_by_id'] = $admin['id'];
    $params[':id']            = $id;

    $sql = 'UPDATE properties SET ' . implode(', ', $sets) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);

    $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    $prop = $stmt->fetch();
    if (!$prop) { send(404, ['error' => 'Propiedad no encontrada.']); }
    send(200, parse_property($prop));
}

// ── DELETE ───────────────────────────────────────────────────
if (method() === 'DELETE') {
    // Borrado SOFT: pasa a estado BORRADA (queda en el panel, filtrado del sitio público)
    $stmt = db()->prepare(
        "UPDATE properties
         SET publish_state = 'BORRADA', published = 0, updated_at = ?, updated_by_id = ?
         WHERE id = ?"
    );
    $stmt->execute([date('Y-m-d H:i:s'), $admin['id'], $id]);
    $chk = db()->prepare('SELECT id FROM properties WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) { send(404, ['error' => 'Propiedad no encontrada.']); }
    send(200, ['success' => true, 'publish_state' => 'BORRADA']);
}

send(405, ['error' => 'Método no permitido.']);
