<?php

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$body = file_get_contents('php://input');
$logPath = __DIR__ . '/requests.log';

$entry = [
    'time' => date(DATE_ATOM),
    'method' => $method,
    'uri' => $uri,
    'headers' => getallheaders(),
    'body' => $body,
];

file_put_contents($logPath, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

http_response_code(200);
echo json_encode(['ok' => true, 'received' => true]);
