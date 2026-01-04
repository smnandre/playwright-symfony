# Getting Started

This guide will help you install and configure the Playwright Symfony Bundle in your Symfony application.

## Prerequisites

Before installing, ensure you have:

- **PHP 8.3 or higher**
- **Symfony 7.0+ or 8.0+**
- **Node.js 18 or higher** (required for Playwright)
- **Composer** for dependency management

## Installation

### Step 1: Install the Bundle

Install the bundle via Composer:

```bash
composer require --dev playwright-php/playwright-symfony
```

The bundle will automatically register itself in `config/bundles.php` for the `test` environment:

```php
<?php

return [
    // ... other bundles
    Playwright\Symfony\PlaywrightSymfonyBundle::class => ['test' => true],
];
```

### Step 2: Install Playwright Browsers

Playwright requires browser binaries to be installed. Run:

```bash
# Install all browsers (Chromium, Firefox, WebKit)
npx playwright install

# Or install only Chromium (recommended for CI)
npx playwright install chromium
```

This downloads the browser binaries needed for testing. You only need to do this once per environment.

## Basic Configuration

The bundle works with sensible defaults, but you can customize it if needed.

### Optional: Create Configuration File

Create `config/packages/test/playwright.yaml`:

```yaml
playwright:
    # Base URL for your application
    base_url: 'http://localhost'

    # Hosts to intercept (handled by Symfony kernel)
    intercepted_hosts:
        - 'localhost'
        - '127.0.0.1'

    # Enable debug logging
    debug_logging: false
```

See [Configuration Reference](configuration.md) for all available options.

## Creating Your First Test

### Step 1: Create a Test Base Class

Create a base class that all your E2E tests will extend:

```php
<?php
// tests/E2E/E2ETest.php

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class E2ETest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new \App\Kernel('test', false);
    }
}
```

### Step 2: Write Your First Test

Create a simple test to verify everything works:

```php
<?php
// tests/E2E/HomepageTest.php

namespace App\Tests\E2E;

class HomepageTest extends E2ETest
{
    public function testHomepageLoads(): void
    {
        // Navigate to homepage
        $page = $this->visit('/');

        // Verify page title
        $title = $page->title();
        $this->assertStringContainsString('Welcome', $title);

        // Verify page content
        $this->assertPageContains('Home');

        // Inspect the response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

### Step 3: Run Your Test

Run the test with the `PLAYWRIGHT_E2E=1` environment variable:

```bash
PLAYWRIGHT_E2E=1 vendor/bin/phpunit tests/E2E/HomepageTest.php
```

**Important:** Tests are skipped by default unless `PLAYWRIGHT_E2E=1` is set. This prevents E2E tests from running in your regular test suite.

## Running Tests

### Basic Usage

```bash
# Run all E2E tests
PLAYWRIGHT_E2E=1 vendor/bin/phpunit tests/E2E

# Run specific test file
PLAYWRIGHT_E2E=1 vendor/bin/phpunit tests/E2E/LoginTest.php

# Run specific test method
PLAYWRIGHT_E2E=1 vendor/bin/phpunit --filter testUserLogin tests/E2E
```

### Debugging with Visible Browser

Run tests with a visible browser window to see what's happening:

```bash
PLAYWRIGHT_E2E=1 PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit tests/E2E
```

This opens the browser window so you can watch the test execute in real-time.

### Testing with Different Browsers

```bash
# Test with Firefox
PLAYWRIGHT_E2E=1 PLAYWRIGHT_BROWSER=firefox vendor/bin/phpunit tests/E2E

# Test with WebKit (Safari engine)
PLAYWRIGHT_E2E=1 PLAYWRIGHT_BROWSER=webkit vendor/bin/phpunit tests/E2E

# Test with Chromium (default)
PLAYWRIGHT_E2E=1 PLAYWRIGHT_BROWSER=chromium vendor/bin/phpunit tests/E2E
```

### Enable Verbose Logging

See detailed logs of request interception:

```bash
PLAYWRIGHT_E2E=1 PLAYWRIGHT_VERBOSE=1 vendor/bin/phpunit tests/E2E
```

## Common Testing Patterns

### Testing a Login Form

```php
public function testUserLogin(): void
{
    $page = $this->visit('/login');

    // Fill the login form
    $page->fill('input[name="email"]', 'user@example.com');
    $page->fill('input[name="password"]', 'password123');
    $page->click('button[type="submit"]');

    // Wait for navigation
    $page->waitForLoadState();

    // Verify login success
    $this->assertPageContains('Dashboard');
    $this->assertSelectorExists('.user-menu');
}
```

### Testing with Authentication

```php
public function testProtectedPage(): void
{
    // Set authentication cookie
    $this->authenticate('user@example.com', ['roles' => ['ROLE_USER']]);

    // Visit protected page
    $page = $this->visit('/profile');

    // Verify access granted
    $this->assertPageContains('My Profile');
}
```

### Testing AJAX Interactions

```php
public function testAjaxLoad(): void
{
    $page = $this->visit('/articles');

    // Click load more button
    $page->click('#load-more');

    // Wait for new content to appear
    $page->waitForSelector('.article-10');

    // Verify content loaded
    $this->assertSelectorExists('.article-10');
}
```

### Testing with Cookies

```php
public function testCookiePreferences(): void
{
    // Set a cookie before visiting
    $this->setCookie('theme', 'dark');

    $page = $this->visit('/');

    // Verify dark theme is applied
    $this->assertSelectorExists('body.theme-dark');

    // Read cookie value
    $theme = $this->getCookie('theme');
    $this->assertEquals('dark', $theme);
}
```

## Troubleshooting

### Tests are Skipped

**Problem:** All tests are skipped with message "Playwright E2E tests are disabled"

**Solution:** Set the `PLAYWRIGHT_E2E=1` environment variable:
```bash
PLAYWRIGHT_E2E=1 vendor/bin/phpunit tests/E2E
```

### Browser Connection Error

**Problem:** "Failed to connect JSON-RPC transport"

**Solution:** Ensure Playwright browsers are installed:
```bash
npx playwright install chromium
```

### Node.js Not Found

**Problem:** "node: command not found"

**Solution:** Install Node.js 18 or higher:
```bash
# macOS with Homebrew
brew install node

# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Or use nvm
nvm install 18
```

### Tests Timeout

**Problem:** Tests hang or timeout

**Solution:** Increase timeout in your test or check browser installation:
```php
// In your test
$page->setDefaultTimeout(60000); // 60 seconds
```

## Next Steps

Now that you have the bundle installed and running:

- Learn about all available [Test Helpers](helpers.md)
- Explore [Configuration Options](configuration.md)
- Understand the [Architecture](architecture.md)
- Enable [Request Logging](logging.md) for debugging

## Integration with CI/CD

See [Configuration Reference](configuration.md#cicd-integration) for GitHub Actions, GitLab CI, and other CI/CD examples.
