<?php
declare(strict_types=1);
require_once __DIR__ . '/partners.php';

function partner_admin_save(): void {
    if (!isset($_SESSION['admin_since'])) sponsor_fail('Log opnieuw in om wijzigingen op te slaan.', 401);
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) sponsor_fail('Vernieuw de pagina en probeer opnieuw.', 403);
    $id = sponsor_text('profile_id', 64, false);
    $old = $id !== '' ? partner_get($id) : null;
    if ($id !== '' && !$old) sponsor_fail('Deze sponsor bestaat niet meer.', 404);
    $revision = filter_var($_POST['revision'] ?? '', FILTER_VALIDATE_INT);
    if ($old && (int)$old['revision'] !== $revision) sponsor_fail('Deze sponsor is ondertussen gewijzigd. Vernieuw de pagina voordat je opnieuw opslaat.', 409);
    $name = sponsor_text('name', 160);
    $website = sponsor_text('website', 600, false);
    if (!partner_url_valid($website)) sponsor_fail('Gebruik een volledige website, bijvoorbeeld https://www.bedrijf.nl.', 422, ['website' => 'Vul een geldig webadres in.']);
    $description = sponsor_text('description', 600, false);
    $edition = sponsor_text('edition_id', 80);
    if (!in_array($edition, array_column(sponsor_catalog()['editions'], 'id'), true)) sponsor_fail('Kies een bekende editie.');
    $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    $order = filter_var($_POST['sort_order'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    if ($amount === false || $order === false) sponsor_fail('Vul een geldig bedrag en een geldige volgorde in.');
    $tier = sponsor_text('tier', 16);
    if (!in_array($tier, ['main', 'partner', 'friend'], true)) sponsor_fail('Kies een geldige plek op de website.');
    $visible = ($_POST['visible'] ?? '') === '1' ? 1 : 0;
    $dark = ($_POST['logo_dark'] ?? '') === '1' ? 1 : 0;
    $app = null;
    if (!$old && ($_POST['application'] ?? '') !== '') {
        $app = sponsor_admin_get(sponsor_text('application', 32));
        if (!$app || $app['is_test']) sponsor_fail('Gebruik een echte sponsoraanvraag.', 422);
        $q = sponsor_db()->prepare('SELECT id FROM sponsor_profiles WHERE application_id = ?'); $q->execute([$app['id']]);
        if ($q->fetchColumn()) sponsor_fail('Deze aanvraag is al aan een sponsor gekoppeld. Open het bestaande profiel.', 409);
    }
    $logo = sponsor_logo();
    $remove = ($_POST['remove_logo'] ?? '') === '1';
    $file = $logo ?? ($remove ? null : ($old['logo_file'] ?? $app['logo_file'] ?? null));
    $asset = ($logo || $remove) ? '' : ($old['logo_asset'] ?? '');
    $id = $old['id'] ?? ('sp-' . bin2hex(random_bytes(12)));
    $db = sponsor_db();
    try {
        if ($old) {
            $q = $db->prepare('UPDATE sponsor_profiles SET name=?, website=?, description=?, edition_id=?, amount=?, tier=?, sort_order=?, visible=?, logo_file=?, logo_asset=?, logo_dark=?, revision=revision+1, updated_at=UTC_TIMESTAMP() WHERE id=? AND revision=?');
            $q->execute([$name,$website,$description,$edition,$amount,$tier,$order,$visible,$file,$asset,$dark,$id,$revision]);
            if ($q->rowCount() !== 1) {
                if ($logo) unlink(dirname(__DIR__) . '/uploads/' . $logo);
                sponsor_fail('Deze sponsor is ondertussen gewijzigd. Vernieuw de pagina.', 409);
            }
        } else {
            $q = $db->prepare('INSERT INTO sponsor_profiles (id,name,website,description,edition_id,amount,tier,sort_order,visible,logo_file,logo_asset,logo_dark,source_note,application_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute([$id,$name,$website,$description,$edition,$amount,$tier,$order,$visible,$file,$asset,$dark,$app ? 'Toegevoegd vanuit aanvraag ' . $app['reference'] : 'Handmatig toegevoegd via sponsorbeheer.',$app['id'] ?? null]);
        }
    } catch (Throwable $e) {
        if ($logo && is_file(dirname(__DIR__) . '/uploads/' . $logo)) unlink(dirname(__DIR__) . '/uploads/' . $logo);
        if ($e instanceof PDOException && $e->getCode() === '23000') sponsor_fail('Deze aanvraag heeft al een sponsorprofiel.', 409);
        throw $e;
    }
    $url = '/beheer/?partners=1&edit=' . rawurlencode($id) . '&saved=1';
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') sponsor_json(['ok' => true, 'url' => $url]);
    header('Location: ' . $url, true, 303); exit;
}
function partner_admin_route(string $csrf): void {
    if (isset($_GET['partner_logo'])) {
        $p = is_string($_GET['partner_logo']) ? partner_get($_GET['partner_logo']) : null;
        if (!$p) { http_response_code(404); exit; }
        partner_serve_logo($p);
    }
    if (isset($_GET['edit']) || isset($_GET['new'])) {
        $p = isset($_GET['edit']) && is_string($_GET['edit']) ? partner_get($_GET['edit']) : null;
        if (isset($_GET['edit']) && !$p) { http_response_code(404); sponsor_admin_missing($csrf); return; }
        $app = null;
        if (!$p && isset($_GET['from']) && is_string($_GET['from'])) {
            $app = sponsor_admin_get($_GET['from']);
            if (!$app || $app['is_test']) { http_response_code(404); sponsor_admin_missing($csrf); return; }
            $q = sponsor_db()->prepare('SELECT id FROM sponsor_profiles WHERE application_id = ?'); $q->execute([$app['id']]);
            if ($existing = $q->fetchColumn()) { header('Location: /beheer/?partners=1&edit=' . rawurlencode($existing), true, 303); return; }
        }
        $p ??= ['id'=>'', 'name'=>$app['company'] ?? '', 'website'=>'', 'description'=>'', 'edition_id'=>$app['edition_id'] ?? sponsor_catalog()['edition']['id'], 'amount'=>$app['package_amount'] ?? 0, 'tier'=>($app['package_amount'] ?? 0) >= 5000 ? 'main' : 'friend', 'sort_order'=>100, 'visible'=>0, 'logo_file'=>null, 'logo_asset'=>'', 'logo_dark'=>0, 'revision'=>0, 'source_note'=>''];
        partner_admin_edit($p, $csrf, $app); return;
    }
    $rows = sponsor_db()->query('SELECT * FROM sponsor_profiles ORDER BY edition_id, sort_order, name')->fetchAll();
    partner_admin_list($rows, $csrf);
}
function partner_admin_list(array $rows, string $csrf): void {
    sponsor_admin_header('Sponsors op de website', $csrf); $current = sponsor_catalog()['edition']['id'];
    $visible = count(array_filter($rows, fn($p) => $p['visible'] && $p['edition_id'] === $current)); ?>
    <section class="overview-intro"><div><p class="eyebrow">EEN PLEK VOOR IEDERE WINTERMAKER</p><h1>Sponsors op de website<span>.</span></h1><p><?= $visible ?> zichtbaar bij de huidige editie · <?= count($rows) ?> opgeslagen profielen. Pas een sponsor aan om de website direct bij te werken.</p></div><a class="button" href="/beheer/?partners=1&new=1">+ Sponsor toevoegen</a></section>
    <p class="overview-note">Dit is het sponsoroverzicht voor de website. Online aanvragen vind je op het tabblad Sponsoraanvragen. Bedragen zijn alleen zichtbaar in het beheer.</p>
    <section class="overview-panel"><div class="table-wrap"><table class="applications-table profiles-table"><caption class="sr-only">Opgeslagen sponsorprofielen</caption><thead><tr><th>Bedrijf</th><th>Bijdrage</th><th>Plek</th><th>Zichtbaarheid</th><th>Logo</th><th><span class="sr-only">Bewerken</span></th></tr></thead><tbody>
    <?php foreach ($rows as $p): ?><tr><td data-label="Bedrijf"><a class="company-link" href="/beheer/?partners=1&amp;edit=<?= partner_escape($p['id']) ?>"><?= partner_escape($p['name']) ?></a><small><?= partner_escape($p['edition_id']) ?></small></td><td data-label="Bijdrage"><?= sponsor_admin_money($p['amount']) ?></td><td data-label="Plek"><?= $p['tier'] === 'main' ? 'Hoofdsponsor' : 'Sponsoroverzicht' ?><small>Volgorde <?= (int)$p['sort_order'] ?></small></td><td data-label="Zichtbaarheid"><span class="mail-badge <?= $p['visible'] && $p['edition_id'] === $current ? 'mail-sent' : 'mail-pending' ?>"><?= !$p['visible'] ? 'Verborgen' : ($p['edition_id'] === $current ? 'Op de website' : 'Andere editie') ?></span></td><td data-label="Logo"><?= partner_logo_url($p) ? 'Logo aanwezig' : 'Bedrijfsnaam' ?></td><td><a class="details-link" href="/beheer/?partners=1&amp;edit=<?= partner_escape($p['id']) ?>">Bewerken <?= sponsor_admin_icon('arrow-up-right') ?></a></td></tr><?php endforeach; ?>
    </tbody></table></div></section><?php sponsor_admin_footer();
}
function partner_admin_edit(array $p, string $csrf, ?array $app): void {
    sponsor_admin_header($p['id'] ? 'Sponsor aanpassen' : 'Sponsor toevoegen', $csrf); ?>
    <a class="back-link" href="/beheer/?partners=1">← Alle sponsors op de website</a><section class="detail-heading"><p class="eyebrow">SPONSORPROFIEL</p><h1><?= $p['id'] ? partner_escape($p['name']) : 'Een nieuwe wintermaker.' ?></h1><p>De bedrijfsnaam, website, korte tekst en het logo zijn openbaar zodra je ‘Zichtbaar op de website’ aanvinkt.</p></section>
    <?php if (isset($_GET['saved'])): ?><p class="profile-success" role="status">Opgeslagen. <?= $p['visible'] && $p['edition_id'] === sponsor_catalog()['edition']['id'] ? 'De sponsor is bijgewerkt op de website.' : 'Dit profiel is bewaard en wordt nu niet getoond bij de huidige editie.' ?> <a href="/#onze-sponsors" target="_blank" rel="noopener">Bekijk de website ↗</a></p><?php endif; ?>
    <form class="profile-form" method="post" action="/beheer/" enctype="multipart/form-data" data-profile-form>
    <input type="hidden" name="action" value="profile-save"><input type="hidden" name="csrf" value="<?= partner_escape($csrf) ?>"><input type="hidden" name="profile_id" value="<?= partner_escape($p['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$p['revision'] ?>"><input type="hidden" name="application" value="<?= partner_escape($app['reference'] ?? '') ?>">
    <div class="detail-grid"><section class="detail-card"><h2>Op de website</h2><label>Bedrijfsnaam<input name="name" maxlength="160" value="<?= partner_escape($p['name']) ?>" required></label><label>Website <span>(optioneel)</span><input type="url" name="website" maxlength="600" value="<?= partner_escape($p['website']) ?>" placeholder="https://www.bedrijf.nl"></label><label>Korte tekst <span>(optioneel, openbaar)</span><input name="description" maxlength="600" value="<?= partner_escape($p['description']) ?>" placeholder="Een korte omschrijving van het bedrijf"></label><label>Editie<select name="edition_id"><?php foreach (sponsor_catalog()['editions'] as $edition): ?><option value="<?= partner_escape($edition['id']) ?>" <?= $p['edition_id'] === $edition['id'] ? 'selected' : '' ?>><?= partner_escape($edition['place'] . ' ' . $edition['season']) ?></option><?php endforeach; ?></select></label><label class="check-label"><input type="checkbox" name="visible" value="1" <?= $p['visible'] ? 'checked' : '' ?>> Zichtbaar op de website bij deze editie</label></section>
    <section class="detail-card"><h2>Logo</h2><?php $logo = partner_logo_url($p, true); if ($logo): ?><div class="profile-logo-preview <?= $p['logo_dark'] ? 'on-dark' : '' ?>"><img src="<?= partner_escape($logo) ?>" alt="Huidig logo van <?= partner_escape($p['name']) ?>" width="320" height="120"></div><?php elseif ($app && $app['logo_file']): ?><p>Het logo uit de aanvraag wordt bij het opslaan overgenomen.</p><?php else: ?><p>Zonder logo tonen we de bedrijfsnaam in het overzicht.</p><?php endif; ?>
    <label>Logo toevoegen of vervangen<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG of WebP · maximaal 2 MB. Gebruik bij voorkeur een transparante achtergrond.</small></label><label class="check-label"><input type="checkbox" name="logo_dark" value="1" <?= $p['logo_dark'] ? 'checked' : '' ?>> Donkere achtergrond voor een licht logo</label><label class="check-label"><input type="checkbox" name="remove_logo" value="1"> Huidig logo weghalen en bedrijfsnaam tonen</label>
    <h2>Soort sponsor en bijdrage</h2><label>Soort sponsor<select name="tier"><option value="main" <?= $p['tier'] === 'main' ? 'selected' : '' ?>>Hoofdsponsor</option><option value="partner" <?= $p['tier'] === 'partner' ? 'selected' : '' ?>>Sponsor</option><option value="friend" <?= $p['tier'] === 'friend' ? 'selected' : '' ?>>Vriend van de ijsbaan</option></select></label><div class="profile-numbers"><label>Bijdrage in euro <span>(privé)</span><input type="number" name="amount" min="0" max="1000000" step="1" value="<?= (int)$p['amount'] ?>" required></label><label>Volgorde <span>(laag eerst)</span><input type="number" name="sort_order" min="0" max="100000" step="1" value="<?= (int)$p['sort_order'] ?>" required></label></div></section></div>
    <?php if ($p['source_note']): ?><p class="overview-note">Bron: <?= partner_escape($p['source_note']) ?></p><?php endif; ?>
    <div class="profile-save-bar"><button type="submit" class="button">Sponsor opslaan</button><a href="/beheer/?partners=1">Terug zonder opslaan</a><p data-profile-status role="status" aria-live="polite"></p></div></form><script src="/beheer/partners.js" defer></script>
    <?php sponsor_admin_footer();
}
