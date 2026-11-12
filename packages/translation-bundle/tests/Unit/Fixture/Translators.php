<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Fixture;

use Override;
use Phil\TranslationBundle\Application\Contract\Translators as TranslatorsPort;

/**
 * The translators of a test: who is at the screen, and the names people are credited under — a person missing from
 * the names asked not to be credited.
 */
final class Translators implements TranslatorsPort
{
    /**
     * @param array<array-key, string> $names
     */
    public function __construct(
        public ?string $current = null,
        public array $names = [],
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->current;
    }

    #[Override]
    public function credit(string $translator): ?string
    {
        return $this->names[$translator] ?? null;
    }
}
