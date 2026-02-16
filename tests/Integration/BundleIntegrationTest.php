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

namespace Playwright\Symfony\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\DependencyInjection\PlaywrightExtension;
use Playwright\Symfony\PlaywrightSymfonyBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversNothing]
class BundleIntegrationTest extends TestCase
{
    private ContainerBuilder $container;
    private PlaywrightExtension $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new PlaywrightExtension();
    }

    public function testBundleLoadsExtension(): void
    {
        $bundle = new PlaywrightSymfonyBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(PlaywrightExtension::class, $extension);
    }

    public function testExtensionLoadsWithDefaultConfiguration(): void
    {
        $config = [];
        $this->extension->load([$config], $this->container);
        // Default browser service must exist via browsers defaulting
        $this->assertTrue($this->container->hasDefinition('playwright.browser.default'));
        $this->assertTrue($this->container->hasAlias('playwright.browser'));
    }

    public function testExtensionLoadsWithClientConfigurations(): void
    {
        $config = [
            'browsers' => [
                'default' => [
                    'type' => 'chromium',
                    'headless' => true,
                ],
                'firefox_debug' => [
                    'type' => 'firefox',
                    'headless' => false,
                ],
            ],
            'default_browser' => 'firefox_debug',
        ];

        $this->extension->load([$config], $this->container);

        $this->assertTrue($this->container->hasDefinition('playwright.browser.default'));
        $this->assertTrue($this->container->hasDefinition('playwright.browser.firefox_debug'));

        $this->assertTrue($this->container->hasAlias('playwright.browser'));
        $this->assertSame('playwright.browser.firefox_debug', (string) $this->container->getAlias('playwright.browser'));
    }

    public function testExtensionSetsParameters(): void
    {
        $config = [
            'debug' => false,
            'intercepted_hosts' => ['example.com', 'test.local'],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertEquals(false, $this->container->getParameter('playwright.debug'));
        $this->assertEquals(['example.com', 'test.local'], $this->container->getParameter('playwright.intercepted_hosts'));
    }

    public function testParametersAreSetCorrectly(): void
    {
        $config = [
            'debug' => true,
            'intercepted_hosts' => ['localhost'],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertTrue($this->container->getParameter('playwright.debug'));
        $this->assertSame(['localhost'], $this->container->getParameter('playwright.intercepted_hosts'));
    }
}
