<?php
// GET /api/properties — listado público (solo PUBLICADA + published=1; filtra DESACTIVADA/BORRADA)
require_once dirname(__DIR__) . '/_config.php';
cors_headers();

if (method() !== 'GET') { send(405, ['error' => 'Método no permitido.']); }

// Auto-migración silenciosa: estado de publicación si la columna no existe
try { db()->exec("ALTER TABLE properties ADD COLUMN publish_state TEXT NOT NULL DEFAULT 'PUBLICADA'"); }
catch (PDOException $_e) { /* ya existe */ }

$stmt = db()->query(
    "SELECT * FROM properties
     WHERE published = 1
       AND (publish_state = 'PUBLICADA' OR publish_state IS NULL OR publish_state = '')
     ORDER BY is_featured DESC, created_at DESC"
);
$properties = $stmt->fetchAll();
$properties = array_map('parse_property', $properties);
$properties = attach_images($properties);

send(200, $properties);
