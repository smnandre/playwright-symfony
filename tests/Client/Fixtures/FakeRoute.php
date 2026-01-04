<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Client\Fixtures;

use Playwright\Network\RequestInterface;

class FakeRoute
{
    public bool $continued = false;
    public bool $fulfilled = false;
    public ?array $fulfilledOptions = null;

    public function __construct(private RequestInterface $request)
    {
    }

    public function request(): RequestInterface
    {
        return $this->request;
    }

    public function continue(): void
    {
        $this->continued = true;
    }

    public function fulfill(array $options): void
    {
        $this->fulfilled = true;
        $this->fulfilledOptions = $options;
    }
}
