<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Client;

use PlaywrightPHP\Network\RequestInterface;
use PlaywrightPHP\Page\PageInterface;
use PlaywrightPHP\Symfony\Browser\PlaywrightBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

    public function __construct(
        private readonly PlaywrightBrowser $browser,
        private readonly HttpKernelInterface $kernel,
        private readonly RequestConverter $requestConverter,
        private readonly ResponseConverter $responseConverter,
        array $server = [],
        ?array $interceptedHosts = null,
        ?object $hookReceiver = null,
    ) {
        parent::__construct($server);

        if (null !== $interceptedHosts) {
            $this->interceptedHosts = $interceptedHosts;
        }

        $this->hookReceiver = $hookReceiver;
    }

    public function visit(string $path): PageInterface
    {
        $this->ensureInterceptorSetUp();
        $url = $this->getBaseUrl().$path;
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

    public function getBaseUrl(): string
    {
        return 'http://localhost';
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

            if ($this->shouldInterceptRequest($url)) {
                $response = $this->handleInternalRequest($request);
                $route->fulfill($this->responseConverter->prepareFulfillOptions($response));
            } else {
                $route->continue();
            }
        });
    }

    private function shouldInterceptRequest(array $url): bool
    {
        return isset($url['host']) && in_array($url['host'], $this->interceptedHosts, true);
    }

    private function handleInternalRequest(RequestInterface $playwrightRequest): SymfonyResponse
    {
        $symfonyRequest = $this->requestConverter->convertToSymfonyRequest($playwrightRequest);

        $this->beforeRequest($symfonyRequest);

        $this->lastSymfonyRequest = $symfonyRequest;
        $response = $this->kernel->handle($symfonyRequest, HttpKernelInterface::MAIN_REQUEST, false);
        $this->lastSymfonyResponse = $response;

        $this->afterResponse($response);

        return $response;
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
}
