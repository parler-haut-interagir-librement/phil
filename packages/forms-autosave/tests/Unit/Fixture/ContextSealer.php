<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Fixture;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftSealer;
use SensitiveParameter;

/**
 * Seals by writing the context before the content, and opens only in the same context: enough to see that a draft is
 * sealed, and bound to its owner and key.
 */
final class ContextSealer implements DraftSealer
{
    #[Override]
    public function seal(#[SensitiveParameter] string $content, string $context): string
    {
        return $context.'|'.$content;
    }

    #[Override]
    public function open(string $sealed, string $context): ?string
    {
        return str_starts_with($sealed, $context.'|') ? substr($sealed, \strlen($context) + 1) : null;
    }
}
