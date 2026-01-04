<?php

declare(strict_types=1);

namespace Playwright\Symfony\BrowserKit;

use Playwright\Page\PageInterface;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

final class ResponseMapper
{
    /**
     * @param array<string, string|array<int, string>> $headers
     */
    public static function fromPlaywright(string $content, int $status, array $headers, string $uri): BrowserKitResponse
    {
        $flatHeaders = [];
        foreach ($headers as $name => $value) {
            $flatHeaders[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return new BrowserKitResponse($content, $status, $flatHeaders);
    }

    public static function lastMainResourceResponse(PageInterface $page): ?object
    {
        return null;
    }
}
