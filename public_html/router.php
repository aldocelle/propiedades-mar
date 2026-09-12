<?php
$baseUrl = 'https://propiedadesmar.cl';
$requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if (strpos($requestPath, '/api/') === 0) {
    $remoteUrl = $baseUrl . $requestPath . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . $_SERVER['QUERY_STRING'] : '');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $headers = [];
    foreach (getallheaders() as $name => $value) {
        $headers[] = "$name: $value";
    }

    $contextOptions = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];

    if (!in_array($method, ['GET', 'HEAD'], true)) {
        $body = file_get_contents('php://input');
        if ($body !== false) {
            $contextOptions['http']['content'] = $body;
        }
    }

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($remoteUrl, false, $context);

    if ($response === false) {
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No se pudo obtener la respuesta remota.']);
        exit;
    }

    $statusCode = 200;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $header, $m)) {
            $statusCode = (int) $m[1];
            continue;
        }
        if (stripos($header, 'Content-Type:') === 0) {
            header($header);
        }
    }
    http_response_code($statusCode);
    echo $response;
    exit;
}

$root = __DIR__;
$path = $requestPath === '/' ? '/index.html' : $requestPath;
$fullPath = $root . $path;

if (is_dir($fullPath)) {
    $fullPath = rtrim($fullPath, '/') . '/index.html';
}

if (!is_file($fullPath)) {
    $fullPath = $root . '/index.html';
}

if (is_file($fullPath)) {
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'xml' => 'application/xml; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
    ];
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    readfile($fullPath);
    exit;
}

http_response_code(404);
echo 'Not Found';
