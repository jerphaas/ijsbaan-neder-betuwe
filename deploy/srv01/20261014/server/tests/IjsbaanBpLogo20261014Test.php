<?php

declare(strict_types=1);

namespace App\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/IjsbaanBpLogo20261014.php';

final class IjsbaanBpLogo20261014Test extends TestCase
{
    public function testPublicReadsArePinnedToTheWebsiteAndExcludeSessions(): void
    {
        $request = ijsbaan14Request(['operation' => 'public', 'path' => '/', 'cookie' => 'secret=value']);
        self::assertSame('https://ijsbaannederbetuwe.nl/', $request['url']);
        self::assertNull($request['body']);
        self::assertFalse(str_contains(implode('\n', $request['headers']), 'secret'));
    }

    public function testRejectsExternalUrlsAndTraversal(): void
    {
        $paths = ['https://example.com/', '//example.com/a.js', '/assets/../config.php', '/api/sponsor.php?action=retry'];
        foreach ($paths as $path) {
            self::assertFalse(ijsbaan14PublicPath($path));
        }
    }

    public function testCannotSendMail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan14Request(['operation' => 'maintenance', 'action' => 'retry']);
    }

    public function testCannotSubmitApplications(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan14Request(['operation' => 'submit', 'path' => '/api/sponsor.php']);
    }

    public function testRejectsUnapprovedOrChangedLogos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan14Request([
            'operation' => 'save', 'slug' => 'de-bruin-betonwerken', 'logo' => base64_encode('wrong logo'),
        ]);
    }

    public function testCannotReadApplicationsThroughAdmin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan14Request(['operation' => 'admin-read', 'path' => '/beheer/?application=123']);
    }

    public function testRejectsHeaderInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ijsbaan14Request([
            'operation' => 'admin-read', 'path' => '/beheer/?partners=1', 'cookie' => "x=y\r\nOther: injected",
        ]);
    }
}
