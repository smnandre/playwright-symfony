<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
 *
 * @extends AbstractBrowser<Request, BrowserKitResponse>
 */
final class PlaywrightClient extends AbstractBrowser
{
    private BrowserContextInterface $context;

    private PageInterface $page;

    private ?BrowserKitResponse $lastResponse = null;

    private bool $followPopups = true;

    /**
     * @param array<string, mixed> $server
     */
    public function __construct(
        BrowserContextInterface $context,
        PageInterface $page,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null,
    ) {
        parent::__construct($server, $history, $cookieJar);

        $this->context = $context;
        $this->page = $page;

        \Playwright\Symfony\Util\CookieJarSync::fromContext($this->cookieJar, $this->context);
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function fromContext(
        BrowserContextInterface $context,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null,
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

    /**
     * @param array<string, mixed> $serverParameters
     */
    public function click(Link $link, array $serverParameters = []): Crawler
    {
        $xpath = \Playwright\Symfony\Util\XPathHelper::buildXPath($link->getNode());
        $locator = $this->page->locator('xpath='.$xpath);
        $this->handlePotentialPopup(static function () use ($locator): void {
            $locator->click();
        });

        return $this->refreshSnapshotAndResponse();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $serverParameters
     */
    public function submit(Form $form, array $values = [], array $serverParameters = []): Crawler
    {
        if (!empty($values)) {
            $form->setValues($values);
        }

        \Playwright\Symfony\Util\FormInteractor::fill($this->page, $form);

        $this->handlePotentialPopup(function () use ($form): void {
            $xpath = \Playwright\Symfony\Util\XPathHelper::buildXPath($form->getNode());
            $this->page->locator('xpath='.$xpath)->evaluate('el => el.requestSubmit ? el.requestSubmit() : el.submit()');
        });

        return $this->refreshSnapshotAndResponse();
    }

    private function navigate(string $url): BrowserKitResponse
    {
        $playwrightResponse = $this->page->goto($url, ['waitUntil' => 'load']);
        $content = $this->page->content() ?? '';
        $status = $playwrightResponse?->status() ?? 200;
        $headers = $playwrightResponse?->headers() ?? [];

        \Playwright\Symfony\Util\CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());

        return $this->createBrowserKitResponse($content, $status, $headers);
    }

    /**
     * Maps Playwright response data to BrowserKit Response.
     *
     * @param array<string, string|array<int, string>> $headers
     */
    private function createBrowserKitResponse(string $content, int $status, array $headers): BrowserKitResponse
    {
        $flatHeaders = [];
        foreach ($headers as $name => $value) {
            $flatHeaders[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return new BrowserKitResponse($content, $status, $flatHeaders);
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
        $this->page->evaluate(
            <<<'JS'
({url, method}) => {
  const form = document.createElement('form');
  form.action = url;
  form.method = method;
  form.style.display = 'none';
  form.id = '__pw_form';
  document.body.appendChild(form);
}
JS,
            ['url' => $action, 'method' => $method]
        );

        foreach ($params as $name => $value) {
            $this->page->evaluate(
                '({name, value}) => { const input = document.createElement("input"); input.name=name; input.value=String(value); document.getElementById("__pw_form").appendChild(input); }',
                ['name' => $name, 'value' => $value]
            );
        }

        foreach ($files as $name => $path) {
            $locator = $this->page->locator('input[type="file"][name="'.addslashes($name).'"]');
            if (0 === $locator->count()) {
                $this->page->evaluate(
                    '({name}) => { const f = document.createElement("input"); f.type="file"; f.name=name; f.style.display="none"; document.getElementById("__pw_form").appendChild(f); }',
                    ['name' => $name]
                );
            }
            // Ensure proper type for setInputFiles
            if (is_array($path)) {
                /** @var string[] $filePaths */
                $filePaths = array_values(array_filter(array_map(static function (mixed $v): string {
                    if (is_string($v)) {
                        return $v;
                    }
                    if (is_int($v)) {
                        return (string) $v;
                    }

                    return '';
                }, $path)));
            } else {
                /** @var string $path */
                $filePaths = (string) $path;
            }
            $this->page->locator('input[type="file"][name="'.addslashes($name).'"]')->setInputFiles($filePaths);
        }

        $this->handlePotentialPopup(fn (): mixed => $this->page->evaluate('() => { const form = document.getElementById("__pw_form"); return form.requestSubmit ? form.requestSubmit() : form.submit(); }'));

        $content = $this->page->content() ?? '';
        $status = 200;
        $headers = [];

        \Playwright\Symfony\Util\CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());

        return $this->createBrowserKitResponse($content, $status, $headers);
    }

    private function refreshSnapshotAndResponse(): Crawler
    {
        $content = $this->page->content() ?? '';
        $status = 200;
        $headers = [];

        \Playwright\Symfony\Util\CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());
        $this->lastResponse = $this->createBrowserKitResponse($content, $status, $headers);

        return new Crawler($content, $this->page->url());
    }

    private function handlePotentialPopup(callable $action): void
    {
        if (!$this->followPopups) {
            $action();

            return;
        }

        $popup = $this->page->context()->waitForPopup(function () use ($action): void {
            $action();
        });
        $this->page = $popup;
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
            if (method_exists($this->context, 'setExtraHTTPHeaders')) {
                $this->context->setExtraHTTPHeaders($headers);
            }
        }

        if (isset($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW'])) {
            if (method_exists($this->context, 'setHttpCredentials')) {
                $this->context->setHttpCredentials([
                    'username' => (string) $server['PHP_AUTH_USER'],
                    'password' => (string) $server['PHP_AUTH_PW'],
                ]);
            }
        }
    }
}
