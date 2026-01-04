# Release Plan: Playwright Symfony Bundle v0.1.0

**Goal:** Release a working, well-tested bundle enabling E2E testing in the same PHP process with full Twig, security, and service integration.

**Current Status:** Core features working (173 tests, 18 skipped, 6 deprecations); changes staged for review.

---

## 📋 Phase 1: Pre-Release Validation (CRITICAL PATH)

### 1.1 Test Suite Completion
- [ ] **Review & fix skipped tests (18 total)**
  - Identify why tests are marked skipped (E2E requirement, fixtures, environment setup)
  - Re-enable critical E2E tests with proper environment setup
  - Verify all functional tests run without `PLAYWRIGHT_E2E=1` for CI/CD friendliness

- [ ] **Verify deprecations (6 total)**
  - Run test suite and capture deprecation warnings
  - Address deprecated Symfony API usage (likely in `BrowserKit` or routing)
  - Ensure PHP 8.3+ compatibility

- [ ] **Coverage validation**
  - Generate coverage report: `composer test-coverage`
  - Target: ≥80% coverage for public API, ≥70% overall
  - Focus on critical paths: request interception, asset serving, helper methods

### 1.2 Code Quality
- [ ] **Run full linting pipeline**
  - `composer cs-check` - verify no style issues
  - Fix any failures with `composer cs-fix`
  - Ensure all files have strict types declaration and license header

- [ ] **Static analysis**
  - Run PHPStan if available: `vendor/bin/phpstan analyse src/`
  - Address any type/logic issues

### 1.3 Documentation Review
- [ ] **Verify all docs match implementation**
  - Check docs/ accuracy (getting-started.md, configuration.md, helpers.md)
  - Update examples if API changed (especially BrowserKit bridge)
  - Add docblock comments to public methods
  - Link examples from README to docs/getting-started.md

- [ ] **Environment variable documentation**
  - Document all env var precedence: `PLAYWRIGHT_E2E`, `PLAYWRIGHT_HEADLESS`, `PLAYWRIGHT_BROWSER`, `PLAYWRIGHT_VERBOSE`
  - Clarify which vars are required vs optional

---

## 📦 Phase 2: Integration Testing (CORE FEATURES)

### 2.1 Same-Thread Request Handling
- [ ] **Validate in-process kernel routing**
  - Confirm `PlaywrightClient` properly converts browser requests to Symfony `Request` objects
  - Verify `ResponseConverter` correctly transforms Symfony `Response` back to browser response
  - Test with multiple controllers (GET, POST, redirects)
  - Test request hooks: `beforeRequest()` and `afterResponse()` execution

- [ ] **Test fixture**
  - Ensure `tests/Fixtures/App/TestKernel.php` properly configured
  - Verify routes load from test kernel (check `NavigationController` routes)
  - Add test routes for: simple GET, form POST, redirects, file uploads

### 2.2 Twig Template Support
- [ ] **Verify Twig rendering in same process**
  - Create test: render template with variables, assert HTML contains expected content
  - Test template inheritance, includes, filters
  - Test asset URLs in templates (should work with `AssetServer`)
  - Test dynamic content via `{{ }}` placeholders

- [ ] **Add test controller & template**
  - Add controller action returning Twig response
  - Create corresponding template demonstrating Twig syntax
  - Verify template renders correctly in E2E test

### 2.3 Symfony Security Integration
- [ ] **Authentication & authorization**
  - Test `authenticate()` helper sets proper security token
  - Verify authenticated routes work with `IS_AUTHENTICATED` voter
  - Test role-based access control (`ROLE_ADMIN`, `ROLE_USER`)
  - Test `logout()` clears authentication

- [ ] **Security events & listeners**
  - Verify `AuthenticationSuccessEvent` fires during `authenticate()`
  - Verify security context properly populated
  - Test custom security voters if used

- [ ] **Add security test**
  - Create protected route requiring `ROLE_ADMIN`
  - Test unauthenticated access denied
  - Test authenticated access allowed
  - Test logout redirects to login

