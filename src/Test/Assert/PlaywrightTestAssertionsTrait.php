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

namespace Playwright\Symfony\Test\Assert;

use Playwright\Page\PageInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
trait PlaywrightTestAssertionsTrait
{
    protected function assertPageContains(string $text): void
    {
        $content = $this->getPage()->content() ?? '';
        $this->assertStringContainsString($text, $content);
    }

    protected function assertPageNotContains(string $text): void
    {
        $content = $this->getPage()->content() ?? '';
        $this->assertStringNotContainsString($text, $content);
    }

    protected function assertSelectorExists(string $selector): void
    {
        $count = $this->getPage()->locator($selector)->count();
        $this->assertGreaterThan(0, $count, "Selector '$selector' not found");
    }

    protected function assertSelectorNotExists(string $selector): void
    {
        $count = $this->getPage()->locator($selector)->count();
        $this->assertSame(0, $count, "Selector '$selector' should not exist");
    }

    protected function click(string $selector): void
    {
        $this->getPage()->locator($selector)->click();
    }

    protected function fill(string $selector, string $value): void
    {
        $this->getPage()->locator($selector)->fill($value);
    }

    protected function select(string $selector, string $value): void
    {
        $this->getPage()->locator($selector)->selectOption($value);
    }

    protected function check(string $selector): void
    {
        $this->getPage()->locator($selector)->check();
    }

    protected function uncheck(string $selector): void
    {
        $this->getPage()->locator($selector)->uncheck();
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function waitForSelector(string $selector, array $options = []): void
    {
        $this->getPage()->waitForSelector($selector, $options);
    }

    protected function screenshot(string $path): void
    {
        $this->getPage()->screenshot($path);
    }

    /**
     * Must be implemented by the class using this trait.
     */
    abstract protected function getPage(): PageInterface;
}
