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

namespace Playwright\Symfony\Util;

use Playwright\Browser\BrowserContextInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;

/**
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class CookieJarSync
{
    public static function fromContext(CookieJar $jar, BrowserContextInterface $context): void
    {
        foreach ($context->cookies() as $c) {
            $jar->set(new Cookie(
                name: (string) $c['name'],
                value: (string) ($c['value'] ?? ''), // @phpstan-ignore nullCoalesce.offset
                expires: $c['expires'] ?? null, // @phpstan-ignore nullCoalesce.offset
                path: (string) ($c['path'] ?? '/'), // @phpstan-ignore nullCoalesce.offset
                domain: (string) ($c['domain'] ?? ''), // @phpstan-ignore nullCoalesce.offset
                secure: (bool) ($c['secure'] ?? false), // @phpstan-ignore nullCoalesce.offset
                httponly: (bool) ($c['httpOnly'] ?? false), // @phpstan-ignore nullCoalesce.offset
            ));
        }
    }

    public static function toJarFromUrl(CookieJar $jar, BrowserContextInterface $context, string $url): void
    {
        foreach ($context->cookies([$url]) as $c) {
            $jar->set(new Cookie(
                name: (string) $c['name'],
                value: (string) ($c['value'] ?? ''), // @phpstan-ignore nullCoalesce.offset
                expires: $c['expires'] ?? null, // @phpstan-ignore nullCoalesce.offset
                path: (string) ($c['path'] ?? '/'), // @phpstan-ignore nullCoalesce.offset
                domain: (string) ($c['domain'] ?? ''), // @phpstan-ignore nullCoalesce.offset
                secure: (bool) ($c['secure'] ?? false), // @phpstan-ignore nullCoalesce.offset
                httponly: (bool) ($c['httpOnly'] ?? false), // @phpstan-ignore nullCoalesce.offset
            ));
        }
    }
}
