<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\UsageMeter\AnchorProvider;
use Phil\UsageMeter\Meter;

/**
 * Anchors by subject, as subscriptions would give them.
 */
final readonly class FixedAnchors implements AnchorProvider
{
    /**
     * @param array<string, DateTimeImmutable> $anchors subject → anchor
     */
    public function __construct(
        private array $anchors,
    ) {
    }

    #[Override]
    public function anchorFor(string $subject, Meter $meter): ?DateTimeImmutable
    {
        return $this->anchors[$subject] ?? null;
    }
}
