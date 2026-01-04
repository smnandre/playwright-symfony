# Configuration Reference

This document describes all configuration options for the Playwright Symfony Bundle.

## Basic Configuration

Create or edit `config/packages/test/playwright.yaml`:

```yaml
playwright:
    enabled: true
    base_url: 'http://localhost'
    intercepted_hosts: ['localhost', '127.0.0.1']
    debug_logging: false
```

## Top-Level Options

### `enabled`

**Type:** `bool`
**Default:** `true`

Enable or disable the entire bundle. When disabled, no services are registered.

```yaml
playwright:
    enabled: true
```

### `base_url`

**Type:** `string`
**Default:** `http://localhost`

The base URL used when building absolute URLs and setting cookies. This is the origin your tests will navigate to.

```yaml
playwright:
    base_url: 'https://myapp.test'
```

### `intercepted_hosts`

**Type:** `array`
**Default:** `['localhost', '127.0.0.1', 'testapp.local']`

List of hostnames that will be intercepted and handled by the Symfony kernel. Requests to other hosts will be forwarded to the real network.

```yaml
playwright:
    intercepted_hosts:
        - 'localhost'
        - '127.0.0.1'
        - 'myapp.local'
```

### `debug`

**Type:** `bool`
**Default:** `%kernel.debug%`

Enable debug mode for additional logging from bundle services.

```yaml
playwright:
    debug: '%kernel.debug%'
```

### `debug_logging`

**Type:** `bool`
**Default:** `false`

Enable verbose request/response logging without requiring the `PLAYWRIGHT_VERBOSE` environment variable.

```yaml
playwright:
    debug_logging: true
```

When enabled, every intercepted request will be logged with timing information.

### `playwright_path`

**Type:** `string`
**Default:** `npx playwright`

Command used to manage Playwright browsers. Useful if Playwright is installed globally or in a custom location.

```yaml
playwright:
    playwright_path: './vendor/bin/playwright'
```

### `node_path`

**Type:** `string`
**Default:** `node`

Path to the Node.js executable. Override if Node.js is installed in a non-standard location.

```yaml
playwright:
    node_path: '/usr/local/bin/node'
```

### `default_browser`

**Type:** `string`
**Default:** `default`

Name of the default browser configuration to use. Must match a key in `browsers` configuration.

```yaml
playwright:
    default_browser: 'chromium_headful'
```

## Browser Configuration

Define named browser configurations under the `browsers` key. Each configuration can have different settings.

```yaml
playwright:
    browsers:
        default:
            type: 'chromium'
            headless: true
            timeout_ms: 30000

        firefox:
            type: 'firefox'
            headless: true

        webkit:
            type: 'webkit'
            headless: true

        debug:
            type: 'chromium'
            headless: false
            slowmo_ms: 100
```

### Browser Options

#### `type`

**Type:** `enum`
**Values:** `chromium`, `firefox`, `webkit`
**Default:** `chromium`

The browser engine to use.

```yaml
browsers:
    my_browser:
        type: 'firefox'
```

#### `channel`

**Type:** `string`
**Default:** `null`

Playwright channel for specific browser versions (e.g., `chrome`, `chrome-beta`, `msedge`).

```yaml
browsers:
    edge:
        type: 'chromium'
        channel: 'msedge'
```

#### `headless`

**Type:** `bool`
**Default:** `true`

Run browser in headless mode (without GUI). Can be overridden by `PLAYWRIGHT_HEADLESS` environment variable.

```yaml
browsers:
    visible:
        type: 'chromium'
        headless: false
```

#### `timeout_ms`

**Type:** `int`
**Default:** `30000`

Default timeout in milliseconds for browser operations.

```yaml
browsers:
    slow:
        type: 'chromium'
        timeout_ms: 60000  # 60 seconds
```

#### `slowmo_ms`

**Type:** `int`
**Default:** `0`

Slows down operations by the specified milliseconds. Useful for debugging.

