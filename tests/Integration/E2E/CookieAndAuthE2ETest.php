<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\Integration\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class CookieAndAuthE2ETest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testCookieHelpersAndAuthenticationLifecycle(): void
    {
        $this->setCookie('notice', '1');
        $this->visit('/cookie');
        $this->assertPageContains('"notice":"1"');
        self::assertSame('1', $this->getCookie('notice'));

        $this->clearCookie('notice');
        self::assertNull($this->getCookie('notice'));

        $this->authenticate('admin@example.test', ['roles' => ['ROLE_ADMIN']]);
        $this->visit('/protected');
        $this->assertPageContains('You are in');
        self::assertNotNull($this->getCookie('AUTH'));

        $this->logout();
        $this->visit('/protected');
        $this->assertPageContains('Access Denied');
        self::assertNull($this->getCookie('AUTH'));

        $this->clearCookies();
        self::assertNull($this->getCookie('notice'));
    }
}
