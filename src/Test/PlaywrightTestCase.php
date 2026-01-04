<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Test;

use Playwright\Page\PageInterface;
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\PlaywrightClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Simplified test case that provides seamless DX while using the new component architecture.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class PlaywrightTestCase extends KernelTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected PlaywrightBrowser $browser;
    protected PlaywrightClient $client;
    protected string $baseUrl = 'http://localhost';
    protected LoggerInterface $playwrightLogger;
    protected bool $debugLogging = false;

    protected function setUp(): void
    {
        $this->playwrightLogger = new NullLogger();
        $this->debugLogging = false;

        // Skip E2E browser tests unless explicitly enabled
        if (getenv('PLAYWRIGHT_E2E') !== '1') {
            $this->markTestSkipped('Playwright E2E tests are disabled. Set PLAYWRIGHT_E2E=1 to enable.');
        }

        parent::setUp();
        self::bootKernel();

        $this->baseUrl = $this->resolveBaseUrl();
        $this->debugLogging = $this->resolveDebugLogging();
        $this->playwrightLogger = $this->resolveLogger();

        $this->browser = PlaywrightBrowser::fromEnvironment();
        $this->browser->start();

        if ($this->debugLogging) {
            $this->playwrightLogger->info('Started Playwright browser', [
                'browser' => $this->browser->getBrowserType(),
                'headless' => $this->browser->isHeadless(),
            ]);
        }

        $interceptedHosts = $this->loadInterceptedHosts();

        $assetServer = $this->resolveAssetServer();

        $this->client = new PlaywrightClient(
            $this->browser,
            self::$kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            $interceptedHosts,
            $this,
            $assetServer,
            $this->baseUrl,
            $this->playwrightLogger,
            $this->debugLogging,
        );
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->browser)) {
                if ($this->debugLogging) {
                    $this->playwrightLogger->info('Stopping Playwright browser', [
                        'browser' => $this->browser->getBrowserType(),
                    ]);
                }
                $this->browser->stop();
            }
        } catch (\Throwable $e) {
            // Ignore browser stop errors during teardown
        }

        $this->restoreExceptionHandlers();
        parent::tearDown();
    }

    private function restoreExceptionHandlers(): void
    {
        try {
            // Symfony's ErrorHandler and PHPUnit can push multiple exception handlers onto the stack.
            // A single `restore_exception_handler()` call only removes the topmost one.
            // This loop ensures all handlers pushed during test execution are popped off,
            // preventing interference with subsequent tests or other Symfony components.
            $maxIterations = 10; // Safeguard to prevent infinite loops if an unexpected handler persists
            $iterations = 0;

            while ($iterations < $maxIterations) {
                // Push a no-op handler to get the previous one without triggering an error
                $previousHandler = set_exception_handler(static fn () => null);
                restore_exception_handler(); // Remove the no-op handler

                if (null === $previousHandler) {
                    // No more custom handlers, only PHP's default is left
                    break;
                }

                // Remove the previously found custom handler
                restore_exception_handler();
                ++$iterations;
            }
        } catch (\Throwable $e) {
            // If exception handler restoration fails, continue with teardown.
            // This prevents test failures due to issues in handler cleanup itself.
        }
    }

    // Clean delegate methods for seamless DX

    protected function visit(string $path): PageInterface
    {
        return $this->client->visit($path);
    }

    protected function getPage(): PageInterface
    {
        return $this->client->getPage();
    }

    // Property accessor for the trait
    public function __get(string $name): mixed
    {
        if ('page' === $name) {
            return $this->getPage();
        }
        throw new \InvalidArgumentException("Property '$name' does not exist");
    }

    protected function setCookie(string $name, string $value, array $options = []): void
    {
        $this->client->setCookie($name, $value, $options);
    }

    protected function getCookie(string $name, ?string $url = null): ?string
    {
        return $this->client->getCookie($name, $url);
    }

    protected function clearCookies(): void
    {
        $this->client->clearCookies();
    }

    protected function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        $this->client->clearCookie($name, $domain, $path);
    }

    protected function authenticate(string $identifier = 'user', array $context = []): void
    {
        $this->client->authenticate($identifier, $context);
    }

    protected function logout(): void
    {
        $this->client->logout();
    }

    protected function getLastRequest(): ?SymfonyRequest
    {
        return $this->client->getLastSymfonyRequest();
    }

    protected function getLastResponse(): ?SymfonyResponse
    {
        return $this->client->getLastSymfonyResponse();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // Hooks for customization
    public function beforeRequest(SymfonyRequest $request): void
    {
        // Override to add custom logic before each request
    }

    public function afterResponse(SymfonyResponse $response): void
    {
        // Override to add custom logic after each response
    }

    protected function loadFixtures(array $fixtures): void
    {
        // Override to load fixtures
    }

    private function resolveLogger(): LoggerInterface
    {
        $container = self::$kernel->getContainer();

        $logger = $this->findLoggerInContainer($container);
        if (null !== $logger) {
            return $logger;
        }

        if ($container->has('test.service_container')) {
            /** @var \Symfony\Component\DependencyInjection\ContainerInterface $testContainer */
            $testContainer = $container->get('test.service_container');
            $logger = $this->findLoggerInContainer($testContainer);
            if (null !== $logger) {
                return $logger;
            }
        }

        return new NullLogger();
    }

    private function findLoggerInContainer(object $container): ?LoggerInterface
    {
        if (method_exists($container, 'has') && $container->has('monolog.logger.playwright')) {
            $candidate = $container->get('monolog.logger.playwright');
            if ($candidate instanceof LoggerInterface) {
                return $candidate;
            }
        }

        if (method_exists($container, 'has') && $container->has('logger')) {
            $candidate = $container->get('logger');
            if ($candidate instanceof LoggerInterface) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveDebugLogging(): bool
    {
        $env = getenv('PLAYWRIGHT_VERBOSE');
        if (false !== $env && '' !== $env) {
            return !in_array(strtolower((string) $env), ['0', 'false', 'off'], true);
        }

        $container = self::$kernel->getContainer();
        if ($container->hasParameter('playwright.debug_logging')) {
            return (bool) $container->getParameter('playwright.debug_logging');
        }

        if ($container->has('test.service_container')) {
            /** @var \Symfony\Component\DependencyInjection\ContainerInterface $testContainer */
            $testContainer = $container->get('test.service_container');
            if ($testContainer->hasParameter('playwright.debug_logging')) {
                return (bool) $testContainer->getParameter('playwright.debug_logging');
            }
        }

        return false;
    }

    private function resolveBaseUrl(): string
    {
        $default = 'http://localhost';
        $container = self::$kernel->getContainer();

        if ($container->hasParameter('playwright.base_url')) {
            return (string) $container->getParameter('playwright.base_url');
        }

        if ($container->has('test.service_container')) {
            /** @var \Symfony\Component\DependencyInjection\ContainerInterface $testContainer */
            $testContainer = $container->get('test.service_container');
            if ($testContainer->hasParameter('playwright.base_url')) {
                return (string) $testContainer->getParameter('playwright.base_url');
            }
        }

        return $default;
    }

    private function loadInterceptedHosts(): array
    {
        $container = self::$kernel->getContainer();
        $defaultHosts = ['localhost', '127.0.0.1', 'testapp.local'];

        if ($container->hasParameter('playwright.intercepted_hosts')) {
            $hosts = $container->getParameter('playwright.intercepted_hosts');

            return empty($hosts) ? $defaultHosts : $hosts;
        }

        return $defaultHosts;
    }

    private function resolveAssetServer(): ?AssetServer
    {
        $container = self::$kernel->getContainer();

        if ($container->has(AssetServer::class)) {
            return $container->get(AssetServer::class);
        }

        if ($container->has('test.service_container')) {
            /** @var \Symfony\Component\DependencyInjection\ContainerInterface $testContainer */
            $testContainer = $container->get('test.service_container');
            if ($testContainer->has(AssetServer::class)) {
                return $testContainer->get(AssetServer::class);
            }
        }

        return null;
    }
}
