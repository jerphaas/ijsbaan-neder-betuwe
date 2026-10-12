<?php

declare(strict_types=1);

namespace App\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/IjsbaanSponsors20261012.php';

final class IjsbaanSponsors20261012Test extends TestCase
{
    public function testPublicReadsArePinnedToTheWebsiteAndExcludeSessions(): void
    {
        $request = ijsbaan12Request(['operation' => 'public', 'path' => '/', 'cookie' => 'secret=value']);
        self::assertSame('https://ijsbaannederbetuwe.nl/', $request['url']);
        self::assertNull($request['body']);
        self::assertFalse(str_contains(implode('\n', $request['headers']), 'secret'));
    }

    public function testRejectsExternalUrlsAndTraversal(): void
    {
        $paths = ['https://example.com/', '//example.com/a.js', '/assets/../config.php', '/api/sponsor.php?action=retry'];
        foreach ($paths as $path) {
            self::assertFalse(ijsbaan12PublicPath($path));
        }
    }

    public function testCannotSendMail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12Request(['operation' => 'maintenance', 'action' => 'retry']);
    }

    public function testCannotSubmitApplications(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12Request(['operation' => 'submit', 'path' => '/api/sponsor.php']);
    }

    public function testRejectsUnapprovedOrChangedLogos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12Request([
            'operation' => 'save', 'slug' => 'de-bruin-betonwerken', 'logo' => base64_encode('wrong logo'),
        ]);
    }

    public function testCannotReadApplicationsThroughAdmin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12Request(['operation' => 'admin-read', 'path' => '/beheer/?application=123']);
    }

    public function testRejectsHeaderInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12Request([
            'operation' => 'admin-read', 'path' => '/beheer/?partners=1', 'cookie' => "x=y\r\nOther: injected",
        ]);
    }
    private function textProfile(): array
    {
        return ['name' => 'Test', 'website' => '', 'description' => '', 'edition_id' => 'opheusden-2026',
            'amount' => '250', 'tier' => 'friend', 'sort_order' => '100', 'visible' => '1', 'logo_dark' => '0',
            'logo_sha256' => '', 'scope_nonce' => str_repeat('a', 64)];
    }

    public function testExactTextOnlyProfileNeedsNoFabricatedLogo(): void
    {
        $json = json_encode($this->textProfile(), JSON_THROW_ON_ERROR);
        $fields = ijsbaan12ProfilePayload($json, hash('sha256', $json), '');
        self::assertSame('Test', $fields['name']);
        self::assertArrayNotHasKey('scope_nonce', $fields);
        self::assertArrayNotHasKey('profile_id', $fields);
    }

    public function testChangedContributionCannotPassThePinnedManifest(): void
    {
        $profile = $this->textProfile();
        $expected = hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR));
        $profile['amount'] = '251';
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12ProfilePayload(json_encode($profile, JSON_THROW_ON_ERROR), $expected, '');
    }

    public function testUnexpectedLogoCannotBeAddedToTextOnlySponsor(): void
    {
        $json = json_encode($this->textProfile(), JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        ijsbaan12ProfilePayload($json, hash('sha256', $json), 'different logo');
    }

    public function testOnlySixteenNewSponsorsAreWhitelisted(): void
    {
        self::assertCount(16, ijsbaan12Profiles());
        self::assertArrayNotHasKey('combinatie-mauritz', ijsbaan12Profiles());
    }

}
