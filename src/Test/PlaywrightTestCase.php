<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Test;

use PlaywrightPHP\Browser\BrowserContextInterface;
use PlaywrightPHP\Network\RequestInterface;
use PlaywrightPHP\Page\PageInterface;
use PlaywrightPHP\Playwright;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class PlaywrightTestCase extends KernelTestCase
{
    protected ?BrowserContextInterface $context = null;
    protected ?PageInterface $page = null;
    /** @var string[] */
    protected array $interceptedHosts = [];
    protected ?SymfonyRequest $lastRequest = null;
    protected ?SymfonyResponse $lastResponse = null;
    private static ?BrowserContextInterface $sharedBrowser = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $browser = $this->getBrowserType();
        $launchOptions = ['headless' => $this->isHeadless()];
        $this->context = match ($browser) {
            'firefox' => Playwright::firefox($launchOptions),
            'webkit' => Playwright::webkit($launchOptions),
            default => Playwright::chromium($launchOptions),
        };
        $this->page = $this->context->newPage();

        $this->loadInterceptedHosts();
        $this->setupRequestInterception();
    }

    protected function tearDown(): void
    {
        // Clean up Playwright resources first
        $this->context?->close();
        $this->context = null;
        $this->page = null;
        $this->lastRequest = null;
        $this->lastResponse = null;

        // Clean up exception handlers before calling parent
        $this->restoreExceptionHandlers();

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    // KernelTestCase handles kernel shutdown automatically

    private function restoreExceptionHandlers(): void
    {
        while (true) {
            $previousHandler = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previousHandler) {
                break;
            }
            restore_exception_handler();
        }
    }

    protected function setupRequestInterception(): void
    {
        // Try both page-level and context-level routing to catch all requests
        $routeHandler = function ($route) {
            $request = $route->request();
            $url = parse_url($request->url());

            $shouldIntercept = $this->shouldInterceptRequest($url);

            if ($shouldIntercept) {
                $response = $this->handleInternalRequest($request);
                $route->fulfill($this->prepareFulfillOptions($response));
            } else {
                $route->continue();
            }
        };

        // Simple single pattern to avoid duplicates
        $this->page->route('**/*', $routeHandler);
    }

    public function shouldInterceptRequest(array $url): bool
    {
        return isset($url['host']) && in_array($url['host'], $this->interceptedHosts, true);
    }

    protected function handleInternalRequest(RequestInterface $playwrightRequest): SymfonyResponse
    {
        $symfonyRequest = $this->convertToSymfonyRequest($playwrightRequest);

        $this->beforeRequest($symfonyRequest);

        // Store request and handle via kernel
        $this->lastRequest = $symfonyRequest;
        $response = self::$kernel->handle($symfonyRequest, HttpKernelInterface::MAIN_REQUEST, false);
        $this->lastResponse = $response;

        $this->afterResponse($response);

        return $response;
    }

    protected function convertToSymfonyRequest(RequestInterface $playwrightRequest): SymfonyRequest
    {
        $url = parse_url($playwrightRequest->url());
        $method = $playwrightRequest->method();
        $headers = $playwrightRequest->headers();
        $postData = $playwrightRequest->postData();

        $parameters = [];
        $cookies = [];
        $files = [];
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $url['path'].(isset($url['query']) ? '?'.$url['query'] : ''),
            'SERVER_NAME' => $url['host'] ?? 'localhost',
            'SERVER_PORT' => $url['port'] ?? 80,
            'HTTP_HOST' => $url['host'] ?? 'localhost',
            'HTTPS' => ($url['scheme'] ?? 'http') === 'https' ? 'on' : 'off',
        ];

        // Normalize headers; map special headers without HTTP_ prefix
        $lower = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', (string) $name));
            if ('CONTENT_TYPE' === $key || 'CONTENT_LENGTH' === $key) {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        // Parse cookies header into cookie bag
        if (isset($lower['cookie']) && is_string($lower['cookie'])) {
            $cookiePairs = array_map('trim', explode(';', $lower['cookie']));
            foreach ($cookiePairs as $pair) {
                if ('' === $pair) {
                    continue;
                }
                [$cName, $cVal] = array_pad(explode('=', $pair, 2), 2, '');
                if ('' !== $cName) {
                    $cookies[$cName] = urldecode($cVal);
                }
            }
        }

        $content = null;
        if ($postData) {
            if (is_string($postData)) {
                // If content-type is form-urlencoded, parse into parameters but preserve content
                $contentType = $lower['content-type'] ?? null;
                if ($contentType && str_starts_with(strtolower((string) $contentType), 'application/x-www-form-urlencoded')) {
                    parse_str($postData, $parameters);
                    $content = $postData; // Preserve original content for getContent()
                } elseif ($contentType && str_starts_with(strtolower((string) $contentType), 'multipart/form-data')) {
                    $content = $postData;
                    $this->parseMultipartFormData((string) $contentType, $postData, $parameters, $files);
                } else {
                    $content = $postData;
                }
            } else {
                $parameters = $postData;
            }
        }

        parse_str($url['query'] ?? '', $query);

        return new SymfonyRequest(
            $query,
            $parameters,
            [],
            $cookies,
            $files,
            $server,
            $content
        );
    }

    /**
     * Parse multipart/form-data body into parameters and files.
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $files
     */
    private function parseMultipartFormData(string $contentType, string $body, array &$parameters, array &$files): void
    {
        if (!preg_match('/boundary=(.+)$/i', $contentType, $m)) {
            return;
        }
        $boundary = trim($m[1], "\"\' ");
        if ('' === $boundary) {
            return;
        }
        $delimiter = '--'.$boundary;
        $parts = preg_split('/(?:^|\r?\n)'.preg_quote($delimiter, '/').'/', $body);
        if (false === $parts) {
            return;
        }

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ('' === trim($part) || str_starts_with($part, '--')) {
                continue;
            }
            $segments = preg_split("/\r?\n\r?\n/", $part, 2);
            if (!is_array($segments) || 2 !== count($segments)) {
                continue;
            }
            [$rawHeaders, $content] = $segments;
            $headers = [];
            foreach (preg_split('/\r?\n/', $rawHeaders) as $line) {
                if (false !== ($pos = strpos($line, ':'))) {
                    $hName = strtolower(trim(substr($line, 0, $pos)));
                    $hVal = trim(substr($line, $pos + 1));
                    $headers[$hName] = $hVal;
                }
            }
            $cd = $headers['content-disposition'] ?? '';
            if (!preg_match('/form-data;\s*name="([^"]+)"(?:;\s*filename="([^"]*)")?/i', $cd, $mm)) {
                continue;
            }
            $name = $mm[1];
            $filename = $mm[2] ?? null;
            $content = rtrim($content, "\r\n");
            if ('' === (string) $filename) {
                $parameters[$name] = $content;
            } else {
                // File field: persist to temp file and create UploadedFile
                $tmp = tempnam(sys_get_temp_dir(), 'pw_upload_');
                if (false === $tmp) {
                    continue;
                }
                file_put_contents($tmp, $content);
                $mime = $headers['content-type'] ?? null;
                $upload = new UploadedFile($tmp, $filename, is_string($mime) ? $mime : null, UPLOAD_ERR_OK, true);
                // Support nested names like files[avatar]
                $this->setArrayByPath($files, $name, $upload);
            }
        }
    }

    /**
     * Sets a value into an array using a path like `foo[bar][baz]`.
     *
     * @param array<string, mixed> $target
     */
    private function setArrayByPath(array &$target, string $path, mixed $value): void
    {
        if (!str_contains($path, '[')) {
            $target[$path] = $value;

            return;
        }
        $segments = [];
        if (preg_match_all('/\[([^\]]*)\]/', $path, $matches)) {
            $root = substr($path, 0, strpos($path, '['));
            $segments[] = $root;
            foreach ($matches[1] as $seg) {
                $segments[] = $seg;
            }
        } else {
            $target[$path] = $value;

            return;
        }

        $ref = &$target;
        $last = array_pop($segments);
        foreach ($segments as $seg) {
            if ('' === $seg || ctype_digit($seg)) {
                $seg = (int) $seg;
            }
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref = &$ref[$seg];
        }
        if ('' === $last || ctype_digit($last)) {
            $last = (int) $last;
        }
        $ref[$last] = $value;
    }

    public function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            $formatted[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $formatted;
    }

    protected function visit(string $path): PageInterface
    {
        $url = $this->getBaseUrl().$path;
        $this->page->goto($url);

        return $this->page;
    }

    protected function setCookie(string $name, string $value, array $options = []): void
    {
        $cookie = array_merge([
            'name' => $name,
            'value' => $value,
            'url' => $this->getBaseUrl(),
            'path' => $options['path'] ?? '/',
        ], $options);

        if (isset($cookie['expires']) && !is_int($cookie['expires'])) {
            $cookie['expires'] = (int) $cookie['expires'];
        }

        $this->context?->addCookies([$cookie]);
    }

    protected function getCookie(string $name, ?string $url = null): ?string
    {
        $url ??= $this->getBaseUrl();
        $cookies = $this->context?->cookies([$url]) ?? [];
        foreach ($cookies as $cookie) {
            if (($cookie['name'] ?? null) === $name) {
                return $cookie['value'] ?? null;
            }
        }

        return null;
    }

    protected function clearCookies(): void
    {
        $this->context?->clearCookies();
    }

    protected function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        $cookie = [
            'name' => $name,
            'value' => '',
            'path' => $path,
            'expires' => 0,
        ];
        if ($domain) {
            $cookie['domain'] = $domain;
        } else {
            $cookie['url'] = $this->getBaseUrl();
        }
        $this->context?->addCookies([$cookie]);
    }

    protected function authenticate(string $identifier = 'user', array $context = []): void
    {
        // Basic default: set an AUTH cookie that app can use
        $payload = json_encode(['id' => $identifier, 'ctx' => $context], JSON_THROW_ON_ERROR);
        $this->setCookie('AUTH', $payload);
    }

    protected function logout(): void
    {
        $this->clearCookie('AUTH');
    }

    public function getBaseUrl(): string
    {
        return 'http://localhost';
    }

    public function isBinaryContentType(?string $contentType): bool
    {
        if (!$contentType) {
            return false;
        }
        $ct = strtolower($contentType);
        if (str_starts_with($ct, 'text/') || str_starts_with($ct, 'application/json') || str_starts_with($ct, 'application/x-www-form-urlencoded') || str_starts_with($ct, 'application/xml') || str_starts_with($ct, 'application/xhtml+xml')) {
            return false;
        }

        return true;
    }

    /**
     * Build fulfill options for Playwright Route from a Symfony Response.
     *
     * @return array<string, mixed>
     */
    protected function prepareFulfillOptions(SymfonyResponse $response): array
    {
        $headers = $this->formatHeaders($response->headers->all());
        $body = $response->getContent();
        $contentType = $response->headers->get('content-type');

        $options = [
            'status' => $response->getStatusCode(),
            'headers' => $headers,
        ];

        if (is_string($body)) {
            if ($this->isBinaryContentType($contentType)) {
                $options['body'] = base64_encode($body);
                $options['isBase64'] = true;
            } else {
                $options['body'] = $body;
            }
        }

        return $options;
    }

    public function isHeadless(): bool
    {
        return 'false' !== getenv('PLAYWRIGHT_HEADLESS');
    }

    protected function getBrowserType(): string
    {
        $browser = strtolower((string) getenv('PLAYWRIGHT_BROWSER'));

        return in_array($browser, ['chromium', 'firefox', 'webkit'], true) ? $browser : 'chromium';
    }

    protected function beforeRequest(SymfonyRequest $request): void
    {
        // Override to add custom logic before each request
    }

    protected function afterResponse(SymfonyResponse $response): void
    {
        // Override to add custom logic after each response
    }

    protected function loadFixtures(array $fixtures): void
    {
        // Override to load fixtures
    }

    protected function getLastResponse(): ?SymfonyResponse
    {
        return $this->lastResponse;
    }

    protected function getLastRequest(): ?SymfonyRequest
    {
        return $this->lastRequest;
    }

    protected function loadInterceptedHosts(): void
    {
        // Get intercepted hosts from container parameter, fallback to defaults
        $container = self::$kernel->getContainer();
        $defaultHosts = ['localhost', '127.0.0.1', 'testapp.local'];

        if ($container->hasParameter('playwright.intercepted_hosts')) {
            $this->interceptedHosts = $container->getParameter('playwright.intercepted_hosts');
        } else {
            $this->interceptedHosts = $defaultHosts;
        }

        // Ensure we have at least the default hosts if parameter is empty
        if (empty($this->interceptedHosts)) {
            $this->interceptedHosts = $defaultHosts;
        }
    }
}
