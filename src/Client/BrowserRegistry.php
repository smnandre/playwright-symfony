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

namespace Playwright\Symfony\Client;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;
use Playwright\Playwright;

/**
 * Registry for managing Playwright browser lifecycle, configuration, and shared instances.
 *
 * This class acts as a factory and lifecycle manager for Playwright browser contexts and pages.
 * It handles browser startup, shutdown, context recreation, and provides a centralized point
 * for browser configuration (browser type, headless mode, launch options).
 *
 * Primary role in architecture:
 * - Used by PlaywrightTestCase to share a single browser instance across multiple tests
 * - Provides browser context and page instances to PlaywrightKernelClient
 * - Manages browser lifecycle hooks (start, stop, restartContext)
 * - Configures request routing for the kernel-based interception flow
 *
 * Key responsibilities:
 * - Start/stop Playwright browsers (chromium, firefox, webkit)
 * - Create and manage browser contexts and pages
 * - Restart contexts between tests for isolation while reusing browser instance (performance)
 * - Set up routing callbacks for request interception via setupRouting()
 * - Read configuration from environment variables (PLAYWRIGHT_BROWSER, PLAYWRIGHT_HEADLESS)
 *
 * Usage:
 * - Typically instantiated via BrowserRegistry::fromEnvironment() in PlaywrightTestCase
 * - Browser instance is shared across tests in the same test class (static $sharedBrowser)
 * - Context is restarted between tests to ensure test isolation
 *
 * This is NOT a browser itself - it's a registry/manager that creates and holds browser instances.
 *
 * @internal Used by PlaywrightTestCase and PlaywrightKernelClient
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class BrowserRegistry
{
    private ?BrowserContextInterface $context = null;
    private ?PageInterface $page = null;

    /**
     * @param array<string, mixed> $launchOptions
     */
    public function __construct(
        private readonly string $browserType = 'chromium',
        private readonly bool $headless = true,
        private readonly array $launchOptions = [],
    ) {
    }

    public function start(): void
    {
        if (null !== $this->context) {
            return;
        }

        $options = array_merge(
            ['headless' => $this->headless],
            $this->launchOptions
        );

        $this->context = match ($this->browserType) {
            'firefox' => Playwright::firefox($options),
            'webkit' => Playwright::webkit($options),
            default => Playwright::chromium($options),
        };

        $this->page = $this->context->newPage();
    }

    public function equals(self $other): bool
    {
        return $this->browserType === $other->browserType
            && $this->headless === $other->headless
            && $this->launchOptions === $other->launchOptions;
    }

    public function stop(): void
    {
        $this->context?->close();
        $this->context = null;
        $this->page = null;
    }

    public function restartContext(): void
    {
        if (null !== $this->page) {
            $this->page->close();
            $this->page = null;
        }
        if (null !== $this->context) {
            $this->context->close();
            $this->context = null;
        }
        $this->start();
    }

    public function getContext(): ?BrowserContextInterface
    {
        $this->ensureStarted();

        return $this->context;
    }

    public function getPage(): ?PageInterface
    {
        $this->ensureStarted();

        return $this->page;
    }

    public function setupRouting(callable $routeHandler): void
    {
        $this->ensureStarted();
        if (null !== $this->page) {
            $this->page->route('**/*', $routeHandler);
        }
    }

    public function isHeadless(): bool
    {
        return $this->headless;
    }

    public function getBrowserType(): string
    {
        return $this->browserType;
    }

    public static function fromEnvironment(): self
    {
        /** @var string|null $env */
        $env = $_ENV['PLAYWRIGHT_BROWSER'] ?? $_SERVER['PLAYWRIGHT_BROWSER'] ?? getenv('PLAYWRIGHT_BROWSER');
        $browserType = strtolower((string) $env);
        if (!in_array($browserType, ['chromium', 'firefox', 'webkit'], true)) {
            $browserType = 'chromium';
        }

        $headless = 'false' !== ($_ENV['PLAYWRIGHT_HEADLESS'] ?? $_SERVER['PLAYWRIGHT_HEADLESS'] ?? getenv('PLAYWRIGHT_HEADLESS'));

        return new self($browserType, $headless);
    }

    private function ensureStarted(): void
    {
        if (null === $this->context) {
            $this->start();
        }
    }
}
