<?php
// Hosted on PHP 8.5; keep credentials and uploaded images outside the document root.
declare(strict_types=1);
require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/mail.php';

function sponsor_config(): array {
    static $config;
    if ($config === null) {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $config;
}
function sponsor_db(): PDO {
    static $db;
    if ($db === null) {
        $c = sponsor_config()['db'];
        $db = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec("SET time_zone = '+00:00'");
    }
    return $db;
}
function sponsor_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function sponsor_fail(string $message, int $status = 422, array $fields = []): void {
    sponsor_json(['ok' => false, 'message' => $message, 'fields' => $fields], $status);
}
function sponsor_catalog(): array {
    return json_decode(file_get_contents(__DIR__ . '/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
}
function sponsor_token(): string {
    $payload = time() . '.' . bin2hex(random_bytes(24));
    return $payload . '.' . hash_hmac('sha256', $payload, sponsor_config()['form_key']);
}
function sponsor_valid_token(string $token): bool {
    $parts = explode('.', $token);
    return count($parts) === 3 && ctype_digit($parts[0]) && strlen($parts[1]) === 48
        && (int)$parts[0] <= time() && (int)$parts[0] >= time() - 7200
        && hash_equals(hash_hmac('sha256', $parts[0] . '.' . $parts[1], sponsor_config()['form_key']), $parts[2]);
}
function sponsor_is_admin(): bool {
    $key = $_SERVER['HTTP_X_MAINTENANCE_KEY'] ?? '';
    return is_string($key) && strlen($key) === 64 && hash_equals(sponsor_config()['maintenance_key'], $key);
}
function sponsor_text(string $key, int $max, bool $required = true): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || !preg_match('//u', $value)) sponsor_fail('Controleer dit veld.', 422, [$key => 'Gebruik gewone tekst.']);
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        sponsor_fail('Controleer de ingevulde gegevens.', 422, [$key => 'Vul dit veld correct in (maximaal ' . $max . ' tekens).']);
    }
    if ($key !== 'notes' && preg_match('/[\r\n]/', $value)) sponsor_fail('Gebruik één regel tekst.', 422, [$key => 'Gebruik één regel tekst.']);
    return $value;
}
function sponsor_rate_limit(PDO $db, string $identity, int $max, string $scope): void {
    $bucket = hash_hmac('sha256', $scope . ':' . gmdate('Y-m-d-H') . ':' . $identity, sponsor_config()['form_key']);
    $db->exec('DELETE FROM sponsor_rate_limits WHERE expires_at < UTC_TIMESTAMP()');
    $q = $db->prepare('INSERT INTO sponsor_rate_limits (bucket, hits, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 HOUR)) ON DUPLICATE KEY UPDATE hits = hits + 1');
    $q->execute([$bucket]);
    $q = $db->prepare('SELECT hits FROM sponsor_rate_limits WHERE bucket = ?');
    $q->execute([$bucket]);
    if ((int)$q->fetchColumn() > $max) sponsor_fail('Er zijn kort na elkaar meerdere pogingen gedaan. Probeer het over een uur opnieuw of bel Ton.', 429);
}
function sponsor_logo(): ?string {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES['logo'];
    if (!is_int($f['error']) || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 2 * 1024 * 1024) {
        sponsor_fail('Je logo kon niet worden toegevoegd.', 422, ['logo' => 'Kies een PNG, JPG of WebP van maximaal 2 MB.']);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $size = @getimagesize($f['tmp_name']);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || !$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 6000 || $size[1] > 6000 || $size[0] * $size[1] > 12000000) {
        sponsor_fail('Dit bestand is geen geschikt logo.', 422, ['logo' => 'Gebruik een PNG, JPG of WebP tot 6.000 pixels breed of hoog (maximaal 12 megapixels).']);
    }
    $image = @imagecreatefromstring(file_get_contents($f['tmp_name']));
    if (!$image) sponsor_fail('Dit logo kan niet worden gelezen.', 422, ['logo' => 'Sla de afbeelding opnieuw op als PNG of JPG.']);
    // Decode and re-encode: metadata, original filename and appended payloads are discarded.
    imagesavealpha($image, true);
    $directory = dirname(__DIR__) . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0700)) throw new RuntimeException('Private upload directory unavailable');
    $name = bin2hex(random_bytes(24)) . '.png';
    $path = $directory . '/' . $name;
    $written = imagepng($image, $path, 6);
    imagedestroy($image);
    if (!$written) throw new RuntimeException('Logo storage failed');
    chmod($path, 0600);
    if (filesize($path) > 8 * 1024 * 1024) {
        unlink($path);
        sponsor_fail('Dit logo wordt te groot voor de e-mailbijlage.', 422, ['logo' => 'Kies een kleinere afbeelding.']);
    }
    return $name;
}
function sponsor_result(int $id): array {
    $db = sponsor_db();
    $q = $db->prepare('SELECT reference FROM sponsor_applications WHERE id = ?');
    $q->execute([$id]);
    $reference = $q->fetchColumn();
    $q = $db->prepare('SELECT recipient_kind, state FROM sponsor_mail WHERE application_id = ?');
    $q->execute([$id]);
    $states = $q->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['ok' => true, 'reference' => $reference, 'confirmationSent' => ($states['sponsor'] ?? '') === 'sent', 'organizerNotified' => ($states['organizer'] ?? '') === 'sent'];
}
function sponsor_route(): void {
    ignore_user_abort(true);
    $config = sponsor_config();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    if ($action !== '') {
        if ($method !== 'POST' || !sponsor_is_admin()) sponsor_fail('Niet gevonden.', 404);
        sponsor_maintenance($action);
        return;
    }
    if (empty($config['enabled'])) sponsor_fail('Het aanvraagformulier wordt klaargemaakt. Neem intussen contact op met Ton.', 503);
    if ($method === 'GET') sponsor_json(['ok' => true, 'token' => sponsor_token(), 'edition' => sponsor_catalog()['edition']['id']]);
    if ($method !== 'POST') { header('Allow: GET, POST'); sponsor_fail('Deze methode is niet beschikbaar.', 405); }
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $config['site_url']) sponsor_fail('Open het formulier op de website en probeer opnieuw.', 403);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 3 * 1024 * 1024) sponsor_fail('De aanvraag is te groot. Kies een logo van maximaal 2 MB.', 413);
    $token = $_POST['token'] ?? '';
    if (!is_string($token) || !sponsor_valid_token($token)) sponsor_fail('Het formulier is verlopen. Sluit het venster en open je pakket opnieuw.', 403);
    if (!empty($_POST['website'])) sponsor_fail('De aanvraag is niet verzonden. Neem contact op met Ton.', 422);
    $db = sponsor_db();
    $hash = hash('sha256', $token);
    $q = $db->prepare('SELECT id FROM sponsor_applications WHERE submission_hash = ?');
    $q->execute([$hash]);
    if ($existing = $q->fetchColumn()) sponsor_json(sponsor_result((int)$existing));
    $isTest = sponsor_is_admin();
    if (!$isTest) sponsor_rate_limit($db, $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20, 'ip');
    $catalog = sponsor_catalog();
    $amount = sponsor_text('package', 10);
    if (!isset($catalog['packages'][$amount]) || ($_POST['edition'] ?? '') !== $catalog['edition']['id']) sponsor_fail('Kies een pakket uit de huidige editie.', 422, ['package' => 'Kies een geldig sponsorpakket.']);
    $company = sponsor_text('company', 160);
    $name = sponsor_text('contact', 160);
    $email = sponsor_text('email', 254);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sponsor_fail('Controleer je e-mailadres.', 422, ['email' => 'Vul een geldig e-mailadres in.']);
    if ($isTest && strtolower($email) !== strtolower($config['from_email'])) sponsor_fail('Een technische test mag alleen naar de eigen mailbox.', 422);
    $phone = sponsor_text('phone', 40, false);
    $notes = sponsor_text('notes', 1500, false);
    if (($_POST['privacy'] ?? '') !== '1') sponsor_fail('Bevestig dat we je gegevens voor deze aanvraag mogen gebruiken.', 422, ['privacy' => 'Vink dit vakje aan om je aanvraag te versturen.']);
    if (!$isTest) sponsor_rate_limit($db, strtolower($email), 5, 'email');
    $logo = sponsor_logo();
    try {
        $db->beginTransaction();
        $q = $db->prepare('INSERT INTO sponsor_applications (reference, submission_hash, created_at, edition_id, package_amount, snapshot_json, company, contact_name, email, phone, notes, logo_file, is_test) VALUES (?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $q->execute(['IJS-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(4))), $hash, $catalog['edition']['id'], (int)$amount, json_encode(['edition' => $catalog['edition'], 'package' => $catalog['packages'][$amount]], JSON_UNESCAPED_UNICODE), $company, $name, $email, $phone, $notes, $logo, $isTest ? 1 : 0]);
        $id = (int)$db->lastInsertId();
        $q = $db->prepare('INSERT INTO sponsor_mail (application_id, recipient_kind) VALUES (?, ?), (?, ?)');
        $q->execute([$id, 'organizer', $id, 'sponsor']);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        if ($logo) unlink(dirname(__DIR__) . '/uploads/' . $logo);
        if ($error instanceof PDOException && $error->getCode() === '23000') {
            $q = $db->prepare('SELECT id FROM sponsor_applications WHERE submission_hash = ?');
            $q->execute([$hash]);
            if ($existing = $q->fetchColumn()) sponsor_json(sponsor_result((int)$existing));
        }
        throw $error;
    }
    // Persistence succeeded. A temporary delivery problem must never pretend the request was lost.
    try { sponsor_deliver($id); } catch (Throwable $error) { error_log('Sponsor delivery pending for application ' . $id); }
    sponsor_json(sponsor_result($id), 201);
}
function sponsor_maintenance(string $action): void {
    $db = sponsor_db();
    if ($action === 'profiles-import') {
        require_once __DIR__ . '/partners.php';
        partner_import();
    }
    if ($action === 'admin-link') {
        require_once __DIR__ . '/admin.php';
        sponsor_json(['ok' => true, 'ticket' => sponsor_admin_ticket(), 'expiresIn' => 300]);
    }
    if ($action === 'migrate') {
        if ($db->query('SELECT DATABASE()')->fetchColumn() !== sponsor_config()['db']['name']) throw new RuntimeException('Wrong database');
        foreach (explode(';', file_get_contents(__DIR__ . '/schema.sql')) as $statement) {
            if (trim($statement)) $db->exec($statement);
        }
        sponsor_json(['ok' => true, 'schema' => 1]);
    }
    if ($action === 'retry') {
        $reference = $_POST['reference'] ?? '';
        if (!is_string($reference) || !preg_match('/^IJS-[0-9]{6}-[A-F0-9]{8}$/', $reference)) sponsor_fail('Ongeldige referentie.');
        $q = $db->prepare('SELECT id FROM sponsor_applications WHERE reference = ?');
        $q->execute([$reference]);
        $id = $q->fetchColumn();
        if (!$id) sponsor_fail('Aanvraag niet gevonden.', 404);
        sponsor_deliver((int)$id);
        sponsor_json(sponsor_result((int)$id));
    }
    if ($action === 'health') {
        $version = json_decode(file_get_contents(__DIR__ . '/version.json'), true);
        $pending = $db->query("SELECT a.reference, a.is_test, m.recipient_kind, m.state, m.attempts, m.failure_code FROM sponsor_mail m JOIN sponsor_applications a ON a.id = m.application_id WHERE m.state <> 'sent' ORDER BY m.id LIMIT 50")->fetchAll();
        sponsor_json(['ok' => true, 'commit' => $version['commit'], 'php' => PHP_VERSION, 'schema' => 1, 'enabled' => (bool)sponsor_config()['enabled'], 'applications' => (int)$db->query('SELECT COUNT(*) FROM sponsor_applications WHERE is_test = 0')->fetchColumn(), 'tests' => (int)$db->query('SELECT COUNT(*) FROM sponsor_applications WHERE is_test = 1')->fetchColumn(), 'pending' => $pending]);
    }
    sponsor_fail('Onbekende actie.', 404);
}
