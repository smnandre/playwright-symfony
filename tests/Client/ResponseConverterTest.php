<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PlaywrightPHP\Symfony\Client\ResponseConverter;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ResponseConverter::class)]
class ResponseConverterTest extends TestCase
{
    public function testPrepareFulfillOptionsForText(): void
    {
        $converter = new ResponseConverter();
        $response = new Response('hello', 200, [
            'content-type' => 'text/plain; charset=utf-8',
            'x-custom' => ['a', 'b'],
        ]);

        $opts = $converter->prepareFulfillOptions($response);

        self::assertSame(200, $opts['status']);
        self::assertSame('hello', $opts['body']);
        self::assertSame('a, b', $opts['headers']['x-custom']);
        self::assertArrayNotHasKey('isBase64', $opts);
    }

    public function testPrepareFulfillOptionsForBinary(): void
    {
        $converter = new ResponseConverter();
        $binary = random_bytes(8);
        $response = new Response($binary, 200, [
            'content-type' => 'image/png',
        ]);

        $opts = $converter->prepareFulfillOptions($response);

        self::assertSame(200, $opts['status']);
        self::assertTrue($opts['isBase64'] ?? false);
        self::assertSame(base64_encode($binary), $opts['body']);
    }

    public function testFormatHeadersMergesArrayValues(): void
    {
        $converter = new ResponseConverter();
        $headers = [
            'content-type' => ['text/html', 'charset=utf-8'],
            'cache-control' => 'no-cache',
            'x-custom' => ['v1', 'v2'],
        ];

        $formatted = $converter->formatHeaders($headers);

        self::assertSame('text/html, charset=utf-8', $formatted['content-type']);
        self::assertSame('no-cache', $formatted['cache-control']);
        self::assertSame('v1, v2', $formatted['x-custom']);
    }
}
