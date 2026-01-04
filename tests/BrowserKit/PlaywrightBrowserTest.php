<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\BrowserKit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\BrowserKit\CookieJarSync;
use Playwright\Symfony\BrowserKit\PlaywrightBrowser as BrowserKitClient;
use Playwright\Symfony\BrowserKit\ResponseMapper;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;

#[CoversClass(BrowserKitClient::class)]
#[CoversClass(CookieJarSync::class)]
#[CoversClass(ResponseMapper::class)]
final class PlaywrightBrowserTest extends TestCase
{
    public function testFactorySeedsCookieJarAndNavigates(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'auth', 'value' => '1', 'domain' => 'example.test', 'path' => '/'],
        ]);

        $client = BrowserKitClient::fromContext($context);
        $client->request('GET', 'http://example.test/foo');

        $page = $client->getPage();
        self::assertSame('http://example.test/foo', $page?->url());

        $client->request(
            'POST',
            'http://example.test/form',
            ['field' => 'value'],
            [],
            ['HTTP_X_CUSTOM' => '1', 'PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'pw']
        );

        self::assertSame(200, $client->getLastResponse()?->getStatusCode());
        self::assertSame('1', $context->extraHTTPHeaders['x-custom'] ?? null);
        self::assertSame([
            'username' => 'user',
            'password' => 'pw',
        ], $context->httpCredentials);
    }
}
