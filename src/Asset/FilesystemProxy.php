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

namespace Playwright\Symfony\Asset;

use Playwright\Symfony\Client\Interception\AssetFile;
use Playwright\Symfony\Client\Interception\AssetLocatorInterface;
use Symfony\Component\Mime\MimeTypes;

final class FilesystemProxy implements AssetLocatorInterface
{
    /** @var string[] */
    private array $roots;
    private ?MimeTypes $mime;

    /**
     * @param string[] $publicRoots
     */
    public function __construct(array $publicRoots)
    {
        $this->roots = $publicRoots;
        $this->mime = class_exists(MimeTypes::class) ? new MimeTypes() : null;
    }

    public function locate(string $requestPath): ?AssetFile
    {
        $parsed = parse_url($requestPath, PHP_URL_PATH);
        $path = '/'.ltrim(false === $parsed ? '' : ($parsed ?? ''), '/');
        if ('/' === $path || str_contains($path, '..')) {
            return null;
        }

        foreach ($this->roots as $root) {
            $full = rtrim($root, '/').$path;
            if (!is_file($full)) {
                continue;
            }

            $base = realpath($root) ?: $root;
            $dir = realpath(dirname($full)) ?: dirname($full);
            if (!str_starts_with($dir, $base)) {
                continue;
            }

            $mtime = @filemtime($full) ?: null;
            $size = @filesize($full) ?: null;
            $ext = pathinfo($full, PATHINFO_EXTENSION);
            $ct = $this->guessMimeType($ext);

            return new AssetFile($full, $mtime, $size, $ct);
        }

        return null;
    }

    private function guessMimeType(?string $extension): string
    {
        if ($extension) {
            if (null !== $this->mime) {
                $types = $this->mime->getMimeTypes($extension);
                if (!empty($types)) {
                    return $types[0];
                }
            }

            $fallback = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf',
                'html' => 'text/html',
                'txt' => 'text/plain; charset=UTF-8',
            ];

            $extension = strtolower($extension);
            if (isset($fallback[$extension])) {
                return $fallback[$extension];
            }
        }

        return 'application/octet-stream';
    }
}
