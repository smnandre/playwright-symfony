<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Symfony\Tests\Fixtures\Tests;

use PlaywrightPHP\Network\RequestInterface;
use PlaywrightPHP\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ConcretePlaywrightTestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class implements KernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
            {
                return new SymfonyResponse('test');
            }

            public function registerBundles(): iterable
            {
                return [];
            }

            public function registerContainerConfiguration($loader): void
            {
            }

            public function boot(): void
            {
            }

            public function shutdown(): void
            {
            }

            public function getBundles(): array
            {
                return [];
            }

            public function getBundle(string $name): object
            {
                return new \stdClass();
            }

            public function locateResource(string $name): string
            {
                return '';
            }

            public function getEnvironment(): string
            {
                return 'test';
            }

            public function isDebug(): bool
            {
                return true;
            }

            public function getProjectDir(): string
            {
                return '';
            }

            public function getContainer(): object
            {
                return new \stdClass();
            }

            public function getStartTime(): float
            {
                return 0.0;
            }

            public function getCacheDir(): string
            {
                return '';
            }

            public function getBuildDir(): string
            {
                return '';
            }

            public function getLogDir(): string
            {
                return '';
            }

            public function getCharset(): string
            {
                return 'UTF-8';
            }

            public function terminate(Request $request, Response $response): void
            {
            }
        };
    }

    // Public wrappers for testing protected methods
    public function publicConvertToSymfonyRequest(RequestInterface $request): SymfonyRequest
    {
        return $this->convertToSymfonyRequest($request);
    }

    public function publicFormatHeaders(array $headers): array
    {
        return $this->formatHeaders($headers);
    }

    public function publicShouldInterceptRequest(array $url): bool
    {
        return $this->shouldInterceptRequest($url);
    }

    public function publicGetBaseUrl(): string
    {
        return $this->getBaseUrl();
    }

    public function publicIsHeadless(): bool
    {
        return $this->isHeadless();
    }

    public function publicIsBinaryContentType(?string $ct = null): bool
    {
        return $this->isBinaryContentType($ct);
    }

    public function publicPrepareFulfillOptions(SymfonyResponse $response): array
    {
        return $this->prepareFulfillOptions($response);
    }

    public function setInterceptedHosts(array $hosts): void
    {
        $this->interceptedHosts = $hosts;
    }
}
