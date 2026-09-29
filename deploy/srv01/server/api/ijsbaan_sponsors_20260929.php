<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/fictief/beursopheusden_support.php';
require_once dirname(__DIR__) . '/includes/IjsbaanSponsors20260929.php';

const IJSBAAN_29_CLOSED = true;
const IJSBAAN_29_CONTEXT = 'ijsbaan-sponsors-srv01-20260929-v1';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$state = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ijsbaan-sponsors-20260929';
if (IJSBAAN_29_CLOSED || time() >= strtotime('2026-09-29T18:00:00Z') || is_file($state . '/closed')) {
    bbo_store_api_json(['ok' => false, 'error' => 'Temporary sponsor task closed.'], 410);
}
$client = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$private = in_array($client, ['127.0.0.1', '::1'], true)
    || (filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
        && (str_starts_with($client, '10.') || str_starts_with($client, '192.168.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $client) === 1));
if (
    !$private || !in_array($host, ['172.16.85.1', '172.16.85.1:80', '127.0.0.1', 'localhost'], true)
    || isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
) {
    bbo_store_api_json(['ok' => false, 'error' => 'Direct private SRV01 route only.'], 403);
}
if (PHP_OS_FAMILY !== 'Windows' || strcasecmp((string) getenv('COMPUTERNAME'), 'SRV01') !== 0) {
    bbo_store_api_json(['ok' => false, 'error' => 'Wrong server.'], 409);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_GET !== [] || $_POST !== []) {
    bbo_store_api_json(['ok' => false, 'error' => 'Encrypted JSON POST required.'], 405);
}

// Authenticated encryption reuses an existing config/env token. Neither that token
// nor the site's credentials/session travel as plaintext over the internal HTTP hop.
// Do not use bbo_store_api_tokens(): its legacy fallback reads other PHP source files.
$token = bbo_store_config_value([
    'BEURSOPHEUSDEN_API_TOKEN', 'DASHBOARD_HISTORY_API_TOKEN', 'TEST_RUNNER_API_TOKEN',
    'SYNC_API_TOKEN', 'DASHBOARD_SYNC_API_TOKEN', 'DEPLOY_API_TOKEN', 'TEST_RUNNER_INLINE_TOKEN',
], '');
if (!is_string($token) || strlen($token) < 32) {
    bbo_store_api_json(['ok' => false, 'error' => 'Existing config/env token unavailable.'], 503);
}
$key = hash('sha256', IJSBAAN_29_CONTEXT . "\0" . $token, true);
$envelope = json_decode((string) file_get_contents('php://input', false, null, 0, 500001), true);
$nonce = base64_decode((string) ($envelope['nonce'] ?? ''), true);
$cipher = base64_decode((string) ($envelope['ciphertext'] ?? ''), true);
if ($nonce === false || strlen($nonce) !== 12 || $cipher === false || strlen($cipher) < 16) {
    bbo_store_api_json(['ok' => false, 'error' => 'Authentication required.'], 401);
}
$plain = openssl_decrypt(
    substr($cipher, 0, -16),
    'aes-256-gcm',
    $key,
    OPENSSL_RAW_DATA,
    $nonce,
    substr($cipher, -16),
    IJSBAAN_29_CONTEXT . ':request'
);
$input = $plain === false ? null : json_decode($plain, true);
if (!is_array($input) || abs(time() - (int) ($input['time'] ?? 0)) > 120) {
    bbo_store_api_json(['ok' => false, 'error' => 'Authentication or request time invalid.'], 401);
}
if (!is_dir($state) && !mkdir($state, 0700, true) && !is_dir($state)) {
    bbo_store_api_json(['ok' => false, 'error' => 'Private replay protection unavailable.'], 503);
}
$nonceFile = @fopen($state . '/nonce-' . bin2hex($nonce), 'x');
if ($nonceFile === false) {
    bbo_store_api_json(['ok' => false, 'error' => 'Request already used.'], 409);
}
fclose($nonceFile);
$lock = fopen($state . '/task.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    bbo_store_api_json(['ok' => false, 'error' => 'Task busy.'], 409);
}
$status = 200;
try {
    if (($input['operation'] ?? '') === 'close') {
        file_put_contents($state . '/closed', gmdate('c'), LOCK_EX);
        $result = ['ok' => true, 'closed' => true];
    } else {
        $request = ijsbaan29Request($input);
        if ($request['slug'] !== null) {
            $attempt = @fopen($state . '/attempt-' . $request['slug'], 'x');
            if ($attempt === false) {
                throw new RuntimeException('Save already attempted; read persisted profile before any further action.');
            }
            fwrite($attempt, gmdate('c'));
            fclose($attempt);
        }
        $result = ijsbaan29Execute($request);
    }
    $result['endpoint_sha256'] = hash_file('sha256', __FILE__);
    $result['client_sha256'] = hash_file('sha256', dirname(__DIR__) . '/includes/IjsbaanSponsors20260929.php');
} catch (InvalidArgumentException $error) {
    $result = ['ok' => false, 'error' => $error->getMessage()];
    $status = 400;
} catch (Throwable $error) {
    $result = ['ok' => false, 'error' => 'Task stopped: ' . $error->getMessage()];
    $status = 409;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
$responseNonce = random_bytes(12);
$responseTag = '';
$responseCipher = openssl_encrypt(
    json_encode($result, JSON_THROW_ON_ERROR),
    'aes-256-gcm',
    $key,
    OPENSSL_RAW_DATA,
    $responseNonce,
    $responseTag,
    IJSBAAN_29_CONTEXT . ':response:' . bin2hex($nonce)
);
bbo_store_api_json([
    'nonce' => base64_encode($responseNonce), 'ciphertext' => base64_encode($responseCipher . $responseTag),
], $status);
