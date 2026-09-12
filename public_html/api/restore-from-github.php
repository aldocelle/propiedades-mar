<?php
header('Content-Type: application/json; charset=utf-8');
$token = $_GET['token'] ?? '';
if ($token !== 'restore2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

$dbPath = '/home/a0110381/public_html/db/propiedadesmar.db';
$results = [];

function uuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
}

function ensure_column(PDO $pdo, string $table, string $column, string $ddl, array &$results): void {
    $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        if (strcasecmp($col['name'], $column) === 0) {
            return;
        }
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $ddl");
    $results[] = "Columna $table.$column agregada";
}

try {
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir) && !mkdir($dbDir, 0755, true) && !is_dir($dbDir)) {
        throw new RuntimeException('No se pudo crear el directorio de la base de datos: ' . $dbDir);
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys=ON;');
    @chmod($dbPath, 0666);
    $results[] = 'Conexión a DB exitosa: ' . $dbPath;

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'owner' CHECK (role IN ('admin','owner')),
        full_name TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $results[] = 'Tabla users lista';

    $pdo->exec("CREATE TABLE IF NOT EXISTS properties (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        description TEXT,
        address TEXT,
        city TEXT,
        price REAL NOT NULL,
        price_type TEXT NOT NULL DEFAULT 'CLP' CHECK (price_type IN ('CLP','UF')),
        bedrooms INTEGER,
        bathrooms INTEGER,
        area_sq_m INTEGER,
        superficie_util INTEGER,
        superficie_terraza INTEGER,
        estacionamientos INTEGER NOT NULL DEFAULT 0,
        bodegas_count INTEGER NOT NULL DEFAULT 0,
        property_type TEXT,
        operation_type TEXT NOT NULL DEFAULT 'Venta',
        status TEXT NOT NULL DEFAULT 'available',
        publish_state TEXT NOT NULL DEFAULT 'PUBLICADA' CHECK (publish_state IN ('PUBLICADA','NO_PUBLICADA','DESACTIVADA','BORRADA')),
        is_featured INTEGER NOT NULL DEFAULT 0,
        published INTEGER NOT NULL DEFAULT 1,
        amenities TEXT NOT NULL DEFAULT '[]',
        orientacion TEXT,
        amoblado TEXT NOT NULL DEFAULT 'Sin amoblar',
        mascotas TEXT NOT NULL DEFAULT 'Consultar',
        numero_piso INTEGER,
        departamentos_por_piso INTEGER,
        cantidad_pisos INTEGER,
        antiguedad INTEGER,
        tipo_departamento TEXT,
        numero_departamento TEXT,
        max_habitantes INTEGER,
        gastos_comunes REAL,
        owner_id TEXT REFERENCES users(id) ON DELETE SET NULL,
        created_by_id TEXT REFERENCES users(id) ON DELETE SET NULL,
        updated_by_id TEXT REFERENCES users(id) ON DELETE SET NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $results[] = 'Tabla properties lista';

    ensure_column($pdo, 'properties', 'published', "published INTEGER NOT NULL DEFAULT 1", $results);
    ensure_column($pdo, 'properties', 'publish_state', "publish_state TEXT NOT NULL DEFAULT 'PUBLICADA'", $results);
    ensure_column($pdo, 'properties', 'departamentos_por_piso', 'departamentos_por_piso INTEGER', $results);
    ensure_column($pdo, 'properties', 'max_habitantes', 'max_habitantes INTEGER', $results);

    $pdo->exec("CREATE TABLE IF NOT EXISTS property_images (
        id TEXT PRIMARY KEY,
        property_id TEXT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
        url TEXT NOT NULL,
        alt_text TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $results[] = 'Tabla property_images lista';

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        site_name TEXT NOT NULL DEFAULT 'Propiedades Mar',
        site_description TEXT,
        site_url TEXT,
        phone TEXT,
        email TEXT,
        whatsapp TEXT,
        main_region TEXT NOT NULL DEFAULT 'Valparaíso',
        address TEXT,
        show_featured_first INTEGER NOT NULL DEFAULT 1,
        enable_comments INTEGER NOT NULL DEFAULT 0,
        properties_per_page INTEGER NOT NULL DEFAULT 12,
        meta_title TEXT,
        meta_description TEXT,
        ga_id TEXT,
        enable_debug INTEGER NOT NULL DEFAULT 0,
        maintenance_mode INTEGER NOT NULL DEFAULT 0,
        app_version TEXT NOT NULL DEFAULT '2.0.0',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $results[] = 'Tabla settings lista';

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_published ON properties(published)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_publish_state ON properties(publish_state)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_slug ON properties(slug)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_properties_city ON properties(city)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_images_property ON property_images(property_id)');
    $results[] = 'Índices creados';

    $properties = [
        ['Departamento frente al mar', 'Moderno departamento con vista al mar y terraza privada.', 'Av. del Mar 123', 'Marbella', 350000, 2, 2, 95, 'Departamento', 'depto-frente-al-mar', 1],
        ['Casa en venta con jardín', 'Casa familiar con jardín amplio y piscina privada.', 'Calle Las Olas 78', 'Punta del Este', 780000, 4, 3, 210, 'Casa', 'casa-jardin-punta-oyeste', 0],
        ['Penthouse con terraza panorámica', 'Penthouse luminoso con terraza privada, quincho y vistas al borde costero.', 'Costanera Norte 410', 'Viña del Mar', 620000, 3, 3, 145, 'Departamento', 'penthouse-terraza-panoramica', 1],
        ['Casa mediterránea cerca de la playa', 'Casa de estilo mediterráneo con piscina, jardín consolidado y espacios amplios.', 'Camino del Sol 55', 'Zapallar', 890000, 5, 4, 280, 'Casa', 'casa-mediterranea-zapallar', 1],
        ['Departamento moderno en primera línea', 'Departamento renovado con balcón, cocina integrada y acceso directo a servicios.', 'Av. Borgoño 15200', 'Concón', 410000, 2, 2, 88, 'Departamento', 'depto-moderno-concon', 1],
        ['Terreno urbano con vista al mar', 'Terreno urbanizado en barrio residencial, ideal para proyecto de vivienda.', 'Lote 12, Mirador del Pacífico', 'Pichilemu', 185000, null, null, 950, 'Terreno', 'terreno-vista-mar-pichilemu', 0],
        ['Cabaña equipada frente al bosque', 'Cabaña acogedora con terraza, estufa a leña y acceso cercano a playa.', 'Ruta Costera Km 8', 'Maitencillo', 265000, 3, 2, 120, 'Casa', 'cabana-frente-bosque', 0],
        ['Loft de diseño en barrio costero', 'Loft compacto con doble altura, terminaciones modernas y excelente conectividad.', 'Pasaje Las Gaviotas 24', 'La Serena', 195000, 1, 1, 62, 'Departamento', 'loft-diseno-la-serena', 0],
        ['Casa familiar con piscina y quincho', 'Propiedad de dos pisos con piscina, quincho techado y suite principal.', 'Los Pinos 890', 'Reñaca', 540000, 4, 3, 230, 'Casa', 'casa-familiar-renca', 1],
        ['Parcela con casa y vista panorámica', 'Parcela amplia con casa principal, terraza envolvente y vistas al valle.', 'Camino Interior 7', 'Pucón', 470000, 4, 3, 260, 'Parcela', 'parcela-casa-pucón', 0],
    ];

    $findStmt = $pdo->prepare('SELECT id FROM properties WHERE slug = ?');
    $insertStmt = $pdo->prepare(
        "INSERT INTO properties
            (id, title, slug, description, address, city, price, bedrooms, bathrooms, area_sq_m, property_type, is_featured, published, publish_state)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,'PUBLICADA')"
    );
    $updateStmt = $pdo->prepare(
        "UPDATE properties SET
            title = ?, description = ?, address = ?, city = ?, price = ?,
            bedrooms = ?, bathrooms = ?, area_sq_m = ?, property_type = ?,
            is_featured = ?, published = 1, publish_state = 'PUBLICADA', updated_at = datetime('now')
         WHERE id = ?"
    );

    $uuids = [];
    foreach ($properties as $prop) {
        [$title, $description, $address, $city, $price, $bedrooms, $bathrooms, $area, $type, $slug, $featured] = $prop;
        $findStmt->execute([$slug]);
        $existing = $findStmt->fetch();
        if ($existing) {
            $id = $existing['id'];
            $updateStmt->execute([$title, $description, $address, $city, $price, $bedrooms, $bathrooms, $area, $type, $featured, $id]);
        } else {
            $id = uuid();
            $insertStmt->execute([$id, $title, $slug, $description, $address, $city, $price, $bedrooms, $bathrooms, $area, $type, $featured]);
        }
        $uuids[$slug] = $id;
    }
    $results[] = '10 propiedades insertadas o actualizadas';

    $images = [
        ['depto-frente-al-mar', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80', 'Vista frontal del departamento', 0],
        ['depto-frente-al-mar', 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80', 'Terraza con vista al océano', 1],
        ['casa-jardin-punta-oyeste', 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1200&q=80', 'Fachada de la casa con jardín', 0],
        ['penthouse-terraza-panoramica', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80', 'Terraza moderna panorámica', 0],
        ['casa-mediterranea-zapallar', 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1200&q=80', 'Fachada casa mediterránea', 0],
        ['depto-moderno-concon', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80', 'Dormitorio moderno', 0],
        ['terreno-vista-mar-pichilemu', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80', 'Vista de terreno costero', 0],
        ['cabana-frente-bosque', 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=1200&q=80', 'Cabaña en el bosque', 0],
        ['loft-diseno-la-serena', 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80', 'Loft moderno', 0],
        ['casa-familiar-renca', 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=80', 'Casa familiar con piscina', 0],
        ['parcela-casa-pucón', 'https://images.unsplash.com/photo-1605146769289-440113cc3d00?auto=format&fit=crop&w=1200&q=80', 'Casa en parcela', 0],
    ];

    $delImg = $pdo->prepare('DELETE FROM property_images WHERE property_id = ?');
    $imgStmt = $pdo->prepare('INSERT INTO property_images (id, property_id, url, alt_text, sort_order) VALUES (?,?,?,?,?)');
    $cleared = [];
    foreach ($images as $img) {
        if (!isset($uuids[$img[0]])) {
            continue;
        }
        $propertyId = $uuids[$img[0]];
        if (!isset($cleared[$propertyId])) {
            $delImg->execute([$propertyId]);
            $cleared[$propertyId] = true;
        }
        $imgStmt->execute([uuid(), $propertyId, $img[1], $img[2], $img[3]]);
    }
    $results[] = '11 imágenes insertadas';

    $adminEmail = 'admin@propiedadesmar.cl';
    $adminPass = 'Admin2026!Propiedades';
    $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$adminEmail]);
    $admin = $stmt->fetch();
    if ($admin) {
        $pdo->prepare('UPDATE users SET password_hash = ?, role = ?, full_name = ?, updated_at = datetime(\'now\') WHERE email = ?')
            ->execute([$hash, 'admin', 'Administrador', $adminEmail]);
        $results[] = 'Usuario admin actualizado';
    } else {
        $pdo->prepare('INSERT INTO users (id, email, password_hash, role, full_name) VALUES (?,?,?,?,?)')
            ->execute([uuid(), $adminEmail, $hash, 'admin', 'Administrador']);
        $results[] = 'Usuario admin creado';
    }

    $pdo->exec("INSERT OR REPLACE INTO settings
        (id, site_name, site_url, email, main_region)
        VALUES (1, 'Propiedades Mar', 'https://propiedadesmar.cl', 'admin@propiedadesmar.cl', 'Valparaíso')");
    $results[] = 'Settings iniciales creadas';

    $counts = [
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'properties' => (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn(),
        'published_properties' => (int) $pdo->query("SELECT COUNT(*) FROM properties WHERE published = 1 AND (publish_state = 'PUBLICADA' OR publish_state IS NULL OR publish_state = '')")->fetchColumn(),
        'images' => (int) $pdo->query('SELECT COUNT(*) FROM property_images')->fetchColumn(),
    ];

    echo json_encode([
        'status' => 'success',
        'message' => 'Base de datos restaurada correctamente',
        'results' => $results,
        'database_path' => $dbPath,
        'counts' => $counts,
        'admin_credentials' => [
            'email' => $adminEmail,
            'password' => $adminPass,
        ],
        'endpoints' => [
            'properties' => 'https://propiedadesmar.cl/api/properties',
            'login' => 'https://propiedadesmar.cl/api/auth/login',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
