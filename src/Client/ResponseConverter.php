<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Client;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Converts Symfony responses to Playwright fulfill options.
 *
 * Handles regular, binary and streamed responses so that Playwright
 * receives proper bytes and content type. This is critical for assets
 * like CSS/JS/images returned by AssetMapper and other responders.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class ResponseConverter
{
    public function prepareFulfillOptions(SymfonyResponse $response): array
    {
        // Normalize and prepare headers
        $headers = $this->formatHeaders($response->headers->all());
        $contentType = $response->headers->get('content-type') ?: null;

        // Compute body for all Response variants
        $body = $response->getContent();

        // BinaryFileResponse does not expose content via getContent()
        if ((null === $body || $body === '') && $response instanceof BinaryFileResponse) {
            $file = $response->getFile();
            $path = method_exists($file, 'getPathname') ? $file->getPathname() : (string) $file;
            $body = @file_get_contents($path) ?: '';
            // Ensure we have a sane content type even if missing
            if (!$contentType) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $map = [
                    'css' => 'text/css; charset=UTF-8',
                    'js' => 'application/javascript; charset=UTF-8',
                    'json' => 'application/json; charset=UTF-8',
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'woff' => 'font/woff',
                    'woff2' => 'font/woff2',
                ];
                $contentType = $map[strtolower((string) $ext)] ?? 'application/octet-stream';
            }
        }

        // StreamedResponse outputs directly in sendContent(). Capture it.
        if ((null === $body || $body === '') && $response instanceof StreamedResponse) {
            $level = ob_get_level();
            ob_start();
            try {
                $response->sendContent();
                $body = ob_get_contents() ?: '';
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }

        $options = [
            'status' => $response->getStatusCode(),
            // Let Playwright set proper length based on provided body
            'headers' => $this->stripContentLength($headers),
        ];

        if ($contentType) {
            // Explicitly pass content type to Playwright
            $options['contentType'] = $contentType;
        }

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

    public function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            $formatted[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $formatted;
    }

    private function stripContentLength(array $headers): array
    {
        // Remove content-length as it may be stale when we construct body here
        foreach (['content-length', 'Content-Length'] as $k) {
            if (isset($headers[$k])) {
                unset($headers[$k]);
            }
        }
        return $headers;
    }

    public function isBinaryContentType(?string $contentType): bool
    {
        if (!$contentType) {
            return false;
        }

        $contentType = strtolower($contentType);

        if (str_starts_with($contentType, 'text/')) {
            return false;
        }

        $nonBinaryPrefixes = [
            'application/json',
            'application/javascript',
            'application/xml',
            'application/xhtml+xml',
            'application/x-www-form-urlencoded',
            'image/svg+xml',
        ];

        foreach ($nonBinaryPrefixes as $prefix) {
            if (str_starts_with($contentType, $prefix)) {
                return false;
            }
        }

        if (str_ends_with($contentType, '+json') || str_ends_with($contentType, '+xml')) {
            return false;
        }

        return true;
    }
}
