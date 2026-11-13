<?php

declare(strict_types=1);

namespace Phil\Epub;

use Dom\Element;
use Dom\HTMLDocument;
use Phil\Epub\Internal\XhtmlSerialization;

/**
 * Splits a fragment of HTML into chapters at its top-level headings: each heading of level `$level` or higher — `<h1>`
 * alone by default — starts a new chapter, unless nothing but white space comes before it.
 */
final readonly class HtmlSplitter
{
    /**
     * @return list<string> the HTML of each part, in order
     */
    public static function split(string $html, int $level = 1): array
    {
        $document = HTMLDocument::createEmpty();
        $body = $document->createElement('body');
        $body->innerHTML = $html;

        $parts = [];
        $part = '';

        foreach ($body->childNodes as $node) {
            $heading = $node instanceof Element ? XhtmlSerialization::HEADINGS[$node->localName] ?? null : null;

            if (null !== $heading && $heading <= $level && '' !== trim($part)) {
                $parts[] = $part;
                $part = '';
            }

            $part .= $document->saveHtml($node);
        }

        $parts[] = $part;

        return $parts;
    }
}
