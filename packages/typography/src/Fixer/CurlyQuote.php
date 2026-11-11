<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * The straight single quote becomes the typographic apostrophe: `l'été` is `l’été`, `don't` is `don’t`. Placed after
 * `SmartQuotes`, it turns what that fixer left — every single quote that is not an inner quotation mark.
 */
final readonly class CurlyQuote implements Fixer
{
    #[Override]
    public function fix(Run $run): Run
    {
        return $run->map(static fn (string $text): string => str_replace("'", Chars::APOSTROPHE, $text));
    }
}
