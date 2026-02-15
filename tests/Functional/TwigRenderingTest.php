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

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;

final class TwigRenderingTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): \Symfony\Component\HttpKernel\KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testTwigTemplateRendersWithVariables(): void
    {
        $this->visit('/twig');

        // Check title variable rendered
        $this->assertPageContains('Twig Template Demo');

        // Check message variable rendered
        $this->assertPageContains('This page is rendered using Twig templates!');

        // Check HTML structure
        $this->assertSelectorExists('h1.heading');
        $this->assertSelectorExists('p.message');
        $this->assertSelectorExists('div.styled-box');
    }
}
