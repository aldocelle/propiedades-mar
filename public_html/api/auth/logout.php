<?php
require_once dirname(__DIR__) . '/_config.php';
cors_headers();

start_session();
$_SESSION = [];
session_destroy();

// Eliminar cookie de sesión
if (isset($_COOKIE[SESSION_NAME])) {
    setcookie(SESSION_NAME, '', time() - 3600, '/');
}

send(200, ['success' => true]);
