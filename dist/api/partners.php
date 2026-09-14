<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: https://jerphaas.github.io');
try {
    require dirname(__DIR__, 2) . '/sponsor-private/app/bootstrap.php';
    require_once dirname(__DIR__, 2) . '/sponsor-private/app/partners.php';
    partner_api();
} catch (Throwable $e) {
    error_log('Sponsor display API unavailable: ' . get_class($e));
    http_response_code(503);
    echo '{"ok":false,"message":"Sponsoroverzicht tijdelijk niet beschikbaar."}';
}
