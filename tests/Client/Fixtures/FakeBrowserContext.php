<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Client\Fixtures;

use PlaywrightPHP\Browser\BrowserContextInterface;
use PlaywrightPHP\Browser\StorageState;
use PlaywrightPHP\Network\NetworkThrottling;
use PlaywrightPHP\Page\PageInterface;

class FakeBrowserContext implements BrowserContextInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $cookies = [];
    /** @var array<int, PageInterface> */
    private array $pages = [];
    private ?string $envValue = null;

    public function addCookies(array $cookies): void
    {
        foreach ($cookies as $cookie) {
            $this->cookies = array_values(array_filter(
                $this->cookies,
                static fn ($c) => ($c['name'] ?? null) !== ($cookie['name'] ?? null)
            ));
            $this->cookies[] = $cookie;
        }
    }

    public function addInitScript(string $script): void
    {
    }

    public function clearCookies(): void
    {
        $this->cookies = [];
    }

    public function clearPermissions(): void
    {
    }

    public function close(): void
    {
    }

    public function cookies(?array $urls = null): array
    {
        return $this->cookies;
    }

    public function exposeBinding(string $name, callable $callback): void
    {
    }

    public function exposeFunction(string $name, callable $callback): void
    {
    }

    public function grantPermissions(array $permissions): void
    {
    }

    public function newPage(array $options = []): PageInterface
    {
        $page = new FakePage($this);
        $this->pages[] = $page;

        return $page;
    }

    public function pages(): array
    {
        return $this->pages;
    }

    public function storageState(?string $path = null): array
    {
        return ['cookies' => $this->cookies, 'origins' => []];
    }

    public function getStorageState(): StorageState
    {
        return new StorageState(cookies: [], origins: []);
    }

    public function setStorageState(StorageState $storageState): void
    {
    }

    public function saveStorageState(string $filePath): void
    {
    }

    public function loadStorageState(string $filePath): void
    {
    }

    public function route(string $url, callable $handler): void
    {
    }

    public function unroute(string $url, ?callable $handler = null): void
    {
    }

    public function getEnv(string $name): ?string
    {
        return $this->envValue;
    }

    public function startTracing(PageInterface $page, array $options = []): void
    {
    }

    public function stopTracing(PageInterface $page, string $path): void
    {
    }

    public function setNetworkThrottling(NetworkThrottling $throttling): void
    {
    }

    public function disableNetworkThrottling(): void
    {
    }
}
