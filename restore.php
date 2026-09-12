<?php
// Script de restauración de la base de datos de PropiedadesMar
// Este script restaura la DB funcional en el servidor

$dbPath = '/home/a0110381/propiedadesmar.db';

// Verificar si la DB existe y tiene contenido
if (file_exists($dbPath) && filesize($dbPath) > 1000) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar tablas
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    // Contar propiedades publicadas
    $count = $pdo->query("SELECT COUNT(*) FROM properties WHERE published = 1")->fetchColumn();
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $images = $pdo->query("SELECT COUNT(*) FROM property_images")->fetchColumn();
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Base de datos verificada correctamente',
        'db_path' => $dbPath,
        'db_size' => filesize($dbPath),
        'tables' => $tables,
        'properties_published' => (int)$count,
        'users' => (int)$users,
        'images' => (int)$images
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'La base de datos no existe o está vacía',
        'db_path' => $dbPath,
        'exists' => file_exists($dbPath),
        'size' => file_exists($dbPath) ? filesize($dbPath) : 0
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
