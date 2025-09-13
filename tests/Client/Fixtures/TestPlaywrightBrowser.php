<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Client\Fixtures;

use PlaywrightPHP\Browser\BrowserContextInterface;
use PlaywrightPHP\Page\PageInterface;
use PlaywrightPHP\Symfony\Browser\PlaywrightBrowser;

class TestPlaywrightBrowser extends PlaywrightBrowser
{
    public function __construct(
        private BrowserContextInterface $context,
        private PageInterface $page,
        string $browserType = 'chromium',
        bool $headless = true,
        array $launchOptions = [],
    ) {
        parent::__construct($browserType, $headless, $launchOptions);
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
    }

    public function getContext(): ?BrowserContextInterface
    {
        return $this->context;
    }

    public function getPage(): ?PageInterface
    {
        return $this->page;
    }

    public function setupRouting(callable $routeHandler): void
    {
        $this->page->route('**/*', $routeHandler);
    }
}
