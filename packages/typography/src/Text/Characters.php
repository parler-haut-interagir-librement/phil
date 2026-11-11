<?php

declare(strict_types=1);

namespace Phil\Typography\Text;

/**
 * What a character is, for the fixers that look at their neighbours.
 *
 * @internal
 */
final class Characters
{
    /**
     * The characters after which a quote opens. `>` is among them, as the end of a tag written as text (`&lt;b&gt;`) or
     * a marker of quoted text: what follows it starts.
     */
    private const string OPENERS = '([{«“‘„‹-–—/"\'>';

    /**
     * The characters before which a single quote closes. `<` is among them, as the start of a tag written as text.
     */
    private const string CLOSING_FOLLOWERS = '.,;:!?)]}»”’"…–—-<';

    public static function isSpace(string $character): bool
    {
        return 1 === preg_match('/^['.Chars::SPACES.']$/u', $character);
    }

    /**
     * The start of the text, a space or an opening mark: what may come right before an opening quote.
     */
    public static function opensAfter(string $character): bool
    {
        return '' === $character || self::isSpace($character) || str_contains(self::OPENERS, $character);
    }

    /**
     * The end of the text, a space or a closing mark: what may come right after a closing quote.
     */
    public static function closesBefore(string $character): bool
    {
        return '' === $character || self::isSpace($character) || str_contains(self::CLOSING_FOLLOWERS, $character);
    }
}
