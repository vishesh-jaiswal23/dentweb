<?php
require_once __DIR__ . '/users/common/config.php';
require_once __DIR__ . '/users/common/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        portal_verify_csrf($_POST['csrf_token'] ?? '');
    } catch (Throwable $th) {
        http_response_code(400);
        exit('Bad request');
    }
}

portal_logout();
portal_redirect('index.html');
