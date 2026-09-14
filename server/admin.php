<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_views.php';

function sponsor_admin_ticket(): string {
    $db = sponsor_db();
    $db->exec('DELETE FROM sponsor_admin_tickets WHERE expires_at < UTC_TIMESTAMP()');
    $ticket = bin2hex(random_bytes(32));
    $q = $db->prepare('INSERT INTO sponsor_admin_tickets (token_hash, created_at, expires_at) VALUES (?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))');
    $q->execute([hash('sha256', $ticket)]);
    return $ticket;
}
function sponsor_admin_session(): void {
    $directory = dirname(__DIR__) . '/sessions';
    if (!is_dir($directory) && !mkdir($directory, 0700)) throw new RuntimeException('Session storage unavailable');
    session_save_path($directory);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '28800');
    session_name('__Host-ijsbaan_beheer');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    if (isset($_SESSION['admin_since']) && ((int)$_SESSION['admin_since'] < time() - 28800 || (int)($_SESSION['admin_seen'] ?? 0) < time() - 7200)) {
        unset($_SESSION['admin_since'], $_SESSION['admin_seen']);
        session_regenerate_id(true);
    }
    // A separate hashed, rotating device token restores a short browser session for up to 30 days.
    if (!isset($_SESSION['admin_since']) && isset($_COOKIE['__Host-ijsbaan_herken'])) {
        $token = $_COOKIE['__Host-ijsbaan_herken'];
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $new = bin2hex(random_bytes(32));
            $q = sponsor_db()->prepare('SELECT expires_at FROM sponsor_admin_devices WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()');
            $q->execute([hash('sha256', $token)]);
            $expires = $q->fetchColumn();
            if ($expires) {
                $q = sponsor_db()->prepare('UPDATE sponsor_admin_devices SET token_hash = ? WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()');
                $q->execute([hash('sha256', $new), hash('sha256', $token)]);
                if ($q->rowCount() === 1) {
                    sponsor_admin_device_cookie($new, (new DateTimeImmutable($expires, new DateTimeZone('UTC')))->getTimestamp());
                    session_regenerate_id(true);
                    $_SESSION['admin_since'] = time(); $_SESSION['admin_seen'] = time();
                    $_SESSION['csrf'] = bin2hex(random_bytes(24));
                }
            }
        }
    }
    if (isset($_SESSION['admin_since'])) $_SESSION['admin_seen'] = time();
}
function sponsor_admin_device_cookie(string $value, int $expires): void {
    setcookie('__Host-ijsbaan_herken', $value, ['expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if ($value !== '') $_COOKIE['__Host-ijsbaan_herken'] = $value;
    else unset($_COOKIE['__Host-ijsbaan_herken']);
}
function sponsor_admin_forget_device(): void {
    $token = $_COOKIE['__Host-ijsbaan_herken'] ?? '';
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
        $q = sponsor_db()->prepare('DELETE FROM sponsor_admin_devices WHERE token_hash = ?');
        $q->execute([hash('sha256', $token)]);
    }
    sponsor_admin_device_cookie('', 1);
}
function sponsor_admin_filters(): array {
    $get = function (string $key, int $max = 160): string {
        $value = $_GET[$key] ?? '';
        return is_string($value) && preg_match('//u', $value) ? mb_substr(trim($value), 0, $max, 'UTF-8') : '';
    };
    $filters = ['q' => $get('q'), 'edition' => $get('edition', 80), 'package' => $get('package', 10), 'view' => $get('view', 10) === 'test' ? 'test' : 'real'];
    $where = ['a.is_test = ?']; $params = [$filters['view'] === 'test' ? 1 : 0];
    if ($filters['q'] !== '') {
        $like = '%' . addcslashes($filters['q'], '\\%_') . '%';
        $where[] = '(a.company LIKE ? OR a.contact_name LIKE ? OR a.email LIKE ? OR a.reference LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['edition'] !== '') { $where[] = 'a.edition_id = ?'; $params[] = $filters['edition']; }
    if ($filters['package'] !== '') { $where[] = 'a.package_amount = ?'; $params[] = ctype_digit($filters['package']) ? (int)$filters['package'] : -1; }
    return [$filters, implode(' AND ', $where), $params];
}
function sponsor_admin_select(): string {
    return "SELECT a.*, organizer.state AS organizer_state, sponsor.state AS sponsor_state FROM sponsor_applications a LEFT JOIN sponsor_mail organizer ON organizer.application_id = a.id AND organizer.recipient_kind = 'organizer' LEFT JOIN sponsor_mail sponsor ON sponsor.application_id = a.id AND sponsor.recipient_kind = 'sponsor'";
}
function sponsor_admin_get(string $reference): ?array {
    if (!preg_match('/^IJS-[0-9]{6}-[A-F0-9]{8}$/', $reference)) return null;
    $q = sponsor_db()->prepare(sponsor_admin_select() . ' WHERE a.reference = ?');
    $q->execute([$reference]);
    return $q->fetch() ?: null;
}
function sponsor_admin_csv_cell($value): string {
    $text = (string)$value;
    // Excel must treat untrusted contact data as text, never as a formula.
    if (preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $text)) $text = "'" . $text;
    return $text;
}
function sponsor_admin_route(): void {
    sponsor_admin_session();
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== sponsor_config()['site_url']) sponsor_fail('Open het beheer op de eigen website.', 403);
        $action = $_POST['action'] ?? '';
        if ($action === 'login') {
            sponsor_rate_limit(sponsor_db(), $_SERVER['REMOTE_ADDR'] ?? '', 30, 'admin-login');
            $ticket = $_POST['ticket'] ?? '';
            if (!is_string($ticket) || !preg_match('/^[a-f0-9]{64}$/', $ticket)) sponsor_fail('Deze inloglink is ongeldig. Open Sponsorbeheer opnieuw op je pc.', 401);
            $q = sponsor_db()->prepare('UPDATE sponsor_admin_tickets SET used_at = UTC_TIMESTAMP() WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()');
            $q->execute([hash('sha256', $ticket)]);
            if ($q->rowCount() !== 1) sponsor_fail('Deze inloglink is verlopen of al gebruikt. Open Sponsorbeheer opnieuw op je pc.', 401);
            session_regenerate_id(true);
            $_SESSION['admin_since'] = time(); $_SESSION['admin_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            sponsor_admin_forget_device();
            $device = bin2hex(random_bytes(32));
            $db = sponsor_db();
            $db->exec('DELETE FROM sponsor_admin_devices WHERE expires_at < UTC_TIMESTAMP()');
            $q = $db->prepare('INSERT INTO sponsor_admin_devices (token_hash, created_at, expires_at) VALUES (?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
            $q->execute([hash('sha256', $device)]);
            sponsor_admin_device_cookie($device, time() + 30 * 86400);
            sponsor_json(['ok' => true]);
        }
        if ($action === 'logout') {
            $csrf = $_POST['csrf'] ?? '';
            if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) sponsor_fail('Vernieuw de pagina en probeer opnieuw.', 403);
            sponsor_admin_forget_device();
            $_SESSION = [];
            session_destroy();
            setcookie(session_name(), '', ['expires' => 1, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            header('Location: /beheer/', true, 303); exit;
        }
        sponsor_fail('Onbekende actie.', 400);
    }
    if ($method !== 'GET' && $method !== 'HEAD') { header('Allow: GET, HEAD, POST'); sponsor_fail('Methode niet toegestaan.', 405); }
    if (!isset($_SESSION['admin_since'])) {
        if (isset($_GET['csv']) || isset($_GET['logo']) || isset($_GET['id'])) http_response_code(401);
        sponsor_admin_login_page(); return;
    }
    $csrf = $_SESSION['csrf'];
    session_write_close();
    if (isset($_GET['logo'])) {
        $app = is_string($_GET['logo']) ? sponsor_admin_get($_GET['logo']) : null;
        $file = $app['logo_file'] ?? '';
        if (!$app || !preg_match('/^[a-f0-9]{48}\.png$/', $file) || !is_file(dirname(__DIR__) . '/uploads/' . $file)) { http_response_code(404); sponsor_admin_missing($csrf); return; }
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="logo-' . $app['reference'] . '.png"');
        header('Content-Length: ' . filesize(dirname(__DIR__) . '/uploads/' . $file));
        readfile(dirname(__DIR__) . '/uploads/' . $file); exit;
    }
    if (isset($_GET['id'])) {
        $app = is_string($_GET['id']) ? sponsor_admin_get($_GET['id']) : null;
        if (!$app) { http_response_code(404); sponsor_admin_missing($csrf); return; }
        sponsor_admin_detail($app, $csrf); return;
    }
    [$filters, $where, $params] = sponsor_admin_filters();
    $db = sponsor_db();
    if (isset($_GET['csv'])) {
        $q = $db->prepare(sponsor_admin_select() . ' WHERE ' . $where . ' ORDER BY a.created_at DESC, a.id DESC');
        $q->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sponsoraanvragen-' . $filters['view'] . '-' . gmdate('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Referentie', 'Datum (Nederland)', 'Bedrijf', 'Contactpersoon', 'E-mail', 'Telefoon', 'Editie', 'Pakket EUR', 'Schaatsmunten', 'Opmerkingen', 'Logo', 'E-mail Ton', 'E-mail sponsor', 'Test'], ';', '"', '');
        while ($app = $q->fetch()) {
            $snapshot = json_decode($app['snapshot_json'], true);
            $values = [$app['reference'], sponsor_admin_date($app['created_at']), $app['company'], $app['contact_name'], $app['email'], $app['phone'], $snapshot['edition']['place'] . ' ' . $snapshot['edition']['season'], $app['package_amount'], $snapshot['package']['coins'] ?? 'Niet vermeld', $app['notes'], $app['logo_file'] ? 'Ja' : 'Nee', sponsor_admin_mail_label($app['organizer_state']), sponsor_admin_mail_label($app['sponsor_state']), $app['is_test'] ? 'Ja' : 'Nee'];
            fputcsv($out, array_map('sponsor_admin_csv_cell', $values), ';', '"', '');
        }
        fclose($out); exit;
    }
    $q = $db->prepare('SELECT COUNT(*) AS total, COUNT(DISTINCT a.company) AS companies, COALESCE(SUM(a.package_amount), 0) AS amount FROM sponsor_applications a WHERE ' . $where);
    $q->execute($params); $stats = $q->fetch();
    $pages = max(1, (int)ceil((int)$stats['total'] / 50));
    $page = min($pages, max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
    $q = $db->prepare(sponsor_admin_select() . ' WHERE ' . $where . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50));
    $q->execute($params); $apps = $q->fetchAll();
    $counts = $db->query('SELECT is_test, COUNT(*) FROM sponsor_applications GROUP BY is_test')->fetchAll(PDO::FETCH_KEY_PAIR);
    $editions = $db->query('SELECT DISTINCT edition_id FROM sponsor_applications ORDER BY edition_id DESC')->fetchAll(PDO::FETCH_COLUMN);
    $packages = $db->query('SELECT DISTINCT package_amount FROM sponsor_applications ORDER BY package_amount')->fetchAll(PDO::FETCH_COLUMN);
    sponsor_admin_list($apps, $filters, $stats, $counts, $editions, $packages, $page, $pages, $csrf);
}
