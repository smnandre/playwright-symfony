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

namespace Playwright\Symfony\Tests\Asset;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Asset\AssetMapperProxy;
use Playwright\Symfony\Client\Interception\AssetFile;

class AssetMapperProxyTest extends TestCase
{
    public function testLocateReturnsNullWhenNoMapperProvided(): void
    {
        $proxy = new AssetMapperProxy(null);

        $this->assertNull($proxy->locate('/build/app.js'));
    }

    public function testLocateUsesGetAssetFromPublicPathWhenAvailable(): void
    {
        $asset = new class {
            public string $publicPath = '/build/app.js';
            public string $sourcePath = '/path/to/app.js';
            public string $content = 'console.log(\"hi\");';
            public int $lastModified = 1234567890;
        };

        $mapper = new class($asset) {
            private object $asset;

            public function __construct(object $asset)
            {
                $this->asset = $asset;
            }

            public function getAssetFromPublicPath(string $publicPath): ?object
            {
                return '/build/app.js' === $publicPath ? $this->asset : null;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $file = $proxy->locate('/build/app.js');

        $this->assertInstanceOf(AssetFile::class, $file);
        $this->assertSame('/path/to/app.js', $file->getPath());
        $this->assertStringContainsString('javascript', $file->getContentType());
        $this->assertTrue($file->hasInlineContent());
        $this->assertSame('console.log(\"hi\");', $file->getContent());
        $this->assertSame(1234567890, $file->getLastModified());
        $this->assertSame(strlen('console.log(\"hi\");'), $file->getSize());
    }

    public function testLocateFallsBackToAssetIndexWhenDirectLookupMissing(): void
    {
        $asset = new class {
            public function getPublicPath(): string
            {
                return '/assets/logo.png';
            }

            public function getSourcePath(): string
            {
                return '/path/to/logo.png';
            }

            public int $lastModified = 42;
        };

        $mapper = new class($asset) {
            private object $asset;

            public function __construct(object $asset)
            {
                $this->asset = $asset;
            }

            /**
             * @return array<int, object>
             */
            public function allAssets(): array
            {
                return [$this->asset];
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $file = $proxy->locate('/assets/logo.png');

        $this->assertInstanceOf(AssetFile::class, $file);
        $this->assertSame('/path/to/logo.png', $file->getPath());
        $this->assertSame('image/png', $file->getContentType());
        $this->assertSame(42, $file->getLastModified());
    }

    public function testLocateReturnsNullWhenAssetHasNoPathOrContent(): void
    {
        $asset = new class {
            public string $publicPath = '/assets/empty.txt';
        };

        $mapper = new class($asset) {
            private object $asset;

            public function __construct(object $asset)
            {
                $this->asset = $asset;
            }

            public function getAssetFromPublicPath(string $publicPath): ?object
            {
                return '/assets/empty.txt' === $publicPath ? $this->asset : null;
            }
        };

        $proxy = new AssetMapperProxy($mapper);

        $this->assertNull($proxy->locate('/assets/empty.txt'));
    }
}
