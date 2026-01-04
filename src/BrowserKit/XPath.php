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

final class XPath
{
    /**
     * Build a robust absolute XPath for a DOMElement from DomCrawler snapshot.
     * This mirrors typical DOM to XPath strategies (tag + position among siblings).
     */
    public static function fromDomElement(\DOMElement $node): string
    {
        $segments = [];
        for ($current = $node; null !== $current && \XML_ELEMENT_NODE === $current->nodeType; $current = $current->parentNode) {
            $index = 1;
            for ($sibling = $current->previousSibling; null !== $sibling; $sibling = $sibling->previousSibling) {
                if (\XML_ELEMENT_NODE === $sibling->nodeType && $sibling->nodeName === $current->nodeName) {
                    ++$index;
                }
            }
            $segments[] = sprintf('%s[%d]', $current->nodeName, $index);
        }

        $segments = array_reverse($segments);

        return '//'.implode('/', $segments);
    }
}
