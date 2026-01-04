<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Client;

use Playwright\Network\RequestInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\Interception\AssetServer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * BrowserKit-compatible client that uses Playwright for browser automation
 * while routing requests through Symfony's HttpKernel.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 *
 * @final
 */
class PlaywrightClient extends AbstractBrowser
{
    private ?SymfonyRequest $lastSymfonyRequest = null;
    private ?SymfonyResponse $lastSymfonyResponse = null;
    private array $interceptedHosts = ['localhost', '127.0.0.1', 'testapp.local'];
    private ?object $hookReceiver = null;
    private bool $interceptorSetUp = false;
    private ?AssetServer $assetServer;
    private ?string $lastProfileToken = null;

    public function __construct(
        private readonly PlaywrightBrowser $browser,
        private readonly HttpKernelInterface $kernel,
        private readonly RequestConverter $requestConverter,
        private readonly ResponseConverter $responseConverter,
        array $server = [],
        ?array $interceptedHosts = null,
        ?object $hookReceiver = null,
        ?AssetServer $assetServer = null,
        private readonly string $baseUrl = 'http://localhost',
        private ?LoggerInterface $logger = null,
        private readonly bool $debugLogging = false,
    ) {
        parent::__construct($server);

        if (null !== $interceptedHosts) {
            $this->interceptedHosts = $interceptedHosts;
        }

        $this->hookReceiver = $hookReceiver;
        $this->assetServer = $assetServer;
        $this->logger = $logger ?? new NullLogger();
    }

    public function visit(string $path): PageInterface
    {
        $this->ensureInterceptorSetUp();
        $url = $this->getBaseUrl().$path;
        $this->log('debug', 'Navigating with Playwright', ['url' => $url]);
        $page = $this->browser->getPage();
        $page->goto($url);

        return $page;
    }

    public function getPage(): PageInterface
    {
        return $this->browser->getPage();
    }

    public function setCookie(string $name, string $value, array $options = []): void
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

        $this->browser->getContext()?->addCookies([$cookie]);
    }

    public function getCookie(string $name, ?string $url = null): ?string
    {
        $url ??= $this->getBaseUrl();
        $cookies = $this->browser->getContext()?->cookies([$url]) ?? [];

        foreach ($cookies as $cookie) {
            if (($cookie['name'] ?? null) === $name) {
                return $cookie['value'] ?? null;
            }
        }

        return null;
    }

    public function clearCookies(): void
    {
        $this->browser->getContext()?->clearCookies();
    }

    public function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
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

        $this->browser->getContext()?->addCookies([$cookie]);
    }

    public function authenticate(string $identifier = 'user', array $context = []): void
    {
        $payload = json_encode(['id' => $identifier, 'ctx' => $context], JSON_THROW_ON_ERROR);
        $this->setCookie('AUTH', $payload);
    }

    public function logout(): void
    {
        $this->clearCookie('AUTH');
    }

    public function getLastSymfonyRequest(): ?SymfonyRequest
    {
        return $this->lastSymfonyRequest;
    }

    public function getLastSymfonyResponse(): ?SymfonyResponse
    {
        return $this->lastSymfonyResponse;
    }

    public function getProfile(): ?Profile
    {
        if (null === $this->lastProfileToken) {
            return null;
        }

        // Access the container from the kernel to get the profiler service
        // We use $this->kernel->getContainer() directly which implies a booted kernel.
        // In a test environment, the kernel is usually booted in setUp.
        if (!$this->kernel->getContainer()->has('profiler')) {
            return null;
        }

        /** @var Profiler $profiler */
        $profiler = $this->kernel->getContainer()->get('profiler');

        // The profiler service might be null in some test configurations (e.g. if WebProfilerBundle is not enabled).
        if (null === $profiler) {
            return null;
        }

        return $profiler->loadProfile($this->lastProfileToken);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setInterceptedHosts(array $hosts): void
    {
        $this->interceptedHosts = $hosts;
    }

    public function getInterceptedHosts(): array
    {
        return $this->interceptedHosts;
    }

    protected function doRequest(object $request): Response
    {
        // This method is required by AbstractBrowser but we handle requests
        // through Playwright's route interception instead
        throw new \BadMethodCallException('Use visit() method instead of request() for Playwright client');
    }

    private function setupRequestInterception(): void
    {
        $this->browser->setupRouting(function ($route) {
            $request = $route->request();
            $url = parse_url($request->url());

            if (!$this->shouldInterceptRequest($url)) {
                $this->log('debug', 'Continuing external request', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
                $route->continue();
                return;
            }

            if (
                $this->assetServer
                && $this->assetServer->supports($request->url(), $request->method())
            ) {
                $assetResponse = $this->assetServer->handle($request->url(), $request->method());
                if (null !== $assetResponse) {
                    $this->log('debug', 'AssetServer fulfilled request', [
                        'url' => $request->url(),
                        'method' => $request->method(),
                    ]);
                    $route->fulfill($assetResponse);
                    return;
                }

                $this->log('debug', 'AssetServer miss falling back to kernel', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
            }

            $response = $this->handleInternalRequest($request);
            $route->fulfill($this->responseConverter->prepareFulfillOptions($response));
        });
    }

    private function shouldInterceptRequest(array $url): bool
    {
        return isset($url['host']) && in_array($url['host'], $this->interceptedHosts, true);
    }

    private function handleInternalRequest(RequestInterface $playwrightRequest): SymfonyResponse
    {
        $symfonyRequest = $this->requestConverter->convertToSymfonyRequest($playwrightRequest);
        $startedAt = microtime(true);

        $this->beforeRequest($symfonyRequest);

        // Capture any debug output that might be generated
        $bufferLevel = ob_get_level();
        ob_start();
        
        try {
            $this->lastSymfonyRequest = $symfonyRequest;
            $response = $this->kernel->handle($symfonyRequest, HttpKernelInterface::MAIN_REQUEST, false);
            $this->lastSymfonyResponse = $response;

            if ($response->headers->has('X-Debug-Token')) {
                $this->lastProfileToken = $response->headers->get('X-Debug-Token');
            }

            // Only clean the buffer if we started it
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->afterResponse($response);

            $this->log('info', 'Fulfilled intercepted request', [
                'method' => $symfonyRequest->getMethod(),
                'uri' => $symfonyRequest->getUri(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return $response;
        } catch (\Throwable $e) {
            // Clean up output buffers even when an exception occurs
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->log('error', 'Exception while handling intercepted request', [
                'method' => $symfonyRequest->getMethod(),
                'uri' => $symfonyRequest->getUri(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ], false);
            
            throw $e;
        }
    }

    protected function beforeRequest(SymfonyRequest $request): void
    {
        if ($this->hookReceiver && method_exists($this->hookReceiver, 'beforeRequest')) {
            $this->hookReceiver->beforeRequest($request);
        }
    }

    protected function afterResponse(SymfonyResponse $response): void
    {
        if ($this->hookReceiver && method_exists($this->hookReceiver, 'afterResponse')) {
            $this->hookReceiver->afterResponse($response);
        }
    }

    private function ensureInterceptorSetUp(): void
    {
        if (!$this->interceptorSetUp) {
            $this->setupRequestInterception();
            $this->interceptorSetUp = true;
        }
    }

    private function log(string $level, string $message, array $context = [], bool $requiresDebug = true): void
    {
        if ($requiresDebug && !$this->debugLogging) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
