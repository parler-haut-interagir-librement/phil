<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application\Contract;

use SensitiveParameter;

/**
 * Seals the content of a draft before it is stored, bound to a context naming its owner and key, and opens it again.
 */
interface DraftSealer
{
    public function seal(#[SensitiveParameter] string $content, string $context): string;

    /**
     * The content, or null when the sealed text cannot be opened in that context: altered, moved, or sealed under a key
     * no longer held.
     */
    public function open(string $sealed, string $context): ?string;
}