### 2.4 Service Container Access
- [ ] **Verify Symfony services accessible during tests**
  - Test accessing Doctrine ORM: `static::getContainer()->get('doctrine.orm.entity_manager')`
  - Test accessing custom services: `static::getContainer()->get('service_id')`
  - Test service methods called from controller during request

- [ ] **Database interaction**
  - Test inserting fixture data before test
  - Verify controller accesses database via ORM
  - Confirm transaction handling (rollback between tests if needed)

---

## 🎨 Phase 3: Feature Completeness

### 3.1 Asset Server
- [ ] **Static asset serving**
  - Verify `/assets/...` requests bypass kernel and serve directly
  - Test `/build/...` (AssetMapper) routes
  - Test cache-busting headers (`Cache-Control`, `ETag`)
  - Verify `FilesystemProxy` and `AssetMapperProxy` work correctly

- [ ] **Add asset test**
  - Create simple CSS/JS files in test app
  - Reference them in template via asset functions
  - Assert assets load with correct paths

### 3.2 Cookie & Session Management
- [ ] **Cookie handling**
  - Test `setCookie()` sets browser cookie correctly
  - Test cookies persist across requests
  - Test `clearCookies()` removes all cookies
  - Test CSRF token handling if applicable

- [ ] **Session storage**
  - Verify sessions properly stored/retrieved
  - Test session data available in controller

### 3.3 Request/Response Inspection
- [ ] **Inspect intercepted requests**
  - Test `getLastRequest()` returns Symfony Request object
  - Verify headers, body, query params accessible
  - Test multiple requests tracked correctly

- [ ] **Inspect responses**
  - Test `getLastResponse()` returns Symfony Response
  - Verify status code, headers, body content
  - Test response headers in assertions

### 3.4 BrowserKit Bridge
- [ ] **PlaywrightBrowser compatibility**
  - Verify BrowserKit tests can use `$client->request()` (or documented limitation)
  - Test crawler operations (if supported)
  - Document which BrowserKit methods work vs don't work

- [ ] **Add BrowserKit test example**
  - Show how to use `PlaywrightBrowser` from DI container
  - Demonstrate DomCrawler usage if applicable

---

## 🧪 Phase 4: Test Suite Hardening

### 4.1 Add Missing E2E Tests
- [ ] **Form handling**
  - Test form fill + submit with validation
  - Test file upload handling
  - Test multipart form data

- [ ] **JavaScript interaction**
  - Test `evaluate()` JS execution
  - Test async operations: `waitForSelector()`, `waitForNavigation()`
  - Test event listeners (click, input, etc.)

- [ ] **Navigation & routing**
  - Test redirect following
  - Test history back/forward
  - Test relative vs absolute URLs

- [ ] **Error handling**
  - Test 404 responses
  - Test 500 errors
  - Test exception handling in controllers

### 4.2 Add Unit Tests
- [ ] **RequestConverter**
  - Test HTTP method conversion (GET, POST, PUT, DELETE)
  - Test query parameters extraction
  - Test request body (JSON, form data)
  - Test header mapping

- [ ] **ResponseConverter**
  - Test status code mapping
  - Test header fulfillment
  - Test body content

- [ ] **AssetServer**
  - Test asset file matching
  - Test cache control headers
  - Test 404 for missing assets

### 4.3 Integration Tests
- [ ] **Complete flow test**
  - Authenticate user
  - Navigate to protected page
  - Verify Twig template renders
  - Access service data in template
  - Submit form with CSRF protection
  - Verify database changes

---

## 📚 Phase 5: Documentation & Release

### 5.1 Documentation Finalization
- [ ] **API Documentation**
  - Document all public methods in `PlaywrightTestCase`
  - Document assertion helpers (trait methods)
  - Document configuration options with examples

- [ ] **Migration/Getting Started**
  - Clear setup instructions for new projects
  - Show typical test structure
  - Include runnable example tests

