<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Text;

/**
 * A target identical to its source, word for word — often a segment copied and forgotten. A source without letters,
 * a number or a code, passes; a name kept as it is is dismissed by the flag `ignore-same`.
 */
final readonly class SameAsSource implements Check
{
    public const string NAME = 'same';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        $source = Text::plain($checked->source);

        if (1 !== preg_match('/\p{L}/u', $source) || $source !== Text::plain($checked->target)) {
            return [];
        }

        return [new CheckIssue(self::NAME, 'The target is the same as the source.')];
    }
}
