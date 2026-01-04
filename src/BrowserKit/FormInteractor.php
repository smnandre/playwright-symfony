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

namespace Playwright\Symfony\BrowserKit;

use Playwright\Page\PageInterface;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;

final class FormInteractor
{
    public static function fill(PageInterface $page, Form $form): void
    {
        foreach ($form->all() as $field) {
            $node = self::getNodeFromField($field);

            $xpath = XPath::fromDomElement($node);
            $locator = $page->locator('xpath='.$xpath);
            $type = strtolower($node->getAttribute('type'));

            if ('select' === $node->tagName) {
                $values = $field->getValue();
                $values = is_array($values) ? $values : [$values];
                $locator->selectOption($values);
                continue;
            }

            if ('checkbox' === $type) {
                $field->getValue() ? $locator->check() : $locator->uncheck();
                continue;
            }

            if ('radio' === $type) {
                if (null !== $field->getValue()) {
                    $locator->check();
                }
                continue;
            }

            if ('file' === $type) {
                $value = $field->getValue();
                if (!empty($value)) {
                    $locator->setInputFiles($value);
                }
                continue;
            }

            $value = $field->getValue();
            $locator->fill(is_string($value) ? $value : '');
        }
    }

    private static function getNodeFromField(FormField $field): \DOMElement
    {
        $reflection = new \ReflectionClass($field);
        $property = $reflection->getProperty('node');

        return $property->getValue($field);
    }
}
