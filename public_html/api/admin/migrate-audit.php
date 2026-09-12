<?php
// Migración ya ejecutada — endpoint deshabilitado (2026-07-08).
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Este endpoint fue deshabilitado.']);
