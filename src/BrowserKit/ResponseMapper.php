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
