<?php

declare(strict_types=1);

namespace App\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/IjsbaanSponsors20260929.php';

final class IjsbaanSponsors20260929Test extends TestCase
{
    public function testPublicReadsArePinnedToTheWebsiteAndExcludeSessions(): void
    {
        $request = ijsbaan29Request(['operation' => 'public', 'path' => '/', 'cookie' => 'secret=value']);
        self::assertSame('https://ijsbaannederbetuwe.nl/', $request['url']);
        self::assertNull($request['body']);
        self::assertFalse(str_contains(implode('\n', $request['headers']), 'secret'));
    }

    public function testRejectsExternalUrlsAndTraversal(): void
    {
        $paths = ['https://example.com/', '//example.com/a.js', '/assets/../config.php', '/api/sponsor.php?action=retry'];
        foreach ($paths as $path) {
            self::assertFalse(ijsbaan29PublicPath($path));
        }
    }

    public function testCannotSendMail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan29Request(['operation' => 'maintenance', 'action' => 'retry']);
    }

    public function testCannotSubmitApplications(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan29Request(['operation' => 'submit', 'path' => '/api/sponsor.php']);
    }

    public function testRejectsUnapprovedOrChangedLogos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan29Request([
            'operation' => 'save', 'slug' => 'de-bruin-betonwerken', 'logo' => base64_encode('wrong logo'),
        ]);
    }

    public function testCannotReadApplicationsThroughAdmin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan29Request(['operation' => 'admin-read', 'path' => '/beheer/?application=123']);
    }

    public function testRejectsHeaderInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan29Request([
            'operation' => 'admin-read', 'path' => '/beheer/?partners=1', 'cookie' => "x=y\r\nOther: injected",
        ]);
    }
}
