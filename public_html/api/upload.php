<?php
// POST /api/upload.php — sube una imagen al servidor y devuelve su URL pública
require_once __DIR__ . '/_config.php';
cors_headers();
$user = require_owner(); // admin o owner pueden subir

if (method() !== 'POST') { send(405, ['error' => 'Método no permitido.']); }

// ── Validar archivo recibido ─────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se proporcionó ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida.',
    ];
    $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    send(400, ['error' => $codes[$code] ?? 'Error al recibir el archivo.']);
}

$file     = $_FILES['file'];
$maxBytes = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxBytes) {
    send(400, ['error' => 'La imagen supera el tamaño máximo de 10 MB.']);
}

// ── Validar tipo MIME real (no confiar en extensión) ─────────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array($mimeReal, $allowed, true)) {
    send(415, ['error' => 'Tipo de archivo no permitido. Usa JPG, PNG, WEBP o GIF.']);
}

$extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$ext = $extMap[$mimeReal];

// ── Preparar directorio de destino ───────────────────────────
// Relativo a api/ (…/public_html/uploads/properties/) — funciona igual en el
// hosting real y en una copia local de public_html.
$uploadDir = dirname(__DIR__) . '/uploads/properties/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        send(500, ['error' => 'No se pudo crear el directorio de imágenes.']);
    }
}

// ── Nombre único para evitar colisiones ──────────────────────
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    send(500, ['error' => 'No se pudo guardar la imagen en el servidor.']);
}

// ── URL pública dinámica ────────────────────────────────────
// En el hosting real devuelve https://propiedadesmar.cl/uploads/...;
// en 127.0.0.1:<puerto> devuelve la URL local para probar sin producción.
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($host !== '' && preg_match('#^(localhost|127\.0\.0\.1)(:\d+)?$#', $host)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $host;
} else {
    $base = rtrim(getenv('PM_PUBLIC_BASE') ?: 'https://propiedadesmar.cl', '/');
}
$publicUrl = $base . '/uploads/properties/' . $filename;

send(200, [
    'url'  => $publicUrl,
    'path' => 'uploads/properties/' . $filename,
]);