- [ ] **Troubleshooting Guide**
  - Common errors: "Failed to connect", "ERR_CONNECTION_REFUSED", timeouts
  - Browser installation issues
  - Request interception debugging

- [ ] **CHANGELOG**
  - Document v0.1.0 features
  - Note breaking changes if any
  - List known limitations

### 5.2 Release Artifacts
- [ ] **Version bumps**
  - Update `composer.json` version (if using version field)
  - Verify `branch-alias` in composer.json

- [ ] **Git & GitHub**
  - Commit all staged changes: `git commit -m "Release v0.1.0: Core bundle with in-process testing"`
  - Create git tag: `git tag -a v0.1.0 -m "v0.1.0: Ready for production use"`
  - Push to GitHub: `git push origin main && git push origin v0.1.0`

- [ ] **GitHub Release**
  - Create release notes summarizing features
  - Link to documentation
  - Note system requirements

---

## 🚀 Phase 6: Post-Release (Optional)

### 6.1 Monitoring
- [ ] **Track issues** - Watch GitHub issues for bugs
- [ ] **Gather feedback** - Collect user feedback from early adopters
- [ ] **Performance baseline** - Measure test execution time vs traditional E2E approaches

### 6.2 Future Enhancements (Roadmap)
- [ ] Assertion helper expansion (accessibility, network assertions)
- [ ] Fixture lifecycle traits (FixtureLifecycleTrait mentioned in ROADMAP)
- [ ] Structured logging with context (logger.channel.playwright)
- [ ] BrowserKit bridge hardening (if separate package extraction planned)

---

## ✅ Acceptance Criteria for v0.1.0

- [x] Code compiles, no PHP/Symfony compatibility issues
- [ ] Test suite passes: `composer test` → all tests green (or documented as intentionally skipped)
- [ ] Code style passes: `composer cs-check` → clean output
- [ ] Coverage ≥70% overall, ≥80% for public API
- [ ] Documentation complete: README updated, getting-started.md clear, examples runnable
- [ ] Core features validated:
  - ✓ In-process request handling (kernel routing)
  - ✓ Twig template rendering
  - ✓ Symfony security integration (auth, roles)
  - ✓ Service container access
  - ✓ Static asset serving
  - ✓ Cookie/session management
- [ ] No critical security issues
- [ ] Composer.lock stable (playwright-php/playwright pinned to released version)
- [ ] CI/CD green on GitHub Actions (if configured)

---

## 📋 Quick Checklist (for execution)

```
PRE-RELEASE:
[ ] composer test (verify passing/skipped breakdown)
[ ] composer cs-check (fix any issues)
[ ] composer test-coverage (verify coverage %)
[ ] Review staged git changes (git status)

VALIDATION:
[ ] Verify test count & skipped reasons
[ ] Validate Twig integration (add test if missing)
[ ] Test security flows (authenticate, logout, roles)
[ ] Test asset serving (/assets, /build paths)
[ ] Test form submissions & file uploads

DOCUMENTATION:
[ ] Update README.md with setup instructions
[ ] Complete docs/ folder (getting-started.md, configuration.md)
[ ] Add troubleshooting guide
[ ] Document all env vars and config options

RELEASE:
[ ] Final: composer cs-fix
[ ] Final: composer test (all green)
[ ] Commit: git commit -m "Release v0.1.0"
[ ] Tag: git tag -a v0.1.0
[ ] Push: git push origin main && git push origin v0.1.0
[ ] Create GitHub Release with notes
```

---

## 📞 Support & Resources

- **Playwright PHP**: https://github.com/playwright-php/playwright
- **Symfony Testing**: https://symfony.com/doc/current/testing.html
- **PHPUnit**: https://phpunit.de/
- **PHP-CS-Fixer**: https://cs.symfony.com/

---

**Last Updated:** 2025-01-04  
**Status:** Draft - Ready for execution phase
