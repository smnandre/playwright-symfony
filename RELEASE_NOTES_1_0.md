# Playwright Symfony 1.0 Release Notes

## Performance: Shared Browser Architecture
The browser lifecycle has been refactored to significantly reduce test execution time.
- Implemented a static shared browser instance in `PlaywrightTestCase` that persists across the entire test class.
- Introduced `restartContext()` to provide fresh cookies and page state for every test without the overhead of restarting the browser process.
- Added smart reconfiguration logic that only restarts the browser if the required engine (Chromium, Firefox, WebKit) or headless settings change.

## Stability: Robust Core Logic
The internal translation layer between Symfony and Playwright has been hardened for production reliability.
- **RequestConverter**: Enhanced header normalization and multipart parsing to handle complex HttpFoundation edge cases.
- **Navigation Safety**: Implemented an automated retry loop in `getCrawler()` to resolve race conditions during active page navigation.
- **Cookie Lifecycle**: Fixed `clearCookie()` to properly expire cookies in the Playwright context, ensuring accurate authentication state.

## Architecture: Modular Utilities
Core logic has been decoupled into dedicated, testable utility classes:
- `XPathHelper`: Robust absolute XPath generation for precise element targeting.
- `FormInteractor`: Centralized logic for filling complex input types (Selects, Checkboxes, Radios, Files).
- `CookieJarSync`: Bidirectional state synchronization between Playwright and Symfony's CookieJar.

## Developer Experience: Professional Testing API
The package now provides a seamless transition for developers familiar with Symfony's native testing tools.
- **Full BrowserKit Support**: The internal client now supports the complete `AbstractBrowser` API, allowing the use of `$this->click()` and `$this->submit()` while maintaining kernel-level interception.
- **Enhanced Assertions**: Expanded `PlaywrightTestAssertionsTrait` with visibility checks (`assertSelectorVisible`) and Symfony-native HTTP assertions (`assertResponseIsSuccessful`, `assertResponseStatusCode`).

## Verification
- Total Tests: 268 passing (100% success rate).
- Line Coverage: Core translation and utility logic reached 100% coverage.
- Compatibility: Fully verified with Symfony 7.x/8.x and PHP 8.3+.
