<?php
declare(strict_types=1);
require __DIR__ . '/../server/partners.php';
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
foreach (['javascript:alert(1)', 'data:text/html,test', 'https://user:password@example.com', "https://example.com\n"] as $url) check(!partner_url_valid($url), 'Unsafe URL accepted');
check(partner_url_valid('https://example.com/bedrijf?x=1&y=2'), 'Normal URL rejected');
$profile = ['id'=>'example','name'=>'Bedrijf <script>alert(1)</script> & Zoon', 'website'=>'javascript:alert(1)', 'description'=>'<b>Test</b>', 'tier'=>'main', 'logo_asset'=>'../../config.json', 'logo_file'=>null, 'logo_dark'=>0, 'revision'=>1];
$html = partner_tile($profile);
check(!str_contains($html, '<script>') && !str_contains($html, 'javascript:') && !str_contains($html, 'config.json') && str_contains($html, '&lt;script&gt;'), 'Untrusted profile data not escaped');
$profile['name'] = 'Voorbeeld'; $profile['website'] = 'https://example.com/';
$parts = partner_fragments([$profile]);
check(str_contains($parts['strip'], 'ONZE SPONSORS') && str_contains($parts['strip'], 'Voorbeeld'), 'Sponsor slider missing');
check(str_contains($parts['grid'], 'Voorbeeld') && str_contains($parts['grid'], 'noopener sponsored'), 'Public profile/link missing');
$profile['tier'] = 'friend';
check(str_contains(partner_fragments([$profile])['strip'], 'Voorbeeld'), 'Friend missing from slider');
$profiles = [];
for ($i = 0; $i < 43; $i++) {
    $row = $profile; $row['name'] = 'Sponsor nummer ' . $i;
    $row['tier'] = $i < 4 ? 'main' : ($i % 2 ? 'friend' : 'partner');
    $profiles[] = $row;
}
$parts = partner_fragments($profiles);
foreach (['strip', 'grid'] as $part) {
    check(substr_count($parts[$part], 'class="partner-item"') === 43, 'Not all 43 sponsors in ' . $part);
    foreach ($profiles as $row) check(str_contains($parts[$part], '>' . $row['name'] . '<'), $row['name'] . ' missing from ' . $part);
}
check(partner_fragments([])['strip'] === '', 'Empty slider rendered');
echo "PASS: safe URLs, escaped profile data, safe logos and all 43 sponsors of every tier in both slider and wall.\n";
