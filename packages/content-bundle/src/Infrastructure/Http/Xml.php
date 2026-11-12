<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

/**
 * Text written into the Atom feeds, escaped for an XML element or a double-quoted attribute.
 */
final class Xml
{
    public static function text(string $text): string
    {
        return htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}
