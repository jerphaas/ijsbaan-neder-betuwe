<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$template = file_get_contents(__DIR__ . '/index.html');
try {
    require dirname(__DIR__) . '/sponsor-private/app/bootstrap.php';
    require_once dirname(__DIR__) . '/sponsor-private/app/partners.php';
    $template = partner_render_home($template);
} catch (Throwable $e) {
    error_log('Sponsor display unavailable: ' . get_class($e));
}
echo $template;
