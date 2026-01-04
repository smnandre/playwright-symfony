# Architecture Overview

This document explains the internal design and architecture of the Playwright Symfony Bundle.

## High-Level Architecture

The bundle consists of four main layers:

```
┌─────────────────────────────────────────────┐
│         PlaywrightTestCase (Test Layer)    │
│  - Test helpers and assertions              │
│  - Lifecycle management                     │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│      PlaywrightClient (Client Layer)        │
│  - Request interception coordination        │
│  - Cookie/session management                │
│  - BrowserKit integration                   │
└──────────────────┬──────────────────────────┘
                   │
         ┌─────────┴─────────┐
         │                   │
┌────────▼────────┐   ┌──────▼──────────────┐
│  PlaywrightBrowser│   │  AssetServer        │
│  (Browser Layer)  │   │  (Asset Layer)      │
│  - Launch/stop    │   │  - Static assets    │
│  - Page creation  │   │  - AssetMapper      │
│  - Route setup    │   │  - Filesystem       │
└───────────────────┘   └─────────────────────┘
```

## Layer Details

### 1. Test Layer

**PlaywrightTestCase** (`src/Test/PlaywrightTestCase.php`)

The main test base class that extends Symfony's `KernelTestCase`.

**Responsibilities:**
- Boot and configure the Symfony kernel
- Initialize PlaywrightBrowser and PlaywrightClient
- Provide test helper methods (`visit()`, `setCookie()`, etc.)
- Include assertion trait for custom assertions
- Manage test lifecycle (setUp/tearDown)
- Resolve bundle configuration from container

**Key Methods:**
```php
protected function setUp(): void
{
    // 1. Boot Symfony kernel
    // 2. Resolve configuration (base_url, intercepted_hosts, etc.)
    // 3. Create PlaywrightBrowser from environment
    // 4. Initialize PlaywrightClient with dependencies
}

protected function visit(string $path): PageInterface
{
    // Delegate to PlaywrightClient
}
```

**PlaywrightTestAssertionsTrait** (`src/Test/Assert/PlaywrightTestAssertionsTrait.php`)

Provides custom assertions for E2E testing:
- `assertPageContains()` - Check page HTML content
- `assertSelectorExists()` - Verify CSS selectors
- `waitForSelector()` - Wait for elements
- Convenience helpers - `click()`, `fill()`, `check()`, etc.

### 2. Client Layer

**PlaywrightClient** (`src/Client/PlaywrightClient.php`)

The central orchestrator that bridges Playwright and Symfony.

**Responsibilities:**
- Extend `AbstractBrowser` for BrowserKit compatibility
- Set up request interception via Playwright's routing API
- Coordinate between AssetServer and HttpKernel
- Convert requests/responses between Playwright and Symfony formats
- Manage cookies and browser state
- Invoke beforeRequest()/afterResponse() hooks

**Request Flow:**
```php
public function visit(string $path): PageInterface
{
    // 1. Ensure interceptor is set up
    $this->ensureInterceptorSetUp();

    // 2. Build absolute URL
    $url = $this->getBaseUrl() . $path;

    // 3. Navigate browser (triggers interception)
    $page = $this->browser->getPage();
    $page->goto($url);

    return $page;
}

private function setupRequestInterception(): void
{
    $this->browser->setupRouting(function ($route) {
        $request = $route->request();
        $url = parse_url($request->url());

        // Check if we should intercept this host
        if (!$this->shouldInterceptRequest($url)) {
            $route->continue(); // Forward to real network
            return;
        }

        // Try AssetServer first (faster for static files)
        if ($this->assetServer?->supports($request->url(), $request->method())) {
            $fulfillOptions = $this->assetServer->handle($request->url(), $request->method());
            if ($fulfillOptions) {
                $route->fulfill($fulfillOptions);
                return;
            }
        }

        // Handle via Symfony kernel
        $symfonyResponse = $this->handleInternalRequest($request);
        $route->fulfill($this->responseConverter->prepareFulfillOptions($symfonyResponse));
    });
}
```

**RequestConverter** (`src/Client/RequestConverter.php`)

Converts Playwright Request objects to Symfony Request objects.

**Handles:**
- URL parsing and path extraction
- HTTP method conversion
- Headers mapping
- POST data and file uploads
- Query parameters

