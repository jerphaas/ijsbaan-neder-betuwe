<?php
declare(strict_types=1);

function partner_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function partner_url_valid(string $url): bool {
    if ($url === '') return true;
    $parts = parse_url($url);
    return (bool)filter_var($url, FILTER_VALIDATE_URL) && $parts && in_array($parts['scheme'] ?? '', ['https', 'http'], true)
        && !isset($parts['user']) && !isset($parts['pass']) && !preg_match('/[\x00-\x20\x7f]/', $url);
}
function partner_get(string $id): ?array {
    $q = sponsor_db()->prepare('SELECT * FROM sponsor_profiles WHERE id = ?');
    $q->execute([$id]); return $q->fetch() ?: null;
}
function partner_public(): array {
    $q = sponsor_db()->prepare('SELECT id, name, website, description, tier, logo_asset, logo_file, logo_dark, revision FROM sponsor_profiles WHERE visible = 1 AND edition_id = ? ORDER BY sort_order, name, id');
    $q->execute([sponsor_catalog()['edition']['id']]); return $q->fetchAll();
}
function partner_logo_url(array $p, bool $admin = false): string {
    if (!empty($p['logo_file'])) return ($admin ? '/beheer/?partner_logo=' : '/api/partners.php?logo=') . rawurlencode($p['id']) . '&v=' . (int)$p['revision'];
    if (preg_match('~^assets/partners/[a-z0-9-]+\.webp$~', $p['logo_asset'] ?? '')) return '/' . $p['logo_asset'];
    return '';
}
function partner_tile(array $p, bool $main = false): string {
    $e = 'partner_escape'; $url = partner_url_valid($p['website']) ? $p['website'] : '';
    $tag = $url ? 'a' : 'div'; $logo = partner_logo_url($p);
    $html = '<li class="partner-item"><' . $tag . ' class="partner-tile' . (!empty($p['logo_dark']) ? ' partner-dark' : '') . '"';
    if ($url) $html .= ' href="' . $e($url) . '" target="_blank" rel="noopener sponsored" aria-label="Website van ' . $e($p['name']) . ' (nieuw tabblad)"';
    $html .= '>';
    if ($logo) $html .= '<span class="partner-logo"><img src="' . $e($logo) . '" alt="Logo van ' . $e($p['name']) . '" width="320" height="120" loading="lazy" decoding="async"></span>';
    else $html .= '<span class="partner-wordmark">' . $e($p['name']) . '</span>';
    if ($logo) $html .= '<span class="partner-name">' . $e($p['name']) . '</span>';
    if (!$main && $p['description'] !== '') $html .= '<span class="partner-description">' . $e($p['description']) . '</span>';
    return $html . '</' . $tag . '></li>';
}
function partner_fragments(array $profiles): array {
    $mains = array_values(array_filter($profiles, fn($p) => $p['tier'] === 'main'));
    $strip = $mains ? '<div class="partner-strip-inner container" data-partners-rendered><div class="partner-strip-title"><span class="eyebrow">ONZE HOOFDSPONSORS</span><span>Een groot hart voor kleine schaatsers.</span><a href="#onze-sponsors">Bekijk alle sponsors <span aria-hidden="true">↗</span></a></div><ul class="partner-main-list" aria-label="Hoofdsponsors">' . implode('', array_map(fn($p) => partner_tile($p, true), $mains)) . '</ul></div>' : '';
    $grid = '<div data-partners-rendered>';
    if ($profiles) {
        $grid .= '<p class="partner-count">' . count($profiles) . ' sponsors doen mee aan deze editie</p><ul class="partner-wall" id="partner-wall" aria-label="Alle sponsors">';
        foreach ($profiles as $p) $grid .= partner_tile($p);
        $grid .= '</ul>';
        if (count($profiles) > 12) $grid .= '<button type="button" class="button partner-expand" aria-expanded="false" aria-controls="partner-wall" hidden>Bekijk alle ' . count($profiles) . ' sponsors <span aria-hidden="true">↓</span></button>';
    } else $grid .= '<p class="partner-empty">Binnenkort vind je hier de sponsors van deze editie.</p>';
    return ['strip' => $strip, 'grid' => $grid . '</div>'];
}
function partner_render_home(string $template): string {
    $parts = partner_fragments(partner_public());
    foreach ($parts as $key => $html) {
        $marker = strtoupper($key);
        $template = preg_replace_callback('~(<!-- PARTNERS:' . $marker . ':START -->).*?(<!-- PARTNERS:' . $marker . ':END -->)~s', fn($m) => $m[1] . $html . $m[2], $template, 1);
    }
    return $template;
}
function partner_serve_logo(array $p): void {
    $file = $p['logo_file'] ?? '';
    $path = dirname(__DIR__) . '/uploads/' . $file;
    if (!preg_match('/^[a-f0-9]{48}\.png$/', $file) || !is_file($path)) { http_response_code(404); exit; }
    // Resize the public delivery copy, never expose the original upload path or metadata.
    $image = imagecreatefrompng($path);
    $scale = min(1, 480 / imagesx($image), 200 / imagesy($image));
    $out = imagecreatetruecolor(max(1, (int)(imagesx($image) * $scale)), max(1, (int)(imagesy($image) * $scale)));
    imagealphablending($out, false); imagesavealpha($out, true);
    imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), imagesx($image), imagesy($image));
    header('Content-Type: image/webp'); header('Cache-Control: no-store');
    imagewebp($out, null, 90); imagedestroy($out); imagedestroy($image); exit;
}
function partner_api(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); sponsor_fail('Methode niet toegestaan.', 405); }
    if (isset($_GET['logo'])) {
        $p = is_string($_GET['logo']) ? partner_get($_GET['logo']) : null;
        if (!$p || !$p['visible'] || $p['edition_id'] !== sponsor_catalog()['edition']['id']) { http_response_code(404); exit; }
        partner_serve_logo($p);
    }
    sponsor_json(['ok' => true, 'fragments' => partner_fragments(partner_public())]);
}
function partner_import(): void {
    // A bounded, idempotent import; existing profiles are never overwritten.
    $raw = $_POST['profiles'] ?? '';
    if (!is_string($raw) || strlen($raw) > 300000) sponsor_fail('Ongeldig importbestand.');
    $rows = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 200) sponsor_fail('Onverwacht aantal sponsors.');
    $seen = [];
    foreach ($rows as $p) {
        if (!is_array($p) || !preg_match('/^excel-2026-[0-9]{2}$/', $p['id'] ?? '') || isset($seen[$p['id']])) sponsor_fail('Ongeldig of dubbel bronnummer.');
        $seen[$p['id']] = true;
        foreach (['name' => 160, 'website' => 600, 'description' => 600, 'source_note' => 1500] as $field => $length) {
            if (!is_string($p[$field] ?? null) || !preg_match('//u', $p[$field]) || mb_strlen($p[$field]) > $length) sponsor_fail('Ongeldig tekstveld.');
        }
        if (trim($p['name']) === '' || !partner_url_valid($p['website']) || $p['edition_id'] !== sponsor_catalog()['edition']['id'] || !in_array($p['tier'], ['main', 'partner', 'friend'], true)) sponsor_fail('Ongeldig sponsorprofiel.');
        foreach (['amount' => 1000000, 'sort_order' => 100000, 'visible' => 1, 'logo_dark' => 1] as $field => $max) if (!is_int($p[$field] ?? null) || $p[$field] < 0 || $p[$field] > $max) sponsor_fail('Ongeldig getal.');
        if ($p['logo_asset'] !== '' && (!preg_match('~^assets/partners/[a-z0-9-]+\.webp$~', $p['logo_asset']) || !is_file(dirname(__DIR__, 2) . '/public_html/' . $p['logo_asset']))) sponsor_fail('Logo ontbreekt op de website.');
    }
    $db = sponsor_db(); $created = 0; $existing = 0;
    try {
        $db->beginTransaction();
        foreach ($rows as $p) {
            if (partner_get($p['id'])) { $existing++; continue; }
            $keys = ['id','name','website','description','edition_id','amount','tier','sort_order','visible','logo_asset','logo_dark','source_note'];
            $q = $db->prepare('INSERT INTO sponsor_profiles (' . implode(',', $keys) . ', created_at, updated_at) VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP())');
            $q->execute(array_map(fn($k) => $p[$k], $keys)); $created++;
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    sponsor_json(['ok' => true, 'created' => $created, 'preserved' => $existing, 'total' => (int)$db->query('SELECT COUNT(*) FROM sponsor_profiles')->fetchColumn()]);
}
