<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\Browser;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Browser\PlaywrightBrowser;

class PlaywrightBrowserTest extends TestCase
{
    public function testFromEnvironmentDefaultsToChromiumOnInvalidBrowser(): void
    {
        putenv('PLAYWRIGHT_BROWSER=invalid');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = PlaywrightBrowser::fromEnvironment();

        $this->assertSame('chromium', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }

    public function testFromEnvironmentRespectsValidBrowserTypesAndHeadlessFlag(): void
    {
        putenv('PLAYWRIGHT_BROWSER=firefox');
        putenv('PLAYWRIGHT_HEADLESS=false');

        $browser = PlaywrightBrowser::fromEnvironment();

        $this->assertSame('firefox', $browser->getBrowserType());
        $this->assertFalse($browser->isHeadless());

        putenv('PLAYWRIGHT_BROWSER=webkit');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = PlaywrightBrowser::fromEnvironment();

        $this->assertSame('webkit', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }
}

