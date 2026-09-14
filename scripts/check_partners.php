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
check(str_contains($parts['strip'], 'Onze') === false && str_contains($parts['strip'], 'Hoofdsponsors'), 'Main sponsor slot missing');
check(str_contains($parts['grid'], 'Voorbeeld') && str_contains($parts['grid'], 'noopener sponsored'), 'Public profile/link missing');
$profile['tier'] = 'friend';
check(partner_fragments([$profile])['strip'] === '', 'Non-main sponsor entered main strip');
echo "PASS: URL safety, escaped names/descriptions, safe logo paths and main sponsor separation.\n";
