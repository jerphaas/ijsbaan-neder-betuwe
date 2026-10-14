<?php

declare(strict_types=1);

/** Temporary read-only transport for the requested B&P logo; no database or mail route. */
function ijsbaan14PublicPath(string $path): bool
{
    return in_array($path, ['/', '/site-version.json', '/api/partners.php', '/sitemap.xml', '/robots.txt', '/google4244cf326cd58185.html'], true)
        || preg_match('~^/api/partners\.php\?(?:check=[a-f0-9]{32}|logo=[a-z0-9-]+(?:&v=[a-f0-9]+)?)$~D', $path) === 1
        || (preg_match('~^/(?:assets/)?[a-zA-Z0-9_./-]+\.(?:css|js|webp|png|jpg|jpeg|svg|woff2)$~D', $path) === 1
            && !str_contains($path, '..') && !str_contains($path, '//'));
}


function ijsbaan14Request(array $input): array
{
    $op = $input['operation'] ?? '';
    $path = (string) ($input['path'] ?? '');
    $headers = ['Accept: application/json', 'Cache-Control: no-cache'];
    $body = null;
    $slug = null;
    if ($op === 'public') {
        if (!ijsbaan14PublicPath($path)) {
            throw new InvalidArgumentException('Public path outside task scope.');
        }
    } elseif ($op === 'maintenance') {
        $action = $input['action'] ?? '';
        if (!in_array($action, ['health', 'admin-link'], true)) {
            throw new InvalidArgumentException('Maintenance action outside task scope.');
        }
        $key = (string) ($input['maintenance_key'] ?? '');
        if (preg_match('/^[a-zA-Z0-9_-]{32,256}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Invalid maintenance key.');
        }
        $path = '/api/sponsor.php?action=' . $action;
        $headers[] = 'X-Maintenance-Key: ' . $key;
        $body = '';
    } elseif ($op === 'admin-read') {
        $allowed = '~^/beheer/\?(?:partners=1(?:&edit=excel-2026-11)?|partner_logo=excel-2026-11)$~D';
        if (preg_match($allowed, $path) !== 1) {
            throw new InvalidArgumentException('Admin path outside task scope.');
        }
    } elseif ($op === 'login') {
        $ticket = (string) ($input['ticket'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $ticket) !== 1) {
            throw new InvalidArgumentException('Invalid one-use ticket.');
        }
        $path = '/beheer/';
        $body = http_build_query(['action' => 'login', 'ticket' => $ticket]);
    } elseif ($op === 'logout') {
        $csrf = (string) ($input['csrf'] ?? '');
        if (preg_match('/^[a-f0-9]{48}$/D', $csrf) !== 1) {
            throw new InvalidArgumentException('Invalid CSRF.');
        }
        $path = '/beheer/';
        $body = http_build_query(['action' => 'logout', 'csrf' => $csrf]);
    } else {
        throw new InvalidArgumentException('Operation outside task scope.');
    }
    $cookie = (string) ($input['cookie'] ?? '');
    if (strlen($cookie) > 4096 || preg_match('/[\r\n]/', $cookie)) {
        throw new InvalidArgumentException('Invalid session cookie.');
    }
    if ($cookie !== '' && in_array($op, ['admin-read', 'login', 'save', 'logout'], true)) {
        $headers[] = 'Cookie: ' . $cookie;
    }
    if ($body !== null) {
        $headers[] = 'Origin: https://ijsbaannederbetuwe.nl';
        if ($op !== 'save') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
    }
    return ['url' => 'https://ijsbaannederbetuwe.nl' . $path, 'headers' => $headers, 'body' => $body, 'slug' => $slug];
}

function ijsbaan14Execute(array $request): array
{
    $handle = curl_init($request['url']);
    $responseHeaders = [];
    $responseBody = '';
    curl_setopt_array($handle, [
        CURLOPT_HTTPHEADER => $request['headers'], CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25, CURLOPT_ENCODING => '',
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        // Same verified hosting address and TLS hostname; no OS/DNS setting changes.
        CURLOPT_RESOLVE => ['ijsbaannederbetuwe.nl:443:185.182.56.187'],
        CURLOPT_USERAGENT => 'IJsbaan-approved-sponsors-SRV01/20261014',
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                if (in_array(strtolower($key), ['content-type', 'set-cookie', 'location'], true)) {
                    $responseHeaders[strtolower($key)][] = trim($value);
                }
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$responseBody): int {
            if (strlen($responseBody) + strlen($chunk) > 4000000) {
                return 0;
            }
            $responseBody .= $chunk;
            return strlen($chunk);
        },
    ]);
    if ($request['body'] !== null) {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $request['body']);
    }
    $ok = curl_exec($handle);
    $result = [
        'ok' => $ok !== false, 'status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
        'connect_error' => curl_errno($handle), 'headers' => $responseHeaders,
        'connection' => [
            'error' => curl_error($handle),
            'peer_ip' => curl_getinfo($handle, CURLINFO_PRIMARY_IP),
            'dns_seconds' => curl_getinfo($handle, CURLINFO_NAMELOOKUP_TIME),
            'tcp_seconds' => curl_getinfo($handle, CURLINFO_CONNECT_TIME),
            'tls_seconds' => curl_getinfo($handle, CURLINFO_APPCONNECT_TIME),
            'total_seconds' => curl_getinfo($handle, CURLINFO_TOTAL_TIME),
        ],
        'body' => base64_encode($responseBody),
    ];
    curl_close($handle);
    return $result;
}

/** Fixed, read-only TCP checks; no credentials, payload or user-supplied destination. */
function ijsbaan14Connectivity(): array
{
    $targets = [
        'https_ipv4' => 'tcp://185.182.56.187:443',
        'http_ipv4' => 'tcp://185.182.56.187:80',
        'directadmin_ipv4' => 'tcp://185.182.56.187:2222',
        'https_ipv6' => 'tcp://[2a0b:7280:200:0:4cf:daff:fe00:da2]:443',
    ];
    $results = [];
    foreach ($targets as $name => $target) {
        $started = microtime(true);
        $socket = @stream_socket_client($target, $errorNumber, $error, 3, STREAM_CLIENT_CONNECT);
        $results[$name] = ['connected' => $socket !== false, 'errno' => $errorNumber,
            'error' => $error, 'seconds' => round(microtime(true) - $started, 3)];
        if ($socket !== false) {
            fclose($socket);
        }
    }
    return ['ok' => true, 'connectivity' => $results];
}
