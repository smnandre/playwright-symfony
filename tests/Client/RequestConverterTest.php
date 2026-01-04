<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(RequestConverter::class)]
class RequestConverterTest extends TestCase
{
    public function testConvertsCookies(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/',
            method: 'GET',
            headers: ['cookie' => 'a=1; b=hello%20world'],
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('1', $symfony->cookies->get('a'));
        self::assertSame('hello world', $symfony->cookies->get('b'));
    }

    public function testMultipartFileUploadCreatesUploadedFile(): void
    {
        $boundary = 'XyZ123456';
        $body = "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"field\"\r\n\r\nvalue\r\n".
            "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"file\"; filename=\"test.txt\"\r\n".
            "Content-Type: text/plain\r\n\r\nHello file!\r\n".
            "--$boundary--\r\n";

        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/upload',
            method: 'POST',
            headers: ['content-type' => 'multipart/form-data; boundary='.$boundary],
            postData: $body,
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('value', $symfony->request->get('field'));
        $file = $symfony->files->get('file');
        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('test.txt', $file->getClientOriginalName());
        self::assertSame('text/plain', $file->getClientMimeType());
    }

    public function testMultipartSupportsArrayFieldsAndUtf8Filenames(): void
    {
        $boundary = 'Boundary123';
        $binary = "Hello\0World\n";
        $body = "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"items[details][name]\"\r\n\r\nWidget\r\n".
            "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"attachments[]\"; filename*=UTF-8''caf%C3%A9.txt\r\n".
            "Content-Type: text/plain\r\n\r\n$binary\r\n".
            "--$boundary--\r\n";

        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/upload',
            method: 'POST',
            headers: ['content-type' => 'multipart/form-data; boundary='.$boundary],
            postData: $body,
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        $items = $symfony->request->all('items');
        self::assertSame('Widget', $items['details']['name']);

        $attachments = $symfony->files->get('attachments');
        self::assertIsArray($attachments);
        self::assertCount(1, $attachments);
        $file = $attachments[0];
        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('café.txt', $file->getClientOriginalName());
        self::assertSame('text/plain', $file->getClientMimeType());
        self::assertSame(rtrim($binary, "\r\n"), file_get_contents($file->getRealPath()));
    }
}
