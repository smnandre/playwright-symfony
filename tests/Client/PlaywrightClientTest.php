<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PlaywrightPHP\Symfony\Client\PlaywrightClient;
use PlaywrightPHP\Symfony\Client\RequestConverter;
use PlaywrightPHP\Symfony\Client\ResponseConverter;
use PlaywrightPHP\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use PlaywrightPHP\Symfony\Tests\Client\Fixtures\FakePage;
use PlaywrightPHP\Symfony\Tests\Client\Fixtures\TestPlaywrightBrowser;
use PlaywrightPHP\Symfony\Tests\Fixtures\MockRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PlaywrightClient::class)]
#[CoversClass(RequestConverter::class)]
#[CoversClass(ResponseConverter::class)]
class PlaywrightClientTest extends TestCase
{
    private TestPlaywrightBrowser $browser;
    private FakeBrowserContext $context;
    private FakePage $page;

    protected function setUp(): void
    {
        $this->context = new FakeBrowserContext();
        $this->page = new FakePage($this->context);
        $this->browser = new TestPlaywrightBrowser($this->context, $this->page);
    }

    public function testVisitNavigatesAndReturnsPage(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $page = $client->visit('/hello');

        self::assertSame($this->page, $page);
        self::assertSame('http://localhost/hello', $this->page->lastGoto);
    }

    public function testInterceptedRequestHandledThroughKernel(): void
    {
        $seen = (object) [
            'before' => null,
            'after' => null,
        ];

        $kernel = new class implements HttpKernelInterface {
            public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
            {
                return new SymfonyResponse('intercepted', 201, ['x-k' => 'v']);
            }
        };

        $hookReceiver = new class($seen) {
            public function __construct(private object $seen)
            {
            }

            public function beforeRequest(SymfonyRequest $request): void
            {
                $this->seen->before = $request;
            }

            public function afterResponse(SymfonyResponse $response): void
            {
                $this->seen->after = $response;
            }
        };

        $client = new PlaywrightClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost', '127.0.0.1', 'testapp.local'],
            $hookReceiver,
        );

        // Trigger the routing setup
        $client->visit('/anything');

        // Simulate a request from Playwright targeting an intercepted host
        $mock = new MockRequest(url: 'http://localhost/path?x=1', method: 'GET');
        $route = $this->page->triggerRequest($mock);

        self::assertTrue($route->fulfilled, 'Route should be fulfilled when intercepted');
        self::assertNotNull($route->fulfilledOptions);
        self::assertSame(201, $route->fulfilledOptions['status'] ?? null);

        // Hooks should have been called with appropriate objects
        self::assertInstanceOf(SymfonyRequest::class, $seen->before);
        self::assertInstanceOf(SymfonyResponse::class, $seen->after);
    }

    public function testNonInterceptedRequestContinues(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost', '127.0.0.1', 'testapp.local'],
        );

        $client->visit('/anything');

        // Simulate external host -> should continue
        $mock = new MockRequest(url: 'http://example.com/page', method: 'GET');
        $route = $this->page->triggerRequest($mock);

        self::assertTrue($route->continued);
        self::assertFalse($route->fulfilled);
    }

    public function testCookieHelpersWorkWithContext(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $client->setCookie('foo', 'bar');
        self::assertSame('bar', $client->getCookie('foo'));

        $client->clearCookie('foo');
        // addCookies with empty value is how clearCookie is implemented; getCookie returns the current value
        self::assertSame('', $client->getCookie('foo'));

        $client->clearCookies();
        self::assertNull($client->getCookie('foo'));
    }
}
