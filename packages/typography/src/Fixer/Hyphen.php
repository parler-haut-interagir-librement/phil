<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Hyphenation\Hyphenator;
use Phil\Typography\Text\Run;

/**
 * Soft hyphens in long words, where the language's patterns allow a break, so that a justified or narrow column can
 * break them: the browser shows a hyphen only at the end of a line. A word is a run of letters and their marks; digits,
 * emoji and symbols are never part of one, and a word that already holds a soft hyphen is left as its author wrote it.
 */
final readonly class Hyphen implements Fixer
{
    public const int MIN_LENGTH = 5;

    public function __construct(
        private Hyphenator $hyphenator,
        private int $minLength = self::MIN_LENGTH,
    ) {
    }

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->map(fn (string $text): string => preg_replace_callback(
            '/\p{L}[\p{L}\p{M}\x{AD}]*/u',
            fn (array $word): string => mb_strlen($word[0]) < $this->minLength || str_contains($word[0], "\u{AD}") ? $word[0] : $this->hyphenator->hyphenate($word[0]),
            $text,
        ) ?? $text);
    }
}
