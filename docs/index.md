# Playwright Symfony Bundle Documentation

Welcome to the Playwright Symfony Bundle documentation. This bundle enables E2E testing for Symfony applications using real browsers with in-process request handling.

## Quick Links

- **[Getting Started](getting-started.md)** - Installation, setup, and your first test
- **[Helper Reference](helpers.md)** - Complete API documentation for test helpers
- **[Configuration](configuration.md)** - Bundle configuration options
- **[Architecture](architecture.md)** - How the bundle works internally
- **[Logging](logging.md)** - Debug and trace request interception

## What is Playwright Symfony?

Playwright Symfony is a testing bundle that combines:

- **Real Browser Testing** via Playwright (Chromium, Firefox, WebKit)
- **In-Process Request Handling** via Symfony HttpKernel
- **Full Symfony Integration** with access to services, events, and state

Unlike traditional E2E testing where you need a separate web server, this bundle intercepts HTTP requests from the browser and routes them through your Symfony kernel in the same PHP process.

## Key Benefits

**No Separate Server Required**
- No need to run `symfony serve` or configure ports
- Tests boot the kernel directly

**Full Symfony Access**
- Query the database during tests
- Access services and verify event dispatch
- Set breakpoints in controllers while the browser waits

**Fast Asset Serving**
- Static files and AssetMapper assets bypass the kernel
- Optimized performance for test suites

**Real Browser Capabilities**
- Execute JavaScript
- Test AJAX interactions
- Verify CSS rendering
- Handle file uploads/downloads
- Manage cookies and sessions

## Typical Use Cases

**Complex User Interfaces**
- Single-page applications with JavaScript
- Dynamic forms with client-side validation
- Drag-and-drop interfaces

**Authentication Flows**
- Login/logout sequences
- OAuth integration
- Two-factor authentication

**E2E User Journeys**
- Multi-step checkout processes
- Registration and onboarding flows
- Admin panel workflows

**JavaScript-Heavy Features**
- AJAX data loading
- WebSocket communication
- Real-time updates

## Quick Example

```php
<?php

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class LoginTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new \App\Kernel('test', false);
    }

    public function testUserLogin(): void
    {
        // Navigate to page
        $page = $this->visit('/login');

        // Interact with page
        $page->fill('#email', 'user@example.com');
        $page->fill('#password', 'secret');
        $page->click('button[type="submit"]');

        // Assert success
        $this->assertPageContains('Dashboard');

        // Inspect intercepted request/response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

## How It Works

1. Your test calls `visit('/path')`
2. The bundle navigates a real browser to `http://localhost/path`
3. The request is intercepted via Playwright's routing API
4. The request is converted to a Symfony Request and handled by your kernel
5. The response is sent back to the browser
6. Your test continues with the rendered page

All in the same PHP process, giving you full access to Symfony internals.

## When to Use This Bundle

**Use Playwright Symfony when:**
- Testing JavaScript-heavy applications
- Verifying complex user interactions
- Testing AJAX and async operations
- Need visual regression testing
- Testing browser-specific behavior

**Use Symfony WebTestCase when:**
- Testing APIs without JavaScript
- Simple form submissions
- Speed is critical (unit/functional tests)
- No browser-specific features needed

## Getting Help

- **GitHub Issues:** https://github.com/playwright-php/playwright-symfony/issues
- **Playwright PHP Docs:** https://github.com/playwright-php/playwright

## Next Steps

Ready to get started? Head to the [Getting Started Guide](getting-started.md).
