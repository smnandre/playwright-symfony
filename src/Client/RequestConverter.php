<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Client;

use PlaywrightPHP\Network\RequestInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Converts Playwright requests to Symfony requests.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class RequestConverter
{
    public function convertToSymfonyRequest(RequestInterface $playwrightRequest): SymfonyRequest
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

        // Normalize headers
        $lower = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', (string) $name));
            if ('CONTENT_TYPE' === $key || 'CONTENT_LENGTH' === $key) {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        // Parse cookies
        if (isset($lower['cookie']) && is_string($lower['cookie'])) {
            $cookies = $this->parseCookieHeader($lower['cookie']);
        }

        $content = null;
        if ($postData) {
            if (is_string($postData)) {
                $contentType = $lower['content-type'] ?? null;
                if ($contentType && str_starts_with(strtolower((string) $contentType), 'application/x-www-form-urlencoded')) {
                    parse_str($postData, $parameters);
                    $content = $postData;
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

    private function parseCookieHeader(string $cookieHeader): array
    {
        $cookies = [];
        $cookiePairs = array_map('trim', explode(';', $cookieHeader));

        foreach ($cookiePairs as $pair) {
            if ('' === $pair) {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if ('' !== $name) {
                $cookies[$name] = urldecode($value);
            }
        }

        return $cookies;
    }

    private function parseMultipartFormData(string $contentType, string $body, array &$parameters, array &$files): void
    {
        if (!preg_match('/boundary=(.+)$/i', $contentType, $matches)) {
            return;
        }

        $boundary = trim($matches[1], "\"\' ");
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
            $headers = $this->parsePartHeaders($rawHeaders);

            $contentDisposition = $headers['content-disposition'] ?? '';
            if (!preg_match('/form-data;\s*name="([^"]+)"(?:;\s*filename="([^"]*)")?/i', $contentDisposition, $matches)) {
                continue;
            }

            $name = $matches[1];
            $filename = $matches[2] ?? null;
            $content = rtrim($content, "\r\n");

            if ('' === (string) $filename) {
                $parameters[$name] = $content;
            } else {
                $this->createUploadedFile($name, $filename, $content, $headers, $files);
            }
        }
    }

    private function parsePartHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', $rawHeaders) as $line) {
            if (false !== ($pos = strpos($line, ':'))) {
                $name = strtolower(trim(substr($line, 0, $pos)));
                $value = trim(substr($line, $pos + 1));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function createUploadedFile(string $name, string $filename, string $content, array $headers, array &$files): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pw_upload_');
        if (false === $tmp) {
            return;
        }

        file_put_contents($tmp, $content);
        $mime = $headers['content-type'] ?? null;
        $upload = new UploadedFile($tmp, $filename, is_string($mime) ? $mime : null, UPLOAD_ERR_OK, true);

        $this->setArrayByPath($files, $name, $upload);
    }

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
            foreach ($matches[1] as $segment) {
                $segments[] = $segment;
            }
        } else {
            $target[$path] = $value;

            return;
        }

        $ref = &$target;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if ('' === $segment || ctype_digit($segment)) {
                $segment = (int) $segment;
            }
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        if ('' === $last || ctype_digit($last)) {
            $last = (int) $last;
        }
        $ref[$last] = $value;
    }
}
