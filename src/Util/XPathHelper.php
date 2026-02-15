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

namespace Playwright\Symfony\Util;

/**
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class XPathHelper
{
    /**
     * Build a robust absolute XPath for a DOMElement.
     * This mirrors typical DOM to XPath strategies (tag + position among siblings).
     */
    public static function buildXPath(\DOMElement $node): string
    {
        $segments = [];

        for ($n = $node; null !== $n && XML_ELEMENT_NODE === $n->nodeType; $n = $n->parentNode) {
            $index = 1;
            $sibling = $n->previousSibling;
            while ($sibling) {
                if (XML_ELEMENT_NODE === $sibling->nodeType && $sibling->nodeName === $n->nodeName) {
                    ++$index;
                }
                $sibling = $sibling->previousSibling;
            }
            $tag = $n->nodeName;
            $segments[] = sprintf('%s[%d]', $tag, $index);
        }

        $segments = array_reverse($segments);

        return '//'.implode('/', $segments);
    }
}