```yaml
browsers:
    debug:
        type: 'chromium'
        slowmo_ms: 250  # 250ms delay between operations
```

#### `args`

**Type:** `array`
**Default:** `[]`

Additional command-line arguments to pass to the browser.

```yaml
browsers:
    custom:
        type: 'chromium'
        args:
            - '--disable-gpu'
            - '--no-sandbox'
            - '--disable-dev-shm-usage'
```

#### `env`

**Type:** `array`
**Default:** `[]`

Environment variables to set for the browser process.

```yaml
browsers:
    custom:
        type: 'chromium'
        env:
            DISPLAY: ':99'
```

#### `downloads_dir`

**Type:** `string`
**Default:** `null`

Directory where downloaded files will be saved.

```yaml
browsers:
    custom:
        type: 'chromium'
        downloads_dir: '%kernel.project_dir%/var/downloads'
```

#### `videos_dir`

**Type:** `string`
**Default:** `null`

Directory where test videos will be recorded.

```yaml
browsers:
    record:
        type: 'chromium'
        videos_dir: '%kernel.project_dir%/var/videos'
```

#### `screenshot_dir`

**Type:** `string`
**Default:** `null`

Directory where screenshots will be saved.

```yaml
browsers:
    custom:
        type: 'chromium'
        screenshot_dir: '%kernel.project_dir%/var/screenshots'
```

#### `proxy`

**Type:** `array`
**Default:** `null`

Proxy server configuration.

```yaml
browsers:
    proxied:
        type: 'chromium'
        proxy:
            server: 'http://proxy.example.com:8080'
            username: 'user'
            password: 'pass'
            bypass: 'localhost,127.0.0.1'
```

#### `tracing`

**Type:** `array`
**Default:** See below

Playwright tracing configuration for debugging.

```yaml
browsers:
    traced:
        type: 'chromium'
        tracing:
            enabled: true
            dir: '%kernel.project_dir%/var/traces'
            screenshots: true
            snapshots: true
```

## Asset Configuration

Configure how static assets are served to the browser.

```yaml
playwright:
    assets:
        public_roots:
            - '%kernel.project_dir%/public'
        prefixes:
            - '/assets'
            - '/build'
            - '/_framework/ux'
        disable_cache: true
```

### `assets.public_roots`

**Type:** `array`
**Default:** `['%kernel.project_dir%/public']`

Filesystem paths where static files are located. The AssetServer will search these directories.

```yaml
playwright:
    assets:
        public_roots:
            - '%kernel.project_dir%/public'
            - '%kernel.project_dir%/assets'
```

### `assets.prefixes`

**Type:** `array`
**Default:** `['/assets', '/build', '/_framework/ux']`

URL path prefixes that should be handled by the AssetServer instead of the kernel. This improves test performance by serving static files directly.

```yaml
playwright:
    assets:
        prefixes:
            - '/assets'
            - '/build'
            - '/bundles'
            - '/static'
```

### `assets.disable_cache`

**Type:** `bool`
**Default:** `true`

Send `Cache-Control: no-store` headers for assets to keep tests deterministic.

```yaml
playwright:
    assets:
        disable_cache: false  # Enable caching
```

## Environment Variables

Environment variables override configuration file settings:

### `PLAYWRIGHT_E2E`

**Required:** Yes (for tests to run)
**Values:** `1` or `0`

Enable E2E test execution. Tests are skipped by default unless this is set.

```bash
PLAYWRIGHT_E2E=1 vendor/bin/phpunit tests/E2E
```

### `PLAYWRIGHT_HEADLESS`

**Values:** `true` or `false`
**Default:** `true`

Show or hide browser window during tests.

```bash
# Show browser window
PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit tests/E2E
```

### `PLAYWRIGHT_BROWSER`

**Values:** `chromium`, `firefox`, `webkit`
**Default:** `chromium`

Choose which browser engine to use.

