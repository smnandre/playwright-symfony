<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP - https://github.com/playwright-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\Client\Interception\AssetFile;

#[CoversClass(FilesystemProxy::class)]
#[UsesClass(AssetFile::class)]
final class FilesystemProxyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/pw_fs_test_'.uniqid();
        @mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testLocateFindsExistingFile(): void
    {
        file_put_contents($this->tempDir.'/test.txt', 'hello world');

        $proxy = new FilesystemProxy([$this->tempDir]);
        $asset = $proxy->locate('/test.txt');

        $this->assertNotNull($asset);
        $this->assertSame('hello world', $asset->getContent());
        $this->assertSame('text/plain', $asset->getContentType());
    }

    public function testLocateReturnsNullForMissingFile(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/missing.txt'));
    }

    public function testLocateReturnsNullForDirectory(): void
    {
        @mkdir($this->tempDir.'/subdir');
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/subdir'));
    }

    public function testLocateHandlesQueryString(): void
    {
        file_put_contents($this->tempDir.'/test.js', 'js');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/test.js?v=123');
        $this->assertNotNull($asset);
        $this->assertSame('js', $asset->getContent());
    }

    public function testLocatePreventsDirectoryTraversal(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/../etc/passwd'));
    }

    public function testLocateWithMultipleRoots(): void
    {
        $root1 = $this->tempDir.'/root1';
        $root2 = $this->tempDir.'/root2';
        @mkdir($root1);
        @mkdir($root2);

        file_put_contents($root2.'/found.txt', 'found me');

        $proxy = new FilesystemProxy([$root1, $root2]);
        $asset = $proxy->locate('/found.txt');

        $this->assertNotNull($asset);
        $this->assertSame('found me', $asset->getContent());
    }

    public function testLocateReturnsNullForRootPath(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/'));
    }

    public function testLocateReturnsNullForPathWithDotDotInMiddle(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/foo/../bar'));
    }

    public function testLocateGuessesMimeTypeForCssFiles(): void
    {
        file_put_contents($this->tempDir.'/style.css', 'body {}');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/style.css');
        $this->assertNotNull($asset);
        $this->assertSame('text/css', $asset->getContentType());
    }

    public function testLocateGuessesMimeTypeForJavaScriptFiles(): void
    {
        file_put_contents($this->tempDir.'/script.js', 'console.log()');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/script.js');
        $this->assertNotNull($asset);
        $this->assertStringContainsString('javascript', $asset->getContentType());
    }

    public function testLocateGuessesMimeTypeForImageFiles(): void
    {
        $imageTypes = [
            'image.png' => 'image/png',
            'photo.jpg' => 'image/jpeg',
            'photo.jpeg' => 'image/jpeg',
            'icon.svg' => 'image/svg+xml',
            'animation.gif' => 'image/gif',
        ];

        $proxy = new FilesystemProxy([$this->tempDir]);

        foreach ($imageTypes as $filename => $expectedMimeType) {
            file_put_contents($this->tempDir.'/'.$filename, 'fake image data');
            $asset = $proxy->locate('/'.$filename);

            $this->assertNotNull($asset);
            $this->assertSame($expectedMimeType, $asset->getContentType());
        }
    }

    public function testLocateGuessesMimeTypeForJsonFiles(): void
    {
        file_put_contents($this->tempDir.'/data.json', '{}');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/data.json');
        $this->assertNotNull($asset);
        $this->assertSame('application/json', $asset->getContentType());
    }

    public function testLocateGuessesMimeTypeForFontFiles(): void
    {
        // Just verify fonts get some mime type (not octet-stream)
        // MimeTypes may return different variations like 'font/woff' or 'application/font-woff'
        $fontFiles = ['font.woff', 'font.woff2', 'font.ttf', 'font.otf'];

        $proxy = new FilesystemProxy([$this->tempDir]);

        foreach ($fontFiles as $filename) {
            file_put_contents($this->tempDir.'/'.$filename, 'fake font data');
            $asset = $proxy->locate('/'.$filename);

            $this->assertNotNull($asset);
            $this->assertNotSame('application/octet-stream', $asset->getContentType(), "$filename should have a specific MIME type");
        }
    }

    public function testLocateHandlesUppercaseExtensions(): void
    {
        file_put_contents($this->tempDir.'/TEST.CSS', 'body {}');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/TEST.CSS');
        $this->assertNotNull($asset);
        $this->assertSame('text/css', $asset->getContentType());
    }

    public function testLocateReturnsOctetStreamForUnknownExtension(): void
    {
        // Use a truly unknown extension that MimeTypes won't recognize
        file_put_contents($this->tempDir.'/file.unknownext123', 'unknown');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/file.unknownext123');
        $this->assertNotNull($asset);
        $this->assertSame('application/octet-stream', $asset->getContentType());
    }

    public function testLocateReturnsOctetStreamForFileWithoutExtension(): void
    {
        file_put_contents($this->tempDir.'/README', 'readme content');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/README');
        $this->assertNotNull($asset);
        $this->assertSame('application/octet-stream', $asset->getContentType());
    }

    public function testLocateIncludesFileSizeAndModificationTime(): void
    {
        $content = 'test content with specific size';
        file_put_contents($this->tempDir.'/sized.txt', $content);
        $expectedSize = strlen($content);

        $proxy = new FilesystemProxy([$this->tempDir]);
        $asset = $proxy->locate('/sized.txt');

        $this->assertNotNull($asset);
        $this->assertSame($expectedSize, $asset->getSize());
        $this->assertNotNull($asset->getLastModified());
        $this->assertIsInt($asset->getLastModified());
    }

    public function testLocateHandlesNestedDirectories(): void
    {
        @mkdir($this->tempDir.'/deep/nested/path', 0777, true);
        file_put_contents($this->tempDir.'/deep/nested/path/file.txt', 'nested content');

        $proxy = new FilesystemProxy([$this->tempDir]);
        $asset = $proxy->locate('/deep/nested/path/file.txt');

        $this->assertNotNull($asset);
        $this->assertSame('nested content', $asset->getContent());
    }

    public function testLocateReturnsFirstMatchInMultipleRoots(): void
    {
        $root1 = $this->tempDir.'/root1';
        $root2 = $this->tempDir.'/root2';
        @mkdir($root1);
        @mkdir($root2);

        file_put_contents($root1.'/same.txt', 'from root1');
        file_put_contents($root2.'/same.txt', 'from root2');

        $proxy = new FilesystemProxy([$root1, $root2]);
        $asset = $proxy->locate('/same.txt');

        $this->assertNotNull($asset);
        $this->assertSame('from root1', $asset->getContent());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
