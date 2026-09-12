<?php
/**
 * PROCESADOR DE FORMULARIO DE CONTACTO
 * Express Website
 *
 * Contrato: siempre responde JSON. Guarda en base de datos y, si PHPMailer
 * está disponible y configurado, además notifica por correo. Un fallo en el
 * correo no invalida el envío: el mensaje ya quedó guardado.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Los errores se registran, nunca se muestran: no deben contaminar el JSON
// ni exponer credenciales o rutas al visitante.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Ferozo no deja llegar al log de PHP por FTP. Se registra junto al sitio, en
// un .log que el .htaccess bloquea al público.
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');

$response = ['success' => false, 'message' => ''];

/** Responde y termina. */
function responder(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function limpiar(string $valor): string {
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}

// DIAGNÓSTICO TEMPORAL — un GET con este parámetro muestra el estado real de
// la conexión sin pasar por el formulario. Quitar este bloque junto con el
// resto del diagnóstico cuando el envío quede verificado.
if (array_key_exists('estado', $_GET)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "QUERY_STRING crudo: " . ($_SERVER['QUERY_STRING'] ?? '(vacío)') . "\n";
    echo "\$_GET recibido: " . var_export($_GET, true) . "\n\n";
    if (($_GET['estado'] ?? '') !== 'da0c7c41c746974a429e08ca') {
        echo "El token no coincide, me detengo aquí a propósito.\n";
        exit;
    }
    echo "PHP: " . PHP_VERSION . "\n";
    if (function_exists('opcache_get_status')) {
        $st = @opcache_get_status(false);
        echo "OPcache activo: " . ($st ? 'sí' : 'no') . "\n";
        if (isset($_GET['reset']) && function_exists('opcache_reset')) {
            echo "opcache_reset(): " . (opcache_reset() ? 'OK' : 'falló') . "\n";
        }
    }
    echo "\ndb_credentials.php mtime: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/db_credentials.php')) . "\n";
    $cred = (array) (require __DIR__ . '/db_credentials.php');
    echo "usuario en archivo: " . ($cred['remoto']['user'] ?? '?') . "\n";
    echo "clave en archivo (primeros 3): " . substr((string) ($cred['remoto']['pass'] ?? ''), 0, 3) . "...\n";
    echo "\nrequire db_config.php:\n";
    require __DIR__ . '/db_config.php';
    echo "entorno: " . $entorno . " | host_http: " . $http_host . "\n";
    echo "DB_HOST=" . DB_HOST . " DB_NAME=" . DB_NAME . " DB_USER=" . DB_USER . "\n";
    echo "connect_errno: " . $conn->connect_errno . "\n";
    echo "connect_error: " . ($conn->connect_error ?: '(ninguno)') . "\n";
    if (!$conn->connect_errno) {
        $r = $conn->query("SHOW TABLES LIKE 'contactos'");
        echo "tabla contactos: " . ($r && $r->num_rows > 0 ? 'existe' : 'no existe') . "\n";
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    // DIAGNÓSTICO TEMPORAL: si ves este texto con "(v2)", el servidor SÍ está
    // ejecutando el archivo actualizado. Revertir a "Método no permitido."
    $response['message'] = 'Método no permitido. (v2 ' . date('H:i:s') . ')';
    responder($response);
}

// ==========================================================
// ENTRADA
// ==========================================================

$nombre   = limpiar((string)($_POST['nombre']   ?? ''));
$email    = limpiar((string)($_POST['email']    ?? ''));
$tipo     = limpiar((string)($_POST['tipo']     ?? ''));
$mensaje  = limpiar((string)($_POST['mensaje']  ?? ''));
$empresa  = limpiar((string)($_POST['empresa']  ?? ''));
$telefono = limpiar((string)($_POST['telefono'] ?? ''));

// ==========================================================
// ANTI-BOT: honeypot + control de tiempo
// Sin captcha ni servicios externos, a propósito (sin build, sin claves que
// gestionar). Un bot detectado recibe la MISMA respuesta de éxito que un
// envío real, pero no se guarda ni se notifica: así no aprende a evitar la
// trampa. El control de tiempo solo se aplica si "ts" llegó (lo pone el JS
// al cargar el formulario); sin JS se omite, para no romper el envío nativo.
// ==========================================================

$respuestaFalsa = [
    'success' => true,
    'message' => 'Mensaje recibido. Te respondemos dentro de un día hábil.',
];

$honeypot = trim((string)($_POST['sitio_web'] ?? ''));
if ($honeypot !== '') {
    error_log('[contacto] Honeypot activado, IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    responder($respuestaFalsa);
}

$ts = (int)($_POST['ts'] ?? 0);
if ($ts > 0) {
    $transcurridoMs = (int)round(microtime(true) * 1000) - $ts;
    if ($transcurridoMs < 2500) {
        error_log('[contacto] Envío demasiado rápido (' . $transcurridoMs . 'ms), IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        responder($respuestaFalsa);
    }
}

// ==========================================================
// VALIDACIÓN EN SERVIDOR
// ==========================================================

if (mb_strlen($nombre) < 2) {
    $response['message'] = 'Escribe tu nombre para saber con quién hablamos.';
    responder($response);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Revisa el correo: falta el @ o el dominio.';
    responder($response);
}
if ($tipo === '') {
    $response['message'] = 'Selecciona qué necesitas para dirigir tu consulta.';
    responder($response);
}
if (mb_strlen($mensaje) < 10) {
    $response['message'] = 'Cuéntanos un poco más del proceso, con al menos 10 caracteres.';
    responder($response);
}

// Solo se aceptan los tipos que ofrece el formulario. Evita guardar basura
// y mantiene la columna consultable por categoría.
$tiposValidos = [
    'diagnostico', 'desarrollo', 'integracion', 'automatizacion',
    'modernizacion', 'sitio-pyme', 'otro',
];
if (!in_array($tipo, $tiposValidos, true)) {
    $tipo = 'otro';
}

// Truncados alineados al ancho real de cada columna de `contactos`.
$nombre   = mb_substr($nombre,   0, 100);
$email    = mb_substr($email,    0, 120);
$empresa  = mb_substr($empresa,  0, 150);
$telefono = mb_substr($telefono, 0, 20);
$mensaje  = mb_substr($mensaje,  0, 8000);

$ip        = $_SERVER['REMOTE_ADDR']     ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$fecha     = date('Y-m-d H:i:s');

// ==========================================================
// PERSISTENCIA
// ==========================================================

require_once __DIR__ . '/db_config.php';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    error_log('[contacto] Sin conexión a base de datos.');
    http_response_code(503);
    $response['message'] = 'No pudimos registrar tu mensaje. Escríbenos a contacto@expresswebsite.cl o por WhatsApp al +56 9 8790 1080.';
    // DIAGNÓSTICO TEMPORAL — quitar este bloque cuando el formulario esté verificado.
    if (($_GET['debug'] ?? '') === 'da0c7c41c746974a429e08ca') {
        $response['debug'] = [
            'punto'         => 'conexion',
            'connect_errno' => isset($conn) ? $conn->connect_errno : null,
            'connect_error' => isset($conn) ? $conn->connect_error : 'conn no definido',
            'entorno'       => $entorno ?? '?',
            'host_http'     => $http_host ?? '?',
            'db'            => defined('DB_NAME') ? DB_NAME : '?',
            'usuario'       => defined('DB_USER') ? DB_USER : '?',
            'servidor'      => defined('DB_HOST') ? DB_HOST : '?',
        ];
    }
    responder($response);
}

// Límite por IP: 4 envíos en 10 minutos. Cubre reintentos legítimos (un
// usuario corrigiendo un error) sin dejar pasar un script insistiendo.
$stmtLimite = $conn->prepare(
    'SELECT COUNT(*) FROM contactos WHERE ip_address = ? AND fecha_creacion >= (NOW() - INTERVAL 10 MINUTE)'
);
if ($stmtLimite !== false) {
    $stmtLimite->bind_param('s', $ip);
    $stmtLimite->execute();
    $stmtLimite->bind_result($enviosRecientes);
    $stmtLimite->fetch();
    $stmtLimite->close();
    if ($enviosRecientes >= 4) {
        error_log('[contacto] Límite de envíos alcanzado, IP ' . $ip);
        $conn->close();
        http_response_code(429);
        $response['message'] = 'Ya recibimos varios mensajes tuyos. Espera unos minutos antes de enviar otro, o escríbenos por WhatsApp.';
        responder($response);
    }
}

$sql = 'INSERT INTO contactos
            (nombre, email, tipo, mensaje, empresa, telefono, ip_address, user_agent, fecha_creacion, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log('[contacto] Error al preparar consulta: ' . $conn->error);
    http_response_code(500);
    $response['message'] = 'No pudimos registrar tu mensaje. Escríbenos a contacto@expresswebsite.cl o por WhatsApp al +56 9 8790 1080.';
    // DIAGNÓSTICO TEMPORAL — quitar este bloque cuando el formulario esté verificado.
    if (($_GET['debug'] ?? '') === 'da0c7c41c746974a429e08ca') {
        $response['debug'] = ['punto' => 'prepare', 'error' => $conn->error];
    }
    responder($response);
}

$estado = 'nuevo';
$stmt->bind_param('ssssssssss', $nombre, $email, $tipo, $mensaje, $empresa, $telefono, $ip, $userAgent, $fecha, $estado);

if (!$stmt->execute()) {
    $errorEjecucion = $stmt->error;
    error_log('[contacto] Error al guardar: ' . $errorEjecucion);
    $stmt->close();
    $conn->close();
    http_response_code(500);
    $response['message'] = 'No pudimos registrar tu mensaje. Escríbenos a contacto@expresswebsite.cl o por WhatsApp al +56 9 8790 1080.';
    // DIAGNÓSTICO TEMPORAL — quitar este bloque cuando el formulario esté verificado.
    if (($_GET['debug'] ?? '') === 'da0c7c41c746974a429e08ca') {
        $response['debug'] = ['punto' => 'execute', 'error' => $errorEjecucion];
    }
    responder($response);
}

$stmt->close();
$conn->close();

// El mensaje ya está guardado: a partir de aquí el envío es exitoso
// aunque la notificación por correo falle.
$response['success'] = true;
$response['message'] = 'Mensaje recibido. Te respondemos dentro de un día hábil.';

// ==========================================================
// NOTIFICACIÓN POR CORREO (opcional, no bloqueante)
// ==========================================================

$rutaPHPMailer = __DIR__ . '/phpmailer/src/';
$archivos = ['PHPMailer.php', 'SMTP.php', 'Exception.php'];
$disponible = true;
foreach ($archivos as $archivo) {
    if (!is_readable($rutaPHPMailer . $archivo)) {
        $disponible = false;
        error_log('[contacto] Falta ' . $archivo . ': se omite la notificación por correo.');
        break;
    }
}

if ($disponible) {
    foreach ($archivos as $archivo) {
        require_once $rutaPHPMailer . $archivo;
    }

    try {
        // Nombre completamente cualificado: evita un `use` fuera del inicio del archivo.
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPDebug = 0; // Nunca depurar hacia la respuesta del visitante.
        // Credenciales: variables de entorno primero, si no smtp_config.php.
        $smtp = ['host' => '', 'user' => '', 'pass' => '', 'port' => 465];
        if (is_readable(__DIR__ . '/smtp_config.php')) {
            $smtp = array_merge($smtp, (array)(require __DIR__ . '/smtp_config.php'));
        }

        $mail->Host       = getenv('SMTP_HOST') ?: $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER') ?: $smtp['user'];
        $mail->Password   = getenv('SMTP_PASS') ?: $smtp['pass'];
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = (int)$smtp['port'];
        $mail->CharSet   = 'UTF-8';

        $mail->setFrom('contacto@expresswebsite.cl', 'Express Website');
        $mail->addAddress('celle.aldo@gmail.com', 'Express Website');
        $mail->addReplyTo($email, $nombre);

        $mail->Subject = 'Nueva consulta: ' . $tipo . ' — ' . $nombre;
        $mail->isHTML(true);
        $mail->Body =
              '<h2>Nueva consulta desde el sitio</h2>'
            . '<p><strong>Nombre:</strong> ' . $nombre . '</p>'
            . ($empresa  !== '' ? '<p><strong>Empresa:</strong> ' . $empresa . '</p>' : '')
            . '<p><strong>Correo:</strong> ' . $email . '</p>'
            . ($telefono !== '' ? '<p><strong>Teléfono:</strong> ' . $telefono . '</p>' : '')
            . '<p><strong>Necesita:</strong> ' . $tipo . '</p>'
            . '<p><strong>Mensaje:</strong><br>' . nl2br($mensaje) . '</p>'
            . '<hr><p style="color:#888;font-size:12px">IP: ' . $ip
            . '<br>Navegador: ' . htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8')
            . '<br>Fecha: ' . $fecha . '</p>';

        $mail->send();
    } catch (\Throwable $e) {
        // El visitante no necesita enterarse: su mensaje ya quedó guardado.
        error_log('[contacto] Falló la notificación por correo: ' . $e->getMessage());
    }
}

responder($response);
