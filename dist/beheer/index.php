<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
try {
    require dirname(__DIR__, 2) . '/sponsor-private/app/bootstrap.php';
    require_once dirname(__DIR__, 2) . '/sponsor-private/app/admin.php';
    sponsor_admin_route();
} catch (Throwable $error) {
    error_log('Sponsor overview unavailable: ' . get_class($error));
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Beheer tijdelijk niet beschikbaar</title><p>Het sponsoroverzicht is tijdelijk niet beschikbaar. Probeer het later opnieuw.</p></html>';
}
