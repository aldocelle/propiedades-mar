<?php
require_once dirname(__DIR__) . '/_config.php';
cors_headers();

$user = get_session_user();

if (!$user) {
    send(401, ['authenticated' => false]);
}

send(200, [
    'authenticated' => true,
    'user'          => [
        'id'        => $user['id'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'full_name' => $user['full_name'],
    ],
]);