```bash
PLAYWRIGHT_BROWSER=firefox vendor/bin/phpunit tests/E2E
```

### `PLAYWRIGHT_VERBOSE`

**Values:** `1`, `0`, `true`, `false`
**Default:** `0`

Enable verbose logging regardless of `debug_logging` configuration.

```bash
PLAYWRIGHT_VERBOSE=1 vendor/bin/phpunit tests/E2E
```

### `KERNEL_CLASS`

**Values:** Any kernel class name
**Default:** `App\Kernel`

Specify custom kernel class for tests.

```bash
KERNEL_CLASS='App\\TestKernel' vendor/bin/phpunit tests/E2E
```

## Complete Example

Here's a comprehensive configuration example:

```yaml
# config/packages/test/playwright.yaml
playwright:
    enabled: true
    base_url: 'http://localhost'
    debug_logging: false

    intercepted_hosts:
        - 'localhost'
        - '127.0.0.1'
        - 'app.test'

    default_browser: 'default'

    browsers:
        # Default headless browser for CI
        default:
            type: 'chromium'
            headless: true
            timeout_ms: 30000
            args:
                - '--no-sandbox'
                - '--disable-dev-shm-usage'

        # Firefox for cross-browser testing
        firefox:
            type: 'firefox'
            headless: true
            timeout_ms: 30000

        # Visible browser for local debugging
        debug:
            type: 'chromium'
            headless: false
            slowmo_ms: 100
            timeout_ms: 60000

        # Browser with recording enabled
        record:
            type: 'chromium'
            headless: true
            videos_dir: '%kernel.project_dir%/var/test-videos'
            screenshot_dir: '%kernel.project_dir%/var/screenshots'

    assets:
        public_roots:
            - '%kernel.project_dir%/public'
        prefixes:
            - '/assets'
            - '/build'
            - '/bundles'
            - '/_framework/ux'
        disable_cache: true
```

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/e2e.yml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, iconv

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress

      - name: Install Playwright browsers
        run: npx playwright install chromium --with-deps

      - name: Run E2E tests
        run: vendor/bin/phpunit tests/E2E
        env:
          PLAYWRIGHT_E2E: '1'
          PLAYWRIGHT_HEADLESS: 'true'
          PLAYWRIGHT_BROWSER: 'chromium'
```

### GitLab CI

```yaml
# .gitlab-ci.yml
e2e:
  stage: test
  image: php:8.3

  before_script:
    - apt-get update && apt-get install -y git nodejs npm
    - composer install
    - npx playwright install chromium --with-deps

  script:
    - vendor/bin/phpunit tests/E2E

  variables:
    PLAYWRIGHT_E2E: '1'
    PLAYWRIGHT_HEADLESS: 'true'
```

### CircleCI

```yaml
# .circleci/config.yml
version: 2.1

jobs:
  e2e:
    docker:
      - image: cimg/php:8.3-node

    steps:
      - checkout
      - run: composer install
      - run: npx playwright install chromium --with-deps
      - run:
          name: Run E2E Tests
          command: vendor/bin/phpunit tests/E2E
          environment:
            PLAYWRIGHT_E2E: '1'
            PLAYWRIGHT_HEADLESS: 'true'
```

## Performance Tuning

### Optimize for Speed

```yaml
playwright:
    browsers:
        fast:
            type: 'chromium'
            headless: true
            timeout_ms: 10000
            args:
                - '--disable-gpu'
                - '--disable-dev-shm-usage'
                - '--disable-software-rasterizer'

    assets:
        disable_cache: true  # Faster for tests
```

### Optimize for Debugging

```yaml
playwright:
    debug_logging: true

    browsers:
        debug:
            type: 'chromium'
            headless: false
            slowmo_ms: 250
            timeout_ms: 0  # No timeout
```

## Next Steps

- Learn about [Test Helpers](helpers.md)
- Understand the [Architecture](architecture.md)
- Enable [Request Logging](logging.md)
