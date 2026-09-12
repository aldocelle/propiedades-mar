<?php
// POST /api/admin/download-dropbox  { url: "https://www.dropbox.com/..." }
require_once dirname(__DIR__) . '/_config.php';
require_once __DIR__ . '/_zip_helper.php';
cors_headers();
require_admin();

if (method() !== 'POST') { send(405, ['error' => 'Método no permitido.']); }

$b   = json_body();
$url = trim($b['url'] ?? '');

if (!$url) { send(400, ['error' => 'URL requerida.']); }
if (!preg_match('#^https://(?:www\.)?dropbox\.com/#', $url)) {
    send(400, ['error' => 'Solo se aceptan links de Dropbox.']);
}

// Convertir link compartido a descarga directa como ZIP
$dlUrl = preg_replace('/([?&])dl=\d/', '$1dl=1', $url);
if (!str_contains($dlUrl, 'dl=1')) {
    $dlUrl .= (str_contains($dlUrl, '?') ? '&' : '?') . 'dl=1';
}

// ── Descargar el ZIP ──────────────────────────────────────────
if (!function_exists('curl_init')) {
    send(500, ['error' => 'El servidor no tiene cURL habilitado.']);
}

$tmp = tempnam(sys_get_temp_dir(), 'dbx');
$fp  = fopen($tmp, 'wb');

$ch = curl_init($dlUrl);
curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 8,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 PropiedadesMar/1.0',
    CURLOPT_SSL_VERIFYPEER => true,
]);
$ok       = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);
fclose($fp);

if (!$ok || $httpCode >= 400) {
    @unlink($tmp);
    send(502, ['error' => $curlErr ?: "Dropbox respondió con HTTP $httpCode. Verifica que el link sea público."]);
}

// Verificar que sea un ZIP real
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp);
if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
    @unlink($tmp);
    send(502, ['error' => "Dropbox no devolvió un ZIP válido (tipo: $mime). Asegúrate de que el link sea a una carpeta compartida pública."]);
}

// ── Procesar el ZIP ───────────────────────────────────────────
if (!class_exists('ZipArchive')) {
    @unlink($tmp);
    send(500, ['error' => 'El servidor no tiene soporte para ZIP.']);
}

$zip    = new ZipArchive();
$opened = $zip->open($tmp);
if ($opened !== true) {
    @unlink($tmp);
    send(500, ['error' => 'No se pudo leer el ZIP descargado (código: ' . $opened . ').']);
}

// Extraer nombre para el slug desde la URL
preg_match('#/([^/?&#]+)(?:[?&#]|$)#', parse_url($url, PHP_URL_PATH), $m);
$sourceName = $m[1] ?? 'propiedad';

$result = extract_zip_contents($zip, $sourceName);
$zip->close();
@unlink($tmp);

send(200, $result);
