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
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\Client\Interception\AssetFile;

class FilesystemProxyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/pw_fs_proxy_'.uniqid('', true);
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (@scandir($this->tempDir) ?: [] as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }

            @unlink($this->tempDir.'/'.$file);
        }

        @rmdir($this->tempDir);
    }

    public function testLocateReturnsNullForInvalidOrMissingPath(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);

        $this->assertNull($proxy->locate('/missing.css'));
        $this->assertNull($proxy->locate('../etc/passwd'));
        $this->assertNull($proxy->locate('/'));
    }

    public function testLocateReturnsAssetFileForExistingFile(): void
    {
        $filePath = $this->tempDir.'/style.css';
        $content = 'body { background: #fff; }';
        file_put_contents($filePath, $content);

        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/style.css');

        $this->assertInstanceOf(AssetFile::class, $asset);
        $this->assertStringEndsWith('/style.css', $asset->getPath());
        $this->assertSame(strlen($content), $asset->getSize());
        $this->assertNotNull($asset->getLastModified());
        $this->assertSame('text/css', $asset->getContentType());
    }
}
