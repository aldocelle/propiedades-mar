<?php
/**
 * DIAGNÓSTICO TEMPORAL — Express Website
 *
 * Prueba la conexión a base de datos con la config actual, en texto plano,
 * y de paso limpia OPcache si se pide con ?reset=1. Solo accesible con el
 * token. Borrar este archivo del servidor cuando el formulario funcione.
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '0');

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
if ($token !== 'a94ff465bbbeb5745590') {
    http_response_code(404);
    exit('no encontrado');
}

echo "=== Express Website — diagnóstico ===\n\n";
echo "PHP: " . PHP_VERSION . "\n";

if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    echo "OPcache activo: " . ($st ? 'sí' : 'no') . "\n";
} else {
    echo "OPcache: función no disponible\n";
}

if (isset($_GET['reset']) && function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo "opcache_reset(): " . ($ok ? 'OK, cache limpiado' : 'falló') . "\n";
}

echo "\n--- archivo db_credentials.php en disco ---\n";
echo "mtime: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/db_credentials.php')) . "\n";
$cred = (array) (require __DIR__ . '/db_credentials.php');
echo "usuario remoto en el archivo: " . ($cred['remoto']['user'] ?? '?') . "\n";
echo "clave remota (primeros 3 caracteres): " . substr((string) ($cred['remoto']['pass'] ?? ''), 0, 3) . "...\n";

echo "\n--- intento de conexión ---\n";
require __DIR__ . '/db_config.php';
echo "entorno detectado: " . $entorno . "\n";
echo "host_http: " . $http_host . "\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "connect_errno: " . $conn->connect_errno . "\n";
echo "connect_error: " . ($conn->connect_error ?? '(ninguno)') . "\n";

if (!$conn->connect_errno) {
    $r = $conn->query("SHOW TABLES LIKE 'contactos'");
    echo "tabla contactos existe: " . ($r && $r->num_rows > 0 ? 'sí' : 'no') . "\n";
}
