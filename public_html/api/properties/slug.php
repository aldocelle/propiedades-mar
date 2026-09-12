<?php
// GET /api/properties/{slug} — detalle público de una propiedad
require_once dirname(__DIR__) . '/_config.php';
cors_headers();

if (method() !== 'GET') { send(405, ['error' => 'Método no permitido.']); }

$slug = $_GET['slug'] ?? '';
if (!$slug) { send(400, ['error' => 'Slug requerido.']); }

$stmt = db()->prepare(
    "SELECT * FROM properties WHERE slug = ? AND published = 1"
);
$stmt->execute([$slug]);
$prop = $stmt->fetch();

if (!$prop) { send(404, ['error' => 'Propiedad no encontrada.']); }

$prop = parse_property($prop);
[$prop] = attach_images([$prop]);

send(200, $prop);
