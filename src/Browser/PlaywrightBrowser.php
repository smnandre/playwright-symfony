<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Browser;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;
use Playwright\Playwright;

/**
 * Handles Playwright browser lifecycle and configuration.
 *
 * Responsibilities:
 * - Browser startup/shutdown
 * - Page creation and management
 * - Browser configuration (headless, type, etc.)
 * - Routing setup for request interception
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class PlaywrightBrowser
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

    public function stop(): void
    {
        $this->context?->close();
        $this->context = null;
        $this->page = null;
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
        $browserType = strtolower((string) getenv('PLAYWRIGHT_BROWSER'));
        if (!in_array($browserType, ['chromium', 'firefox', 'webkit'], true)) {
            $browserType = 'chromium';
        }

        $headless = 'false' !== getenv('PLAYWRIGHT_HEADLESS');

        return new self($browserType, $headless);
    }

    private function ensureStarted(): void
    {
        if (null === $this->context) {
            $this->start();
        }
    }
}
