<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Normalizer;
use Override;
use Phil\Typography\Text\Run;

/**
 * Text in Unicode normalization form C: an `e` followed by a combining acute accent becomes `é`, so the other fixers,
 * the hyphenation patterns and the reader's search all see one character. Emoji sequences are unchanged by it.
 */
final readonly class UnicodeNormalization implements Fixer
{
    #[Override]
    public function fix(Run $run): Run
    {
        return $run->map(static function (string $text): string {
            $normalized = Normalizer::normalize($text);

            return false === $normalized ? $text : $normalized;
        });
    }
}
