<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP - https://github.com/playwright-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Functional;

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class CookieSessionTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testSetCookieAndRetrieve(): void
    {
        $this->setCookie('test_cookie', 'test_value');
        $this->visit('/cookie');

        $this->assertPageContains('"test_cookie":"test_value"');
        self::assertSame('test_value', $this->getCookie('test_cookie'));
    }

    public function testMultipleCookies(): void
    {
        $this->setCookie('cookie1', 'value1');
        $this->setCookie('cookie2', 'value2');
        $this->setCookie('cookie3', 'value3');

        $this->visit('/cookie');

        $this->assertPageContains('"cookie1":"value1"');
        $this->assertPageContains('"cookie2":"value2"');
        $this->assertPageContains('"cookie3":"value3"');

        self::assertSame('value1', $this->getCookie('cookie1'));
        self::assertSame('value2', $this->getCookie('cookie2'));
        self::assertSame('value3', $this->getCookie('cookie3'));
    }

    public function testCookiePersistsAcrossRequests(): void
    {
        $this->setCookie('persistent', 'stays');

        // First request
        $this->visit('/cookie');
        $this->assertPageContains('"persistent":"stays"');

        // Second request - cookie should still be there
        $this->visit('/hello');
        $this->visit('/cookie');
        $this->assertPageContains('"persistent":"stays"');

        // Third request - still there
        $this->visit('/form');
        $this->visit('/cookie');
        $this->assertPageContains('"persistent":"stays"');
    }

    public function testClearCookieRemovesSpecificCookie(): void
    {
        $this->setCookie('keep', 'this');
        $this->setCookie('remove', 'this');

        $this->visit('/cookie');
        $this->assertPageContains('"keep":"this"');
        $this->assertPageContains('"remove":"this"');

        $this->clearCookie('remove');

        $this->visit('/cookie');
        $this->assertPageContains('"keep":"this"');
        self::assertSame('this', $this->getCookie('keep'));
        self::assertNull($this->getCookie('remove'));
    }

    public function testClearCookiesRemovesAllCookies(): void
    {
        $this->setCookie('cookie1', 'value1');
        $this->setCookie('cookie2', 'value2');
        $this->setCookie('cookie3', 'value3');

        $this->visit('/cookie');
        $this->assertPageContains('"cookie1":"value1"');

        $this->clearCookies();

        $this->visit('/cookie');
        $content = $this->getLastResponse()->getContent();
        self::assertIsString($content);
        self::assertSame('[]', $content);

        self::assertNull($this->getCookie('cookie1'));
        self::assertNull($this->getCookie('cookie2'));
        self::assertNull($this->getCookie('cookie3'));
    }

    public function testGetCookieReturnsNullForNonExistent(): void
    {
        self::assertNull($this->getCookie('does_not_exist'));

        $this->setCookie('exists', 'value');
        self::assertNull($this->getCookie('different_name'));
    }

    public function testCookieWithSpecialCharacters(): void
    {
        $this->setCookie('special', 'hello world!@#$%');
        $this->visit('/cookie');

        self::assertSame('hello world!@#$%', $this->getCookie('special'));
    }

    public function testSessionStorageAndRetrieval(): void
    {
        $this->visit('/session-set?key=user_id&value=12345');

        $response = $this->getLastResponse();
        self::assertSame(200, $response->getStatusCode());
        $this->assertPageContains('Session set: user_id = 12345');

        // Retrieve session value
        $this->visit('/session-get?key=user_id');
        $this->assertPageContains('Session value: 12345');
    }

    public function testSessionPersistsAcrossRequests(): void
    {
        $this->visit('/session-set?key=counter&value=1');
        $this->assertPageContains('Session set: counter = 1');

        // Navigate to different page
        $this->visit('/hello');

        // Session should still be available
        $this->visit('/session-get?key=counter');
        $this->assertPageContains('Session value: 1');
    }

    public function testSessionClearRemovesData(): void
    {
        $this->visit('/session-set?key=temp&value=data');
        $this->assertPageContains('Session set: temp = data');

        $this->visit('/session-clear');
        $this->assertPageContains('Session cleared');

        $this->visit('/session-get?key=temp');
        $this->assertPageContains('Session value: null');
    }
}
