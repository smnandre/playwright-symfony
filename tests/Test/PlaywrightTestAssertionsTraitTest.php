<?php

declare(strict_types=1);

namespace Playwright\Symfony\Tests\Test;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;

class PlaywrightTestAssertionsTraitTest extends TestCase
{
    use PlaywrightTestAssertionsTrait;

    /** @var object */
    private $page;

    protected function setUp(): void
    {
        $this->page = new class {
            public string $content = '<html><body>Hello World</body></html>';
            /** @var array<string, array<int, mixed>> */
            public array $calls = [];

            public function content(): string
            {
                return $this->content;
            }

            public function locator(string $selector): object
            {
                $this->calls['locator'][] = $selector;

                return (object) ['selector' => $selector];
            }

            public function querySelector(string $selector): ?object
            {
                $this->calls['querySelector'][] = $selector;

                return null;
            }

            public function click(string $selector): void
            {
                $this->calls['click'][] = $selector;
            }

            public function fill(string $selector, string $value): void
            {
                $this->calls['fill'][] = [$selector, $value];
            }

            public function selectOption(string $selector, string $value): void
            {
                $this->calls['selectOption'][] = [$selector, $value];
            }

            public function check(string $selector): void
            {
                $this->calls['check'][] = $selector;
            }

            public function uncheck(string $selector): void
            {
                $this->calls['uncheck'][] = $selector;
            }

            public function waitForSelector(string $selector, array $options = []): void
            {
                $this->calls['waitForSelector'][] = [$selector, $options];
            }

            public function screenshot(array $options): void
            {
                $this->calls['screenshot'][] = $options;
            }
        };
    }

    public function testAssertPageContainsUsesPageContent(): void
    {
        $this->assertPageContains('Hello World');
    }

    public function testAssertPageNotContainsUsesPageContent(): void
    {
        $this->assertPageNotContains('Missing Text');
    }

    public function testAssertSelectorExistsUsesLocator(): void
    {
        $this->assertSelectorExists('#main');

        $this->assertSame(['#main'], $this->page->calls['locator'] ?? []);
    }

    public function testAssertSelectorNotExistsUsesQuerySelector(): void
    {
        $this->assertSelectorNotExists('.missing');

        $this->assertSame(['.missing'], $this->page->calls['querySelector'] ?? []);
    }

    public function testInteractionHelpersDelegateToPage(): void
    {
        $this->click('#button');
        $this->fill('#input', 'value');
        $this->select('#select', 'option');
        $this->check('#check');
        $this->uncheck('#uncheck');
        $this->waitForSelector('#wait', ['timeout' => 1000]);
        $this->screenshot('/tmp/screenshot.png');

        $this->assertSame(['#button'], $this->page->calls['click'] ?? []);
        $this->assertSame([['#input', 'value']], $this->page->calls['fill'] ?? []);
        $this->assertSame([['#select', 'option']], $this->page->calls['selectOption'] ?? []);
        $this->assertSame(['#check'], $this->page->calls['check'] ?? []);
        $this->assertSame(['#uncheck'], $this->page->calls['uncheck'] ?? []);
        $this->assertSame([['#wait', ['timeout' => 1000]]], $this->page->calls['waitForSelector'] ?? []);
        $this->assertSame([['path' => '/tmp/screenshot.png']], $this->page->calls['screenshot'] ?? []);
    }
}

