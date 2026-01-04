<?php

declare(strict_types=1);

namespace Playwright\Symfony\BrowserKit;

use Playwright\Page\PageInterface;
use Symfony\Component\DomCrawler\Form;

final class FormInteractor
{
    public static function fill(PageInterface $page, Form $form): void
    {
        foreach ($form->all() as $field) {
            $node = $field->getNode();
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $xpath = XPath::fromDomElement($node);
            $locator = $page->locator('xpath='.$xpath);
            $type = strtolower($node->getAttribute('type') ?? '');

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

            $locator->fill((string) $field->getValue());
        }
    }
}
