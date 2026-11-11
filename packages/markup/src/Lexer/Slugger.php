<?php

declare(strict_types=1);

namespace Phil\Markup\Lexer;

/**
 * The anchors of the headings of one document, as GitHub writes them, unique in the document: lower case, accents of
 * the Latin letters dropped, every other run of characters a hyphen, a letter first, 56 characters at most before a
 * suffix. A second heading of the same text takes `-1`, then `-2`.
 */
final class Slugger
{
    private const array LATIN = [
        'a' => 'àáâãäåāăą', 'c' => 'çćĉċč', 'd' => 'ďđð', 'e' => 'èéêëēĕėęě', 'g' => 'ĝğġģ', 'h' => 'ĥħ',
        'i' => 'ìíîïĩīĭįı', 'j' => 'ĵ', 'k' => 'ķ', 'l' => 'ĺļľŀł', 'n' => 'ñńņňŉ', 'o' => 'òóôõöøōŏő',
        'r' => 'ŕŗř', 's' => 'śŝşš', 't' => 'ţťŧ', 'u' => 'ùúûüũūŭůűų', 'w' => 'ŵ', 'y' => 'ýÿŷ', 'z' => 'źżž',
        'ae' => 'æ', 'oe' => 'œ', 'ss' => 'ß', 'th' => 'þ',
    ];

    /** @var array<string, string> the anchors taken, by themselves */
    private array $taken = [];

    /** @var array<string, int> the suffix to try first for each base, those below it being taken */
    private array $suffixes = [];

    /**
     * Keeps an anchor the writer chose, so that no generated one takes it.
     */
    public function reserve(string $anchor): void
    {
        $this->taken[$anchor] = $anchor;
    }

    /**
     * A new anchor for a heading's text. The suffix a base reached is remembered, so that many headings of the same
     * text take linear time.
     */
    public function slug(string $text): string
    {
        $base = $this->base($text);
        $slug = $base;
        $suffix = $this->suffixes[$base] ?? 1;

        for (; isset($this->taken[$slug]); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        $this->suffixes[$base] = $suffix;
        $this->reserve($slug);

        return $slug;
    }

    private function base(string $text): string
    {
        $text = mb_strtolower($text);

        foreach (self::LATIN as $ascii => $letters) {
            $text = str_replace(mb_str_split($letters), $ascii, $text);
        }

        $cursor = new Cursor($text);
        $words = [];

        while (!$cursor->isEnd()) {
            $words[] = $cursor->consumeWhile('abcdefghijklmnopqrstuvwxyz0123456789_');
            $cursor->consumeUntil('abcdefghijklmnopqrstuvwxyz0123456789_');
        }

        $slug = rtrim(substr(implode('-', array_filter($words, static fn (string $word): bool => '' !== $word)), 0, 54), '-');

        return ctype_alpha($slug[0] ?? '') ? $slug : 'h-'.$slug;
    }
}
