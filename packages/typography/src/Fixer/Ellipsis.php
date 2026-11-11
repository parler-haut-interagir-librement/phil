<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * Three full stops, close together or spaced, become an ellipsis: `Wait...` is `Wait…`.
 */
final readonly class Ellipsis implements Fixer
{
    #[Override]
    public function fix(Run $run): Run
    {
        return $run->replace(static fn (string $text): string => str_replace(['...', '. . .'], Chars::ELLIPSIS, $text));
    }
}
