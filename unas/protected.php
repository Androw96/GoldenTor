<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!current_user()) {
    if (isset($_GET['page'])) {
        header('Location: /fiok.html?next=' . rawurlencode('/kalkulatorok.html'), true, 303);
        exit;
    }
    http_response_code(401);
    exit;
}

$asset = (string) ($_GET['asset'] ?? '');
if ($asset === 'calculator') {
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(dirname(__DIR__) . '/calculator.js');
    exit;
}
if ($asset === 'insurance') {
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(dirname(__DIR__) . '/insurance-market.js');
    exit;
}
if (($_GET['page'] ?? '') === 'kalkulatorok') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__) . '/kalkulatorok.html');
    exit;
}

http_response_code(404);
