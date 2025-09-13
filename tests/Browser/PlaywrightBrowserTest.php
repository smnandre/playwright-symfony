<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Browser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PlaywrightPHP\Symfony\Browser\PlaywrightBrowser;

#[CoversClass(PlaywrightBrowser::class)]
class PlaywrightBrowserTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure a clean environment for each test
        putenv('PLAYWRIGHT_BROWSER');
        putenv('PLAYWRIGHT_HEADLESS');
    }

    public function testFromEnvironmentDefaults(): void
    {
        $browser = PlaywrightBrowser::fromEnvironment();

        self::assertSame('chromium', $browser->getBrowserType());
        self::assertTrue($browser->isHeadless());
    }

    public function testFromEnvironmentRespectsVariables(): void
    {
        putenv('PLAYWRIGHT_BROWSER=firefox');
        putenv('PLAYWRIGHT_HEADLESS=false');

        $browser = PlaywrightBrowser::fromEnvironment();

        self::assertSame('firefox', $browser->getBrowserType());
        self::assertFalse($browser->isHeadless());
    }
}
