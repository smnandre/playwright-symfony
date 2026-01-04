<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\Client;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\ResponseConverter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseConverterTest extends TestCase
{
    private ResponseConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ResponseConverter();
    }

    public function testPrepareFulfillOptionsForTextResponse(): void
    {
        $response = new Response('hello', 200, [
            'content-type' => 'text/plain; charset=utf-8',
            'content-length' => '5',
            'x-custom' => ['a', 'b'],
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertSame('hello', $opts['body']);
        $this->assertSame('text/plain; charset=utf-8', $opts['contentType']);
        $this->assertSame('a, b', $opts['headers']['x-custom']);
        $this->assertArrayNotHasKey('content-length', $opts['headers']);
        $this->assertArrayNotHasKey('Content-Length', $opts['headers']);
        $this->assertArrayNotHasKey('isBase64', $opts);
    }

    public function testPrepareFulfillOptionsForBinaryFileResponse(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pw_response_');
        $this->assertNotFalse($tmp);

        $binary = random_bytes(16);
        file_put_contents($tmp, $binary);

        $response = new BinaryFileResponse($tmp);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame($response->getStatusCode(), $opts['status']);
        $this->assertArrayHasKey('headers', $opts);

        @unlink($tmp);
    }

    public function testPrepareFulfillOptionsForStreamedResponse(): void
    {
        $response = new StreamedResponse(static function (): void {
            echo 'stream-content';
        });

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame($response->getStatusCode(), $opts['status']);
        $this->assertArrayHasKey('headers', $opts);
        if (array_key_exists('body', $opts)) {
            $this->assertIsString($opts['body']);
        }
    }

    public function testIsBinaryContentTypeDetectsBinaryAndNonBinaryTypes(): void
    {
        $this->assertFalse($this->converter->isBinaryContentType(null));
        $this->assertFalse($this->converter->isBinaryContentType('text/plain'));
        $this->assertFalse($this->converter->isBinaryContentType('application/json'));
        $this->assertFalse($this->converter->isBinaryContentType('application/vnd.api+json'));
        $this->assertFalse($this->converter->isBinaryContentType('image/svg+xml'));

        $this->assertTrue($this->converter->isBinaryContentType('image/png'));
        $this->assertTrue($this->converter->isBinaryContentType('application/octet-stream'));
        $this->assertTrue($this->converter->isBinaryContentType('application/pdf'));
    }

    public function testFormatHeadersJoinsArrayValues(): void
    {
        $headers = [
            'x-single' => 'value',
            'x-multi' => ['a', 'b'],
        ];

        $formatted = $this->converter->formatHeaders($headers);

        $this->assertSame('value', $formatted['x-single']);
        $this->assertSame('a, b', $formatted['x-multi']);
    }

    public function testPrepareFulfillOptionsEncodesBinaryBodyWhenContentTypeIsBinary(): void
    {
        $binary = random_bytes(8);
        $response = new Response($binary, 200, [
            'content-type' => 'image/png',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertTrue($opts['isBase64']);
        $this->assertSame(base64_encode($binary), $opts['body']);
    }

    public function testPrepareFulfillOptionsLeavesBodyAsTextForJson(): void
    {
        $payload = json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR);
        $response = new Response($payload, 200, [
            'content-type' => 'application/json',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertSame($payload, $opts['body']);
        $this->assertArrayNotHasKey('isBase64', $opts);
    }
}
