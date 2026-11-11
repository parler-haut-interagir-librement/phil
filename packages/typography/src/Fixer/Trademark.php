<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * The legal signs: `(tm)` becomes ™ and `(r)` becomes ®, attached to the word before them; `(c)` becomes © and `(p)`
 * becomes ℗ when a year follows, held to it by a no-break space — so the item `(c)` of a list stays as written.
 */
final readonly class Trademark implements Fixer
{
    private const array PATTERNS = [
        '/ *\((?:tm|TM|Tm)\)/u' => '™',
        '/ *\([rR]\)/u' => '®',
        '/\([cC]\) ?(?=\d)/u' => '©'.Chars::NO_BREAK_SPACE,
        '/\([pP]\) ?(?=\d)/u' => '℗'.Chars::NO_BREAK_SPACE,
        '/© (?=\d)/u' => '©'.Chars::NO_BREAK_SPACE,
    ];

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->replace(static fn (string $text): string => preg_replace(array_keys(self::PATTERNS), self::PATTERNS, $text) ?? $text);
    }
}
