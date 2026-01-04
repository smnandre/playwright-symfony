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
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;

final class CookieJarSync
{
    public static function fromContext(CookieJar $jar, BrowserContextInterface $context): void
    {
        foreach ($context->cookies() as $cookie) {
            $jar->set(new Cookie(
                name: (string) $cookie['name'],
                value: (string) ($cookie['value'] ?? ''),
                expires: isset($cookie['expires']) ? (int) $cookie['expires'] : null,
                path: (string) ($cookie['path'] ?? '/'),
                domain: (string) ($cookie['domain'] ?? ''),
                secure: (bool) ($cookie['secure'] ?? false),
                httponly: (bool) ($cookie['httpOnly'] ?? false)
            ));
        }
    }

    public static function toJarFromUrl(CookieJar $jar, BrowserContextInterface $context, string $url): void
    {
        foreach ($context->cookies() as $cookie) {
            $jar->set(new Cookie(
                name: (string) $cookie['name'],
                value: (string) ($cookie['value'] ?? ''),
                expires: isset($cookie['expires']) ? (int) $cookie['expires'] : null,
                path: (string) ($cookie['path'] ?? '/'),
                domain: (string) ($cookie['domain'] ?? ''),
                secure: (bool) ($cookie['secure'] ?? false),
                httponly: (bool) ($cookie['httpOnly'] ?? false)
            ));
        }
    }

    public static function applyJarToContext(CookieJar $jar, BrowserContextInterface $context, string $url): void
    {
        $cookies = [];
        foreach ($jar->allValues($url) as $name => $value) {
            $cookies[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        if (!empty($cookies)) {
            $context->addCookies($cookies);
        }
    }
}
