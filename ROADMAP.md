# Project Roadmap

## Audit Follow-ups
- **Multipart parsing:** Add fuzz/regression coverage (binary uploads, nested boundaries, malformed filenames) around the new `HeaderUtils`-driven parser so future changes can’t silently reintroduce the old bugs.
- **Dependency stability:** Keep `playwright-php/playwright` pinned to released tags; add CI guardrails that fail when `composer.lock` drifts back to `dev-main`.
- **Client semantics:** Document clearly that `PlaywrightClient` cannot be used via `AbstractBrowser::request()` and provide guidance for users expecting BrowserKit flows (link to the BrowserKit bridge when ready).
- **Profiler + base URL:** Ensure the profiler token lookup continues to work after future kernel changes and keep the `PLAYWRIGHT_BASE_URL` parameter documented in README/phpunit templates.
- **Redirect limitation:** Track the known limitation where real-browser redirects can bypass interception (leading to `ERR_CONNECTION_REFUSED`) and explore mitigation (e.g., capturing redirect targets and fulfilling them manually).

## Assets
- **Status:** AssetServer + proxies are shipping behind bundle parameters.
- **Next steps:**
  - Emit structured debug logs for asset hits/misses (hooked into `playwright.debug_logging`) so users can trace why a request fell back to the kernel.
  - Document and implement a service-tag hook so custom `AssetLocatorInterface` implementations can be registered from applications without editing the bundle.

## BrowserKit Bridge
- **Goal:** Provide a BrowserKit + DomCrawler bridge powered by a real Playwright page (now under `Playwright\Symfony\BrowserKit`).
- **Next steps:**
  - Add a lightweight network tracker so BrowserKit responses capture status/headers from the last main-frame request.
  - Publish a reusable PHPUnit trait/base test case that spins up the BrowserKit bridge with fixtures and artifact capture (HTML/screenshot on failure).
  - Build a demo suite exercising navigation, clicks, multipart submits, cookies, auth headers, and popup flows against the test kernel.
  - Re-evaluate whether to extract the bridge into a separate package once the tracker + demo land.

## Test Suite
- **Goal:** Finish lightweight coverage for `PlaywrightTestCase` orchestration without launching a real browser.
- **Next steps:**
  - Build/extend the fixture subclass (`tests/Fixtures/Tests/TestablePlaywrightTestCase`) so we can inject fake browser/client objects and expose wrappers for protected hooks.
  - Add tests covering `PLAYWRIGHT_E2E`, `PLAYWRIGHT_HEADLESS`, `PLAYWRIGHT_BROWSER`, and `PLAYWRIGHT_VERBOSE` environment interactions plus container-parameter fallbacks (`playwright.base_url`, `playwright.debug_logging`, `playwright.intercepted_hosts`).
  - Use fake browser/client implementations to verify helpers (`visit`, `setCookie`, `clearCookies`, `authenticate`, `logout`, `getLastRequest/Response`, magic `$page`).
  - Add teardown coverage proving fake browsers receive `stop()` and that `restoreExceptionHandlers()` fully unwinds stacked handlers.

## DX: Assertion Helper Expansion
- **MVP (v0.9.x):** Ship text + selector assertions (`assertPageHasText`, `assertSelectorTextContains`, `assertSelectorVisible`) with PHPUnit coverage and README + `docs/assertions.md` examples.
- **Phase 2:** Add accessibility helpers (`assertRoleVisible`, `assertAriaState`) and document extension hooks (`registerAssertionHelper`).
- **Phase 3:** Explore network/download assertions once Playwright telemetry APIs stabilize.
- **Success metrics:** ≥40% reduction in boilerplate in sample suites, 100% coverage for helper paths, zero BC-break reports during beta.

## DX: Logging & Fixtures
- **Structured logging:** Use `logger.channel.playwright` to emit info/debug events (browser lifecycle, intercepted requests, asset cache hits/misses) with context `{url, method, status_code, asset_path, duration_ms}`.
- **Verbosity controls:** Keep `playwright.debug_logging` + `PLAYWRIGHT_VERBOSE` precedence documented (env > config > default) so CI noise stays low.
- **Fixture utilities:** Deliver a `FixtureLifecycleTrait` (plus Doctrine example) that wires `seedFixtures()` / `resetState()` around Playwright visits; document ordering relative to `beforeRequest/afterResponse` hooks.
- **Docs & samples:** Update README tips, add `docs/logging.md` and fixture guide, and include sample tests demonstrating the trait + verbose logging flow.

## Legacy Asset Bridge
- **Goal:** Decide whether the KernelBrowser-based `AssetMapperPlaywrightBridge` is still needed now that AssetServer exists.
- **Next steps:**
  - Either harden it (tests + docs for the KernelBrowser proxy) for teams needing pure Kernel flows, or deprecate it formally in favor of AssetServer once parity tests confirm no gaps.
