<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftSealer;
use SensitiveParameter;

/**
 * Stores the content of drafts as it is, when the application turns encryption off: anyone who reads the table or a
 * backup of it reads what users typed.
 */
final readonly class PlainSealer implements DraftSealer
{
    #[Override]
    public function seal(#[SensitiveParameter] string $content, string $context): string
    {
        return $content;
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        return $sealed;
    }
}
