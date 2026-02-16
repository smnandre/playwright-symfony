# Asset Dev Server Bridge

The bundle ships with a lightweight in-process asset server that fulfills Playwright’s intercepted requests without
booting Symfony’s HTTP kernel. It binds together the `AssetMapperProxy` (for assets managed via Symfony AssetMapper) and
the filesystem fallback so that static files resolve exactly the same way they do in your application.

## When It Runs

During every intercepted browser request the bundle asks the `AssetServer` if it can handle the URL. A request is
handled when:

1. The HTTP method is `GET` or `HEAD`.
2. The path starts with one of the configured prefixes (see below).
3. One of the registered asset locators (AssetMapper, filesystem) can resolve the path to an `AssetFile`.

When these conditions are met, the bundle bypasses the kernel entirely and responds straight from PHP memory, which
keeps asset-heavy pages fast even in high-parallel Playwright suites.

## Configuration

The asset server is configured under the `playwright.assets` section in `config/packages/test/playwright.yaml` (or the
equivalent bundle config you load in your test environment):

```yaml
# config/packages/test/playwright.yaml
playwright:
  assets:
    prefixes:
      - '/assets'
      - '/build'
      - '/_framework/ux'
      - '/custom'
    public_roots:
      - '%kernel.project_dir%/public'
      - '%kernel.project_dir%/.legacy-static'
    disable_cache: true
```

### Options

| Option          | Description                                                                                                                                                                                                                                |
|-----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `prefixes`      | URL prefixes that should be served directly by the asset bridge. Paths are normalized so both `/assets` and `assets/` work.                                                                                                                |
| `public_roots`  | Filesystem directories searched by the filesystem locator when AssetMapper does not return a match.                                                                                                                                        |
| `disable_cache` | When `true`, the asset server sends `Cache-Control: no-store, max-age=0, must-revalidate` for deterministic tests. Set to `false` if you want Playwright to respect long-lived caching headers (e.g. when testing ServiceWorker behavior). |

You can override any of these values per suite using Symfony’s standard config precedence rules or with environment
variables consumed inside the YAML (e.g. `disable_cache: '%env(bool:PLAYWRIGHT_ASSET_NO_CACHE)%'`).

## Customizing Prefixes and Roots

If your app exposes additional static mount points (for example `/static/brand` or `/bundles/acmeapp`), add them to
`prefixes`. The bundle will normalize the strings and treat `http://localhost/static/logo.svg` as eligible for
in-process serving.

For vendor assets or integration fixtures that live outside `public/`, declare extra `public_roots`. This is especially
handy when your tests reference files under `.storybook-static` or a temporary build folder generated as part of the CI
pipeline.

## Cache Behavior

With `disable_cache: true` the asset server always emits `Cache-Control: no-store, max-age=0, must-revalidate`. Turning
caching off avoids tricky flakiness where a previous test left a cached response in the browser. When you need to verify
HTTP caching semantics (ETag handling, ServiceWorker precaching, etc.), flip the flag to `false` and the bundle will
respond with `Cache-Control: public, max-age=31536000, immutable` plus a `Last-Modified` header when the metadata is
available.

Remember to reset the flag (or scope it to a specific test run through environment variables) so other suites keep their
deterministic defaults.

## Troubleshooting

| Symptom                       | Possible Causes & Fixes                                                                                                                                                                                     |
|-------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Requests still hit the kernel | Ensure the URL matches one of the `prefixes`. Add logging (`playwright.debug_logging: true`) to see the decision in realtime.                                                                               |
| Asset not found               | Confirm the path exists under one of `public_roots` or that AssetMapper exposes it. The AssetMapper locator respects digest paths, so make sure your importmap/build generated files are on disk.           |
| Binary files are corrupted    | The asset server automatically base64-encodes responses when the MIME type is not text-based. If you see malformed responses, double-check the MIME type reported by AssetMapper or the filesystem locator. |
| HEAD requests return a body   | The server strips `body` for HEAD requests; if you observe otherwise, verify that the client/browser is actually issuing a HEAD request and not a GET.                                                      |

## See Also

- [`docs/bridge/browserkit.md`](bridge/browserkit.md) – use BrowserKit/DomCrawler on top of a Playwright context.
- [`README.md`](../README.md) – high-level bundle overview and environment variable tips.
