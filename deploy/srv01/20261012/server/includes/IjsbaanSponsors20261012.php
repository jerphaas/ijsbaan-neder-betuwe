<?php

declare(strict_types=1);

/** Temporary transport for the requested sponsor update; no database or mail route. */
function ijsbaan12Profiles(): array
{
    // Salted commitments to exactly 16 approved private payloads; no contribution amounts in Git.
    return [
        'etos-roelofsen' => '9b46f73717a9e3d926e67c5019b74506bd9a3d38c9a8c6741c255006e12ba6cc',
        'willem-designvloeren' => '61ee283eef71b246bd6d44e74bf2f8b216a5dbe7f121bb15bcd0d92e63c5cdda',
        'hk-vastgoed' => '0b4cc4272db6888885c69131533ba6039e8ed3da678523a6c099c5271a1a25bf',
        'van-roekel-optiek' => '7323b9f79ead038f12bfc2ad3dc77b6a92922b4e179ca617ef88dfaccb431d75',
        'hdv-wonen' => 'f832e8b0e206c89017e54749e36bc2c1071bc2e2c6e2b7ee0f468933f3ef19be',
        'mvl-dodewaard' => '9a3e81ac9e8f3992c79eaea8adc10c11e56320464f33bcb1525d95594cd33395',
        'van-leeuwen-grondwerk' => 'e2c3da6a68378641883a81e9738d74bb9aef393703c6324830f9d45a510cfa44',
        'village-collection' => '88a20816fc3cde6135ceff731c36fd03544bd4f8854859362de9b6f1b249ba50',
        'plantenbakken-en-zo' => '9699e3275e5aaa516e5fa167ad616a2c358d63e3fa4c4304cfc9c7b503833eee',
        'joop-van-mourik' => 'dec4bf99762dcb68c8597d050245b6130e9b4a669a47a8ee8bc6383c128842de',
        'van-gessel-groep' => 'd510b97dcac02b17de0cdb1e6b3116a8a20accfb13434a6810925f9fc52d5357',
        'arends-den-heuvel' => 'd1ffd383c260f6578f26dd284218682809e5b49f3c209cd2b8fde09956df4fe8',
        'johan-willemsen' => 'a58f5cd80c7d6fd5b22fe3228f0e359459da795daf7f7422ec0a1b6a462a2062',
        'vakgarage-de-kruijf' => '2974556c017cd6653269a6cf100ec4eae367ebff27c70241deee5867b49e7654',
        'van-suilichem-autos' => '2e5f2828d6169cad686e9f8cece5305b77233fbf3ca7da222eb4155ac7d66a16',
        'veerhuis-opheusden' => 'cfa35e1cea37d9ccea201e24317df6a5f8305bf886a184b7dca24ef4b84d9148',
    ];
}

function ijsbaan12PublicPath(string $path): bool
{
    return in_array($path, ['/', '/site-version.json', '/api/partners.php', '/sitemap.xml', '/robots.txt', '/google4244cf326cd58185.html'], true)
        || preg_match('~^/api/partners\.php\?(?:check=[a-f0-9]{32}|logo=[a-z0-9-]+(?:&v=[a-f0-9]+)?)$~D', $path) === 1
        || (preg_match('~^/(?:assets/)?[a-zA-Z0-9_./-]+\.(?:css|js|webp|png|jpg|jpeg|svg|woff2)$~D', $path) === 1
            && !str_contains($path, '..') && !str_contains($path, '//'));
}


/** Validate an exact private manifest, including the original image bytes. */
function ijsbaan12ProfilePayload(string $json, string $expectedHash, string $logo): array
{
    if (strlen($json) > 12000 || !hash_equals($expectedHash, hash('sha256', $json))) {
        throw new InvalidArgumentException('Profile differs from the approved task manifest.');
    }
    $profile = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($profile) || ($profile['edition_id'] ?? '') !== 'opheusden-2026'
        || ($profile['visible'] ?? '') !== '1'
        || preg_match('/^[a-f0-9]{64}$/D', (string) ($profile['scope_nonce'] ?? '')) !== 1
    ) {
        throw new InvalidArgumentException('Profile outside the requested edition.');
    }
    $logoHash = (string) ($profile['logo_sha256'] ?? '');
    if (strlen($logo) > 200000 || ($logoHash === '' ? $logo !== ''
        : (!hash_equals($logoHash, hash('sha256', $logo)) || substr($logo, 0, 4) !== 'RIFF'
            || substr($logo, 8, 4) !== 'WEBP'))) {
        throw new InvalidArgumentException('Logo differs from the approved original.');
    }
    $fields = [];
    foreach (['name', 'website', 'description', 'edition_id', 'amount', 'tier', 'sort_order', 'visible', 'logo_dark'] as $field) {
        if (!isset($profile[$field]) || !is_string($profile[$field])) {
            throw new InvalidArgumentException('Incomplete sponsor profile.');
        }
        $fields[$field] = $profile[$field];
    }
    return $fields;
}

function ijsbaan12Request(array $input): array
{
    $op = $input['operation'] ?? '';
    $path = (string) ($input['path'] ?? '');
    $headers = ['Accept: application/json', 'Cache-Control: no-cache'];
    $body = null;
    $slug = null;
    if ($op === 'public') {
        if (!ijsbaan12PublicPath($path)) {
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
        $allowed = '~^/beheer/\?(?:partners=1(?:&(?:new=1|edit=[a-z0-9-]+))?|partner_logo=[a-z0-9-]+)$~D';
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
    } elseif ($op === 'save') {
        $slug = (string) ($input['slug'] ?? '');
        $expectedHash = ijsbaan12Profiles()[$slug] ?? null;
        $logo = base64_decode((string) ($input['logo'] ?? ''), true);
        if ($expectedHash === null || $logo === false) {
            throw new InvalidArgumentException('Sponsor outside the approved task manifest.');
        }
        $profile = ijsbaan12ProfilePayload((string) ($input['profile_json'] ?? ''), $expectedHash, $logo);
        $csrf = (string) ($input['csrf'] ?? '');
        if (preg_match('/^[a-f0-9]{48}$/D', $csrf) !== 1) {
            throw new InvalidArgumentException('Invalid CSRF.');
        }
        $path = '/beheer/';
        // Creation only. Existing profiles, applications and their mail are never updated.
        $fields = ['action' => 'profile-save', 'csrf' => $csrf, 'profile_id' => '',
            'revision' => '0', 'application' => ''] + $profile;
        $boundary = 'ijsbaan12-' . bin2hex(random_bytes(16));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        if ($logo !== '') {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"logo\"; filename=\"logo.webp\"\r\n";
            $body .= "Content-Type: image/webp\r\n\r\n" . $logo . "\r\n";
        }
        $body .= '--' . $boundary . "--\r\n";
        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
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

function ijsbaan12Execute(array $request): array
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
        CURLOPT_USERAGENT => 'IJsbaan-approved-sponsors-SRV01/20261012',
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
function ijsbaan12Connectivity(): array
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
