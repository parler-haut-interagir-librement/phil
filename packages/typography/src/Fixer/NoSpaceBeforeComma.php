<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * No space before a comma, in every language: `word , word` and `word ,word` are `word, word`.
 */
final readonly class NoSpaceBeforeComma implements Fixer
{
    private const string BEFORE_WORD = '/(?<=[^'.Chars::SPACES.'])['.Chars::SPACES.']+,(?=\p{L})/u';

    private const string BEFORE = '/(?<=[^'.Chars::SPACES.'])['.Chars::SPACES.']+,/u';

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->replace(static fn (string $text): string => preg_replace([self::BEFORE_WORD, self::BEFORE], [', ', ','], $text) ?? $text);
    }
}
