<?php
// ============================================================
// _config.php — Núcleo de la API PHP para Propiedades Mar
// SQLite en /home/a0110381/ · sesiones PHP reemplazan Supabase Auth
// ============================================================

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');
define('DB_PATH', getenv('DB_PATH') ?: '/home/a0110381/propiedadesmar.db');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('SESSION_NAME',   'pm_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7); // 7 días

// ── Conexión a base de datos (SQLite por defecto; MySQL si se configuran variables de entorno) ──────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (strtolower(DB_DRIVER) === 'mysql' && DB_NAME !== '' && DB_USER !== '') {
                $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
                ]);
            } else {
                $pdo = new PDO('sqlite:' . DB_PATH);
                $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec('PRAGMA journal_mode=WAL');
                $pdo->exec('PRAGMA foreign_keys=ON');
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error de conexión a la base de datos.']);
            exit;
        }
    }
    return $pdo;
}

// ── Headers CORS y JSON ──────────────────────────────────────
function cors_headers(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // Producción (propiedadesmar.cl) + cualquier localhost:* (dev/atención)
    if (preg_match('#^https://(www\.)?propiedadesmar\.cl$#', $origin)
        || preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Sesión segura ────────────────────────────────────────────
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ── Autenticación ────────────────────────────────────────────
function get_session_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, email, role, full_name FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_auth(): array {
    $user = get_session_user();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'No autenticado.']); exit; }
    return $user;
}

function require_admin(): array {
    $user = require_auth();
    if ($user['role'] !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Acceso denegado.']); exit; }
    return $user;
}

function require_owner(): array {
    $user = require_auth();
    if (!in_array($user['role'], ['admin', 'owner'], true)) {
        http_response_code(403); echo json_encode(['error' => 'Acceso denegado.']); exit;
    }
    return $user;
}

// ── Helpers ──────────────────────────────────────────────────
function uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

function json_body(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function method(): string { return $_SERVER['REQUEST_METHOD']; }

function send(int $code, mixed $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function now(): string { return date('Y-m-d H:i:s'); }

// Convierte fila de DB: amenities string JSON → array PHP, booleans
function parse_property(array $row): array {
    if (isset($row['amenities']) && is_string($row['amenities'])) {
        $row['amenities'] = json_decode($row['amenities'], true) ?? [];
    }
    foreach (['is_featured', 'published'] as $col) {
        if (array_key_exists($col, $row)) $row[$col] = (bool) $row[$col];
    }
    return $row;
}

// Adjunta property_images a cada propiedad como array anidado
function attach_images(array $properties): array {
    if (empty($properties)) return [];
    $ids = array_column($properties, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, property_id, url, alt_text, sort_order
         FROM property_images WHERE property_id IN ($placeholders)
         ORDER BY sort_order ASC"
    );
    $stmt->execute($ids);
    $byProp = [];
    foreach ($stmt->fetchAll() as $img) {
        $byProp[$img['property_id']][] = $img;
    }
    foreach ($properties as &$prop) {
        $prop['property_images'] = $byProp[$prop['id']] ?? [];
    }
    return $properties;
}