**ResponseConverter** (`src/Client/ResponseConverter.php`)

Converts Symfony Response objects to Playwright fulfill options.

**Handles:**
- Status code mapping
- Headers conversion
- Content encoding (base64 for binary)
- Content-Type detection

### 3. Browser Layer

**PlaywrightBrowser** (`src/Browser/PlaywrightBrowser.php`)

Manages the Playwright browser lifecycle.

**Responsibilities:**
- Launch and stop browser processes
- Create browser contexts and pages
- Configure browser type (chromium/firefox/webkit)
- Set up headless/headful mode
- Register route handlers for request interception

**Lifecycle:**
```php
public function start(): void
{
    // 1. Determine browser type and options
    // 2. Launch browser via Playwright
    $this->context = Playwright::chromium($options);

    // 3. Create initial page
    $this->page = $this->context->newPage();
}

public function setupRouting(callable $routeHandler): void
{
    // Register universal route handler
    $this->page->route('**/*', $routeHandler);
}

public function stop(): void
{
    // Close browser context
    $this->context?->close();
}
```

**Factory Method:**
```php
public static function fromEnvironment(): self
{
    // Read PLAYWRIGHT_BROWSER env var
    $browserType = getenv('PLAYWRIGHT_BROWSER') ?: 'chromium';

    // Read PLAYWRIGHT_HEADLESS env var
    $headless = getenv('PLAYWRIGHT_HEADLESS') !== 'false';

    return new self($browserType, $headless);
}
```

### 4. Asset Layer

**AssetServer** (`src/Client/Interception/AssetServer.php`)

Fast asset response system that bypasses the kernel for performance.

**Responsibilities:**
- Check if request matches configured asset prefixes
- Delegate to asset locators to find files
- Build Playwright fulfill options for assets
- Handle cache control headers

**Flow:**
```php
public function supports(string $url, string $method): bool
{
    // Only GET/HEAD requests
    if (!in_array($method, ['GET', 'HEAD'])) {
        return false;
    }

    $path = parse_url($url, PHP_URL_PATH);

    // Check against configured prefixes
    foreach ($this->prefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}

public function handle(string $url, string $method): ?array
{
    $path = parse_url($url, PHP_URL_PATH);

    // Try each locator
    foreach ($this->locators as $locator) {
        $asset = $locator->locate($path);
        if ($asset) {
            return $this->buildFulfillOptions($asset, $method);
        }
    }

    return null; // Not found, will fall through to kernel
}
```

**AssetMapperProxy** (`src/Asset/AssetMapperProxy.php`)

Integrates with Symfony's AssetMapper to serve digested assets.

**Responsibilities:**
- Query AssetMapper for mapped assets
- Handle versioned/digested filenames
- Resolve asset content and metadata

**FilesystemProxy** (`src/Asset/FilesystemProxy.php`)

Serves static files from configured public directories.

**Responsibilities:**
- Search multiple public roots for files
- Detect content types via file extension
- Read file contents for response

## Request Interception Flow

Here's the detailed flow when a test calls `visit('/path')`:

```
1. Test: visit('/path')
   └─> PlaywrightTestCase::visit()
       └─> PlaywrightClient::visit()

2. PlaywrightClient builds URL
   └─> "http://localhost/path"

3. PlaywrightClient ensures interception is set up
   └─> PlaywrightBrowser::setupRouting(callback)

4. Browser navigates to URL
   └─> page->goto("http://localhost/path")

5. Playwright triggers route interception
   └─> callback($route)

6. Check if host should be intercepted
   ├─> NO  → route->continue() (forward to network)
   └─> YES → continue to step 7

7. Check if AssetServer supports this request
   ├─> YES → AssetServer::handle()
   │         ├─> Try AssetMapperProxy
   │         ├─> Try FilesystemProxy
   │         └─> Return fulfill options or null
   └─> NO  → continue to step 8

8. Handle via Symfony kernel
   └─> PlaywrightClient::handleInternalRequest()
       ├─> RequestConverter::convertToSymfonyRequest()
       ├─> Call beforeRequest() hook
       ├─> HttpKernel::handle($request)
       ├─> Call afterResponse() hook
       ├─> ResponseConverter::prepareFulfillOptions()
       └─> Store lastRequest/lastResponse

9. Fulfill route with response
   └─> route->fulfill($fulfillOptions)

10. Browser receives response
    └─> Page is rendered

11. Test continues
    └─> Can access getLastRequest()/getLastResponse()
```

