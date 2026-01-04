<?php

declare(strict_types=1);

namespace Playwright\Symfony\Client\Interception;

/**
 * Describes a service able to resolve assets for a given request path.
 */
interface AssetLocatorInterface
{
    public function locate(string $requestPath): ?AssetFile;
}
