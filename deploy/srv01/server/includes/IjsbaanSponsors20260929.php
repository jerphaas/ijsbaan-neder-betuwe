<?php

declare(strict_types=1);

/** Temporary transport for five approved website profiles; no database or mail route. */
function ijsbaan29Profiles(): array
{
    return [
        'de-bruin-betonwerken' => [
            'De Bruin Betonwerken', 'https://debruinbetonwerken.nl/', '', '500',
            'cc5d041a2613dc64a968215f02049a2a70aef30e8af36cdbb698f002065358e4',
        ],
        'arends-natuurlijk' => [
            'Arends Natuurlijk', 'https://arendsnatuurlijk.com/', '', '510',
            '948282ad77a0689c928d5f869d88a12ba40319c9015d3edced27fdc9df25c522',
        ],
        'van-dijk-metaaldesign' => [
            'Van Dijk Metaaldesign', 'https://www.vandijkmetaaldesign.nl/', 'Folkert van Dijk', '520',
            'e4e1fcb471a3a0e38a2fada5cca460aeda819cc2f4a0f4143a9653b94d98f888',
        ],
        'van-dam-wonen-en-slapen' => [
            'Van Dam Wonen en Slapen', 'https://www.vandamwonen.nl/', '', '530',
            '1672d56ba657a1e51dfa1db5b544826c119d7bd3d94e8a7200936738a714c26b',
        ],
        'heeren-van-opheusden' => [
            'Heeren van Opheusden', 'https://heerenvanopheusden.nl/', '', '540',
            'f9d173f4476b7a42a88f15f356c57f6a17b749ca82e631be4a8fa79807bc07e4',
        ],
    ];
}

function ijsbaan29PublicPath(string $path): bool
{
    return in_array($path, ['/', '/site-version.json', '/api/partners.php'], true)
        || preg_match('~^/api/partners\.php\?(?:check=[a-f0-9]{32}|logo=[a-z0-9-]+(?:&v=[a-f0-9]+)?)$~D', $path) === 1
        || (preg_match('~^/(?:assets/)?[a-zA-Z0-9_./-]+\.(?:css|js|webp|png|jpg|jpeg|svg|woff2)$~D', $path) === 1
            && !str_contains($path, '..') && !str_contains($path, '//'));
}

function ijsbaan29Request(array $input): array
{
    $op = $input['operation'] ?? '';
    $path = (string) ($input['path'] ?? '');
    $headers = ['Accept: application/json', 'Cache-Control: no-cache'];
    $body = null;
    $slug = null;
    if ($op === 'public') {
        if (!ijsbaan29PublicPath($path)) {
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
        $profile = ijsbaan29Profiles()[$slug] ?? null;
        $logo = base64_decode((string) ($input['logo'] ?? ''), true);
        if (
            $profile === null || $logo === false || strlen($logo) > 200000
            || !hash_equals($profile[4], hash('sha256', $logo))
        ) {
            throw new InvalidArgumentException('Profile or original logo outside approved five.');
        }
        $csrf = (string) ($input['csrf'] ?? '');
        if (preg_match('/^[a-f0-9]{48}$/D', $csrf) !== 1) {
            throw new InvalidArgumentException('Invalid CSRF.');
        }
        $path = '/beheer/';
        // A zero contribution is the required technical default, not a confirmed amount.
        $fields = [
            'action' => 'profile-save', 'csrf' => $csrf, 'profile_id' => '', 'revision' => '0',
            'application' => '', 'name' => $profile[0], 'website' => $profile[1], 'description' => $profile[2],
            'edition_id' => 'opheusden-2026', 'amount' => '0', 'tier' => 'friend',
            'sort_order' => $profile[3], 'visible' => '1', 'logo_dark' => '0',
        ];
        $boundary = 'ijsbaan29-' . bin2hex(random_bytes(16));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"logo\"; filename=\"logo.webp\"\r\n";
        $body .= "Content-Type: image/webp\r\n\r\n" . $logo . "\r\n--" . $boundary . "--\r\n";
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

function ijsbaan29Execute(array $request): array
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
        CURLOPT_USERAGENT => 'IJsbaan-approved-sponsors-SRV01/20260929',
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