## Configuration Resolution

The bundle resolves configuration from multiple sources in priority order:

1. **Environment Variables** (highest priority)
   - `PLAYWRIGHT_E2E` - Enable tests
   - `PLAYWRIGHT_HEADLESS` - Browser mode
   - `PLAYWRIGHT_BROWSER` - Browser type
   - `PLAYWRIGHT_VERBOSE` - Logging

2. **Bundle Configuration** (`config/packages/test/playwright.yaml`)
   - `playwright.base_url`
   - `playwright.intercepted_hosts`
   - `playwright.debug_logging`
   - `playwright.browsers.*`
   - `playwright.assets.*`

3. **Defaults** (lowest priority)
   - Defined in `Configuration.php`

**Resolution in PlaywrightTestCase:**
```php
private function resolveBaseUrl(): string
{
    $container = self::$kernel->getContainer();

    if ($container->hasParameter('playwright.base_url')) {
        return $container->getParameter('playwright.base_url');
    }

    return 'http://localhost'; // Default
}
```

## Service Registration

Services are registered in `PlaywrightExtension.php`:

```php
// Core services
$container->register('playwright.browser', PlaywrightBrowser::class);
$container->register('playwright.client', PlaywrightClient::class);

// Converters
$container->register('playwright.request_converter', RequestConverter::class);
$container->register('playwright.response_converter', ResponseConverter::class);

// Asset handling
$container->register('playwright.asset_server', AssetServer::class);
$container->register('playwright.asset_mapper_proxy', AssetMapperProxy::class);
$container->register('playwright.filesystem_proxy', FilesystemProxy::class);
```

## Performance Optimizations

### 1. Asset Bypassing

Static assets are served directly by AssetServer without hitting the kernel:

- Faster test execution
- Reduced kernel overhead
- Configurable via `assets.prefixes`

### 2. Request Filtering

Only configured hosts are intercepted:

- External requests (CDNs, APIs) pass through to network
- Configurable via `intercepted_hosts`

### 3. Lazy Browser Initialization

Browser is only started when tests need it:

- Tests can be skipped without browser overhead
- Controlled via `PLAYWRIGHT_E2E=1` flag

### 4. Efficient Converters

Request/Response converters use direct property access:

- No serialization overhead
- Minimal memory footprint
- Binary content handled efficiently (base64 when needed)

## Extension Points

### Custom Test Base Classes

```php
abstract class MyE2ETest extends PlaywrightTestCase
{
    protected function loadFixtures(array $fixtures): void
    {
        // Custom fixture loading
    }

    protected function beforeRequest(SymfonyRequest $request): void
    {
        // Custom request modification
    }
}
```

### Custom Asset Locators

Implement `AssetLocatorInterface`:

```php
class CustomAssetLocator implements AssetLocatorInterface
{
    public function locate(string $requestPath): ?AssetFile
    {
        // Custom asset resolution logic
    }
}
```

Register in configuration:

```yaml
services:
    app.custom_asset_locator:
        class: App\CustomAssetLocator
        tags: ['playwright.asset_locator']
```

### Custom Browsers

Define named browser configurations:

```yaml
playwright:
    browsers:
        my_browser:
            type: 'chromium'
            headless: false
            args: ['--start-maximized']
```

## Testing Strategy

The bundle itself uses multiple testing levels:

1. **Unit Tests** (`tests/`)
   - Test converters with fake Playwright objects
   - Test browser lifecycle
   - Test asset locators

2. **Integration Tests** (`tests/Integration/`)
   - Test component integration
   - Verify request/response flow

3. **E2E Tests** (`tests/Integration/E2E/`)
   - Test with real browsers
   - Verify end-to-end functionality
   - Use test Symfony application (`tests/Fixtures/App/`)

## Next Steps

- Review [Helper Reference](helpers.md) for available test methods
- Check [Configuration](configuration.md) for customization options
- Enable [Logging](logging.md) to debug request flow
