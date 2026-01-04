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

namespace Playwright\Symfony\Tests\Integration\E2E;

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class FormValidationE2ETest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testFormFillAndSubmitWithValidData(): void
    {
        $page = $this->visit('/form');

        // Fill form with valid data
        $page->fill('#name', 'John Doe');
        $page->click('button[type="submit"]');

        // Assert success message
        $this->assertPageContains('Hello John Doe');

        $response = $this->getLastResponse();
        self::assertSame(200, $response->getStatusCode());
    }

    public function testFormSubmitWithEmptyFieldShowsValidationError(): void
    {
        $page = $this->visit('/form');

        // Try to submit empty form
        $page->fill('#name', '');
        $page->click('button[type="submit"]');

        // Should see validation error
        $this->assertPageContains('Name is required');

        $response = $this->getLastResponse();
        self::assertSame(400, $response->getStatusCode());
    }

    public function testFormFieldsAreAccessible(): void
    {
        $page = $this->visit('/form');

        // Verify form elements exist
        $this->assertSelectorExists('form[method="POST"]');
        $this->assertSelectorExists('#name');
        $this->assertSelectorExists('button[type="submit"]');
    }
}
