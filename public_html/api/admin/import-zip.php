<?php
// POST /api/admin/import-zip
require_once dirname(__DIR__) . '/_config.php';
require_once __DIR__ . '/_zip_helper.php';
cors_headers();
require_admin();

if (method() !== 'POST') { send(405, ['error' => 'Método no permitido.']); }

if (empty($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE  => 'El archivo supera el límite del servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite del formulario.',
        UPLOAD_ERR_PARTIAL   => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE   => 'No se proporcionó ningún archivo.',
    ];
    $code = $_FILES['zip']['error'] ?? UPLOAD_ERR_NO_FILE;
    send(400, ['error' => $codes[$code] ?? 'Error al recibir el archivo.']);
}

$file = $_FILES['zip'];
if (!preg_match('/\.zip$/i', $file['name'])) {
    send(400, ['error' => 'Solo se aceptan archivos .zip']);
}

if (!class_exists('ZipArchive')) {
    send(500, ['error' => 'El servidor no tiene soporte para ZIP.']);
}

$zip    = new ZipArchive();
$opened = $zip->open($file['tmp_name']);
if ($opened !== true) {
    send(400, ['error' => 'No se pudo abrir el archivo ZIP (código: ' . $opened . ').']);
}

$result = extract_zip_contents($zip, pathinfo($file['name'], PATHINFO_FILENAME));
$zip->close();

send(200, $result);
