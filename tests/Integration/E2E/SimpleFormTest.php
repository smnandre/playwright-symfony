<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Integration\E2E;

use PlaywrightPHP\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use PlaywrightPHP\Symfony\Test\PlaywrightTestCase;
use PlaywrightPHP\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class SimpleFormTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testFormRouteExists(): void
    {
        // Just try to visit the form page to see if route exists
        $this->visit('/form');

        // If we get here without error, the route exists
        $this->assertPageContains('<form');
    }
}
