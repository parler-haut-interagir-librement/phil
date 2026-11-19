<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

/**
 * Text written into XML: escaped, invalid UTF-8 replaced, and the characters XML 1.0 refuses left out.
 *
 * @internal
 */
final class XmlText
{
    public const string DECLARATION = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    public static function escape(string $text): string
    {
        $text = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($text, 'UTF-8'));

        return htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}
