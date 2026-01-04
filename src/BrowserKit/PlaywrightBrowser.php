<?php

declare(strict_types=1);

namespace Playwright\Symfony\BrowserKit;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;

/**
 * A BrowserKit client backed by a real Playwright page.
 *
 * Notes:
 * - By default, uses "real-browser" semantics for navigation/click/submit.
 * - Builds a DomCrawler snapshot from the live DOM after each action.
 */
final class PlaywrightBrowser extends AbstractBrowser
{
    private BrowserContextInterface $context;

    private PageInterface $page;

    private ?BrowserKitResponse $lastResponse = null;

    private bool $followPopups = true;

    public function __construct(
        BrowserContextInterface $context,
        PageInterface $page,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null
    ) {
        parent::__construct($server, $history, $cookieJar);

        $this->context = $context;
        $this->page = $page;

        CookieJarSync::fromContext($this->cookieJar, $this->context);
    }

    public static function fromContext(
        BrowserContextInterface $context,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null
    ): self {
        return new self($context, $context->newPage(), $server, $history, $cookieJar);
    }

    public function getPage(): PageInterface
    {
        return $this->page;
    }

    public function getLastResponse(): ?BrowserKitResponse
    {
        return $this->lastResponse;
    }

    protected function doRequest($request): BrowserKitResponse
    {
        \assert($request instanceof Request);

        $method = strtoupper($request->getMethod());
        $uri = (string) $request->getUri();

        $this->applyServerParams($request);

        if (in_array($method, ['GET', 'HEAD'], true) && empty($request->getParameters()) && empty($request->getFiles())) {
            $response = $this->navigate($uri);
            $this->lastResponse = $response;

            return $response;
        }

        $response = $this->submitSyntheticForm(
            $uri,
            $method,
            $request->getParameters(),
            $request->getFiles(),
            (string) $request->getContent()
        );
        $this->lastResponse = $response;

        return $response;
    }

    public function click(Link $link, array $serverParameters = []): Crawler
    {
        $xpath = XPath::fromDomElement($link->getNode());
        $locator = $this->page->locator('xpath='.$xpath);
        $this->handlePotentialPopup(static fn (): bool => (bool) $locator->click());

        return $this->refreshSnapshotAndResponse();
    }

    public function submit(Form $form, array $values = [], array $serverParameters = []): Crawler
    {
        if (!empty($values)) {
            $form->setValues($values);
        }

        FormInteractor::fill($this->page, $form);

        $this->handlePotentialPopup(function () use ($form): void {
            $xpath = XPath::fromDomElement($form->getNode());
            $this->page->locator('xpath='.$xpath)->evaluate('el => el.requestSubmit ? el.requestSubmit() : el.submit()');
        });

        return $this->refreshSnapshotAndResponse();
    }

    private function navigate(string $url): BrowserKitResponse
    {
        $playwrightResponse = $this->page->goto($url, ['waitUntil' => 'load']);
        $content = $this->page->content();
        $status = $playwrightResponse?->status() ?? 200;
        $headers = $playwrightResponse?->headers() ?? [];

        CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());

        return ResponseMapper::fromPlaywright($content, $status, $headers, $this->page->url());
    }

    /**
     * Submits a synthetic form in-page to preserve browser semantics (JS, events).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $files
     */
    private function submitSyntheticForm(string $action, string $method, array $params, array $files, string $rawContent): BrowserKitResponse
    {
        $method = strtoupper($method);
        $formHandle = $this->page->evaluateHandle(
            <<<'JS'
(url, method) => {
  const form = document.createElement('form');
  form.action = url;
  form.method = method;
  form.style.display = 'none';
  document.body.appendChild(form);
  return form;
}
JS,
            $action,
            $method
        );

        foreach ($params as $name => $value) {
            $this->page->evaluate(
                '(form, name, value) => { const input = document.createElement("input"); input.name=name; input.value=String(value); form.appendChild(input); }',
                $formHandle,
                $name,
                $value
            );
        }

        foreach ($files as $name => $path) {
            $locator = $this->page->locator('input[type="file"][name="'.addslashes((string) $name).'"]');
            if (0 === $locator->count()) {
                $this->page->evaluate(
                    '(form, name) => { const f = document.createElement("input"); f.type="file"; f.name=name; f.style.display="none"; form.appendChild(f); }',
                    $formHandle,
                    $name
                );
            }
            $this->page->locator('input[type="file"][name="'.addslashes((string) $name).'"]')->setInputFiles($path);
        }

        $this->handlePotentialPopup(fn (): mixed => $this->page->evaluate('(form) => form.requestSubmit ? form.requestSubmit() : form.submit()', $formHandle));

        $content = $this->page->content();
        $playwrightResponse = ResponseMapper::lastMainResourceResponse($this->page);
        $status = $playwrightResponse?->status() ?? 200;
        $headers = $playwrightResponse?->headers() ?? [];

        CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());

        return ResponseMapper::fromPlaywright($content, $status, $headers, $this->page->url());
    }

    private function refreshSnapshotAndResponse(): Crawler
    {
        $playwrightResponse = ResponseMapper::lastMainResourceResponse($this->page);
        $content = $this->page->content();
        $status = $playwrightResponse?->status() ?? 200;
        $headers = $playwrightResponse?->headers() ?? [];

        CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());
        $this->lastResponse = ResponseMapper::fromPlaywright($content, $status, $headers, $this->page->url());

        return new Crawler($content, $this->page->url());
    }

    private function handlePotentialPopup(callable $action): void
    {
        if (!$this->followPopups) {
            $action();

            return;
        }

        $popup = null;
        $this->page->once('popup', function ($newPage) use (&$popup): void {
            $popup = $newPage;
        });
        $action();
        if ($popup instanceof PageInterface) {
            $this->page = $popup;
        }
    }

    private function applyServerParams(Request $request): void
    {
        $server = $request->getServer();
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (!empty($headers)) {
            $this->context->setExtraHTTPHeaders($headers);
        }

        if (isset($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW'])) {
            $this->context->setHttpCredentials([
                'username' => (string) $server['PHP_AUTH_USER'],
                'password' => (string) $server['PHP_AUTH_PW'],
            ]);
        }
    }
}
