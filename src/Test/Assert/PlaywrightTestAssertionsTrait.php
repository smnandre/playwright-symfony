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

/**
 * @author Simon André <smn.andre@gmail.com>
 */
trait PlaywrightTestAssertionsTrait
{
    protected function assertPageContains(string $text): void
    {
        $content = $this->page->content();
        $this->assertStringContainsString($text, $content);
    }

    protected function assertPageNotContains(string $text): void
    {
        $content = $this->page->content();
        $this->assertStringNotContainsString($text, $content);
    }

    protected function assertSelectorExists(string $selector): void
    {
        $element = $this->page->locator($selector);
        $this->assertNotNull($element, "Selector '$selector' not found");
    }

    protected function assertSelectorNotExists(string $selector): void
    {
        $count = $this->page->locator($selector)->count();
        $this->assertSame(0, $count, "Selector '$selector' should not exist");
    }

    protected function click(string $selector): void
    {
        $this->page->click($selector);
    }

    protected function fill(string $selector, string $value): void
    {
        $this->page->fill($selector, $value);
    }

    protected function select(string $selector, string $value): void
    {
        $this->page->selectOption($selector, $value);
    }

    protected function check(string $selector): void
    {
        $this->page->check($selector);
    }

    protected function uncheck(string $selector): void
    {
        $this->page->uncheck($selector);
    }

    protected function waitForSelector(string $selector, array $options = []): void
    {
        $this->page->waitForSelector($selector, $options);
    }

    protected function screenshot(string $path): void
    {
        $this->page->screenshot(['path' => $path]);
    }
}
