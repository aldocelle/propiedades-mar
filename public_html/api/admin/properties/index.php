<?php
// GET  /api/admin/properties — todas las propiedades
// POST /api/admin/properties — crear propiedad
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$admin = require_admin();

// Auto-migración: agrega columnas de auditoría y estado de publicación si no existen
foreach (['created_by_id', 'updated_by_id'] as $_col) {
    try { db()->exec("ALTER TABLE properties ADD COLUMN $_col TEXT REFERENCES users(id) ON DELETE SET NULL"); }
    catch (PDOException $_e) { /* columna ya existe, ignorar */ }
}
try { db()->exec("ALTER TABLE properties ADD COLUMN publish_state TEXT NOT NULL DEFAULT 'PUBLICADA'"); }
catch (PDOException $_e) { /* ya existe */ }

// Estados válidos de publicación (Publicada/No publicada/Desactivada/Borrada)
if (!function_exists('valid_publish_state')) {
    function valid_publish_state(mixed $v): string {
        return in_array($v, ['PUBLICADA', 'NO_PUBLICADA', 'DESACTIVADA', 'BORRADA'], true)
            ? $v : 'PUBLICADA';
    }
}

// ── GET ──────────────────────────────────────────────────────
if (method() === 'GET') {
    $stmt = db()->query(
        "SELECT p.*,
                uc.full_name AS created_by_name,
                uu.full_name AS updated_by_name
         FROM properties p
         LEFT JOIN users uc ON p.created_by_id = uc.id
         LEFT JOIN users uu ON p.updated_by_id = uu.id
         ORDER BY p.created_at DESC"
    );
    $properties = $stmt->fetchAll();
    $properties = array_map('parse_property', $properties);
    $properties = attach_images($properties);
    send(200, $properties);
}

// ── POST ─────────────────────────────────────────────────────
if (method() === 'POST') {
    $b = json_body();

    $title = trim($b['title'] ?? '');
    $slug  = trim($b['slug']  ?? '');
    $price = $b['price'] ?? null;

    if (!$title || !$slug || $price === null || $price === '') {
        send(400, ['error' => 'Campos requeridos: title, slug, price.']);
    }

    $id = uuid();
    $now = date('Y-m-d H:i:s');

    $stmt = db()->prepare(
        "INSERT INTO properties (
            id, title, slug, description, address, city,
            price, price_type, bedrooms, bathrooms, area_sq_m,
            superficie_util, superficie_terraza, estacionamientos, bodegas_count,
            property_type, operation_type, status, publish_state,
            is_featured, published, amenities,
            orientacion, amoblado, mascotas,
            numero_piso, cantidad_pisos, antiguedad,
            gastos_comunes, owner_id, created_by_id, updated_by_id, created_at, updated_at
        ) VALUES (
            :id, :title, :slug, :description, :address, :city,
            :price, :price_type, :bedrooms, :bathrooms, :area_sq_m,
            :superficie_util, :superficie_terraza, :estacionamientos, :bodegas_count,
            :property_type, :operation_type, :status, :publish_state,
            :is_featured, :published, :amenities,
            :orientacion, :amoblado, :mascotas,
            :numero_piso, :cantidad_pisos, :antiguedad,
            :gastos_comunes, :owner_id, :created_by_id, :updated_by_id, :created_at, :updated_at
        )"
    );

    $stmt->execute([
        ':id'                 => $id,
        ':title'              => $title,
        ':slug'               => $slug,
        ':description'        => $b['description']       ?? null,
        ':address'            => $b['address']           ?? null,
        ':city'               => $b['city']              ?? null,
        ':price'              => (float) $price,
        ':price_type'         => in_array($b['price_type'] ?? '', ['CLP','UF']) ? $b['price_type'] : 'CLP',
        ':bedrooms'           => isset($b['bedrooms'])   ? (int) $b['bedrooms']   : null,
        ':bathrooms'          => isset($b['bathrooms'])  ? (int) $b['bathrooms']  : null,
        ':area_sq_m'          => isset($b['area_sq_m'])  ? (int) $b['area_sq_m']  : null,
        ':superficie_util'    => isset($b['superficie_util'])    ? (int) $b['superficie_util']    : null,
        ':superficie_terraza' => isset($b['superficie_terraza']) ? (int) $b['superficie_terraza'] : null,
        ':estacionamientos'   => (int) ($b['estacionamientos'] ?? 0),
        ':bodegas_count'      => (int) ($b['bodegas_count']    ?? 0),
        ':property_type'      => $b['property_type']     ?? null,
        ':operation_type'     => $b['operation_type']    ?? 'Venta',
        ':status'             => $b['status']            ?? 'available',
        ':publish_state'      => valid_publish_state($b['publish_state'] ?? ($b['published'] ?? 1 ? 'PUBLICADA' : 'NO_PUBLICADA')),
        ':is_featured'        => (int) ($b['is_featured'] ?? 0),
        ':published'          => isset($b['published']) ? (int) $b['published'] : 1,
        ':amenities'          => json_encode($b['amenities'] ?? []),
        ':orientacion'        => $b['orientacion']       ?? null,
        ':amoblado'           => $b['amoblado']          ?? 'Sin amoblar',
        ':mascotas'           => $b['mascotas']          ?? 'Consultar',
        ':numero_piso'        => isset($b['numero_piso'])    ? (int) $b['numero_piso']    : null,
        ':cantidad_pisos'     => isset($b['cantidad_pisos']) ? (int) $b['cantidad_pisos'] : null,
        ':antiguedad'         => isset($b['antiguedad'])     ? (int) $b['antiguedad']     : null,
        ':gastos_comunes'     => isset($b['gastos_comunes']) ? (float) $b['gastos_comunes'] : null,
        ':owner_id'           => $admin['id'],
        ':created_by_id'      => $admin['id'],
        ':updated_by_id'      => $admin['id'],
        ':created_at'         => $now,
        ':updated_at'         => $now,
    ]);

    $stmt2 = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt2->execute([$id]);
    $prop = parse_property($stmt2->fetch());

    send(201, $prop);
}

send(405, ['error' => 'Método no permitido.']);
