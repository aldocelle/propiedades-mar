<?php
// GET  /api/owner/properties — propiedades del propietario logueado
// POST /api/owner/properties — crear propiedad (propietario)
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
$user = require_owner();

// ── GET ──────────────────────────────────────────────────────
if (method() === 'GET') {
    // Admin ve todas; owner solo las suyas
    if ($user['role'] === 'admin') {
        $stmt = db()->query('SELECT * FROM properties ORDER BY created_at DESC');
    } else {
        $stmt = db()->prepare('SELECT * FROM properties WHERE owner_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['id']]);
    }
    $properties = $stmt->fetchAll();
    $properties = array_map('parse_property', $properties);
    $properties = attach_images($properties);
    send(200, $properties);
}

// ── POST ─────────────────────────────────────────────────────
if (method() === 'POST') {
    $b    = json_body();
    $title = trim($b['title'] ?? '');
    $slug  = trim($b['slug']  ?? '');
    $price = $b['price'] ?? null;

    if (!$title || !$slug || $price === null || $price === '') {
        send(400, ['error' => 'Campos requeridos: title, slug, price.']);
    }

    $id  = uuid();
    $now = date('Y-m-d H:i:s');

    db()->prepare(
        "INSERT INTO properties (id, title, slug, description, address, city,
            price, price_type, bedrooms, bathrooms, area_sq_m,
            property_type, operation_type, status, is_featured, published,
            amenities, amoblado, mascotas, estacionamientos, bodegas_count,
            owner_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $id, $title, $slug,
        $b['description'] ?? null,
        $b['address']     ?? null,
        $b['city']        ?? null,
        (float) $price,
        in_array($b['price_type'] ?? '', ['CLP','UF']) ? $b['price_type'] : 'CLP',
        isset($b['bedrooms'])  ? (int) $b['bedrooms']  : null,
        isset($b['bathrooms']) ? (int) $b['bathrooms'] : null,
        isset($b['area_sq_m']) ? (int) $b['area_sq_m'] : null,
        $b['property_type']  ?? null,
        $b['operation_type'] ?? 'Venta',
        $b['status']         ?? 'available',
        (int) ($b['is_featured'] ?? 0),
        isset($b['published']) ? (int) $b['published'] : 1,
        json_encode($b['amenities'] ?? []),
        $b['amoblado'] ?? 'Sin amoblar',
        $b['mascotas'] ?? 'Consultar',
        (int) ($b['estacionamientos'] ?? 0),
        (int) ($b['bodegas_count']    ?? 0),
        $user['id'],
        $now, $now,
    ]);

    $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    send(201, parse_property($stmt->fetch()));
}

send(405, ['error' => 'Método no permitido.']);
