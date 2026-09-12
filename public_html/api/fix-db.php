<?php
// Script de restauración de la base de datos de PropiedadesMar
// Acceder vía: https://propiedadesmar.cl/api/fix-db.php?token=fix2026

$token = $_GET['token'] ?? '';
if ($token !== 'fix2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$dbPath = '/home/a0110381/propiedadesmar.db';
$results = [];

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys=ON;');
    $results[] = 'Conexión a DB exitosa: ' . $dbPath;

    // Crear esquema de tablas usando array de queries
    $queries = [];
    
    $queries[] = "CREATE TABLE IF NOT EXISTS users (id TEXT PRIMARY KEY, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'owner' CHECK (role IN ('admin','owner')), full_name TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))";
    
    $queries[] = "CREATE TABLE IF NOT EXISTS properties (id TEXT PRIMARY KEY, title TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, description TEXT, address TEXT, city TEXT, price REAL NOT NULL, price_type TEXT NOT NULL DEFAULT 'CLP' CHECK (price_type IN ('CLP','UF')), bedrooms INTEGER, bathrooms INTEGER, area_sq_m INTEGER, superficie_util INTEGER, superficie_terraza INTEGER, estacionamientos INTEGER NOT NULL DEFAULT 0, bodegas_count INTEGER NOT NULL DEFAULT 0, property_type TEXT, operation_type TEXT NOT NULL DEFAULT 'Venta', status TEXT NOT NULL DEFAULT 'available', publish_state TEXT NOT NULL DEFAULT 'PUBLICADA' CHECK (publish_state IN ('PUBLICADA','NO_PUBLICADA','DESACTIVADA','BORRADA')), is_featured INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, amenities TEXT NOT NULL DEFAULT '[]', orientacion TEXT, amoblado TEXT NOT NULL DEFAULT 'Sin amoblar', mascotas TEXT NOT NULL DEFAULT 'Consultar', numero_piso INTEGER, departamentos_por_piso INTEGER, cantidad_pisos INTEGER, antiguedad INTEGER, tipo_departamento TEXT, numero_departamento TEXT, max_habitantes INTEGER, gastos_comunes REAL, owner_id TEXT REFERENCES users(id) ON DELETE SET NULL, created_by_id TEXT REFERENCES users(id) ON DELETE SET NULL, updated_by_id TEXT REFERENCES users(id) ON DELETE SET NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))";
    
    $queries[] = "CREATE TABLE IF NOT EXISTS property_images (id TEXT PRIMARY KEY, property_id TEXT NOT NULL REFERENCES properties(id) ON DELETE CASCADE, url TEXT NOT NULL, alt_text TEXT, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')))";
    
    $queries[] = "CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK (id = 1), site_name TEXT NOT NULL DEFAULT 'Propiedades Mar', site_description TEXT, site_url TEXT, phone TEXT, email TEXT, whatsapp TEXT, main_region TEXT NOT NULL DEFAULT 'Valparaíso', address TEXT, show_featured_first INTEGER NOT NULL DEFAULT 1, enable_comments INTEGER NOT NULL DEFAULT 0, properties_per_page INTEGER NOT NULL DEFAULT 12, meta_title TEXT, meta_description TEXT, ga_id TEXT, enable_debug INTEGER NOT NULL DEFAULT 0, maintenance_mode INTEGER NOT NULL DEFAULT 0, app_version TEXT NOT NULL DEFAULT '2.0.0', created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))";
    
    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
    $results[] = 'Tablas creadas correctamente';


    // Crear índices
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_slug ON properties(slug)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_city ON properties(city)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_published ON properties(published)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_images_property ON property_images(property_id)');
    $results[] = 'Índices creados correctamente';

    // Crear usuario admin
    $adminEmail = 'admin@propiedadesmar.cl';
    $adminPass = 'Admin2026!Propiedades';
    $adminName = 'Administrador Propiedades Mar';

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$adminEmail]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $id = bin2hex(random_bytes(16));
        $id = substr($id, 0, 8) . '-' . substr($id, 8, 4) . '-' . substr($id, 12, 4) . '-' . substr($id, 16, 4) . '-' . substr($id, 20, 12);
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]);
        $pdo->prepare('INSERT INTO users (id, email, password_hash, role, full_name) VALUES (?,?,?,?,?)')
            ->execute([$id, $adminEmail, $hash, 'admin', $adminName]);
        $results[] = 'Usuario admin creado: ' . $adminEmail;
    } else {
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]);
        $pdo->prepare('UPDATE users SET password_hash = ?, role = ? WHERE email = ?')
            ->execute([$hash, 'admin', $adminEmail]);
        $results[] = 'Password de admin actualizado: ' . $adminEmail;
    }

    // Verificar/crear settings
    $stmt = $pdo->query('SELECT id FROM settings WHERE id = 1');
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO settings (id, site_name, site_url, email, main_region) VALUES (1, 'Propiedades Mar', 'https://propiedadesmar.cl', 'admin@propiedadesmar.cl', 'Valparaíso')");
        $results[] = 'Settings iniciales creados';
    }

    // Verificar conteos
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $propCount = $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
    $pubCount = $pdo->query("SELECT COUNT(*) FROM properties WHERE published = 1 AND (publish_state = 'PUBLICADA' OR publish_state IS NULL)")->fetchColumn();
    $imgCount = $pdo->query('SELECT COUNT(*) FROM property_images')->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'message' => 'Base de datos restaurada correctamente',
        'results' => $results,
        'database' => [
            'path' => $dbPath,
            'size_bytes' => filesize($dbPath),
            'tables' => $tables,
            'user_count' => (int)$userCount,
            'property_count' => (int)$propCount,
            'published_properties' => (int)$pubCount,
            'image_count' => (int)$imgCount
        ],
        'credentials' => [
            'email' => $adminEmail,
            'password' => $adminPass
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
