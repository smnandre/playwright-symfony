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

namespace Playwright\Symfony\Client\Interception;

/**
 * Describes a service able to resolve assets for a given request path.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface AssetLocatorInterface
{
    public function locate(string $requestPath): ?AssetFile;
}
