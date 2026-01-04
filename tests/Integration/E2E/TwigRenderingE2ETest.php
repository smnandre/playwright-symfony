<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\Integration\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;

final class TwigRenderingE2ETest extends PlaywrightTestCase
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
