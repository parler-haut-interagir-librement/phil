<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

/**
 * Plain text escaped for HTML, its web addresses — `https://…`, `http://…`, `www.…` — made links. Punctuation that
 * ends a sentence stays out of the address.
 */
final class Autolink
{
    private const string ADDRESS = '~\b(?:https?://|www\.)[^\s<>"\']+~i';

    public static function html(string $text): string
    {
        $html = '';
        $offset = 0;

        preg_match_all(self::ADDRESS, $text, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$found, $at]) {
            $address = rtrim($found, '.,;:!?)\'"');
            $html .= self::escape(substr($text, $offset, $at - $offset));
            $href = str_starts_with(strtolower($address), 'www.') ? 'https://'.$address : $address;
            $html .= \sprintf('<a href="%s">%s</a>', self::escape($href), self::escape($address));
            $offset = $at + \strlen($address);
        }

        return $html.self::escape(substr($text, $offset));
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5 | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
