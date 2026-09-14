<?php
// Application code, credentials and uploaded files are outside the public web root.
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
try {
    $privateRoot = dirname(__DIR__, 2) . '/sponsor-private';
    require $privateRoot . '/app/bootstrap.php';
    sponsor_route();
} catch (Throwable $error) {
    error_log('Sponsor endpoint unavailable: ' . get_class($error));
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Het formulier is tijdelijk niet beschikbaar. Probeer het later opnieuw of neem contact op met Ton.']);
}
