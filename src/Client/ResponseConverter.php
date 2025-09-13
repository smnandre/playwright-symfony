<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Client;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Converts Symfony responses to Playwright fulfill options.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class ResponseConverter
{
    public function prepareFulfillOptions(SymfonyResponse $response): array
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

    public function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            $formatted[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $formatted;
    }

    public function isBinaryContentType(?string $contentType): bool
    {
        if (!$contentType) {
            return false;
        }

        $contentType = strtolower($contentType);

        $textTypes = [
            'text/',
            'application/json',
            'application/x-www-form-urlencoded',
            'application/xml',
            'application/xhtml+xml',
        ];

        foreach ($textTypes as $textType) {
            if (str_starts_with($contentType, $textType)) {
                return false;
            }
        }

        return true;
    }
}
