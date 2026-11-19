<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Fixture;

use Override;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\LimitProvider;
use Phil\UsageMeter\Meter;

/**
 * Limits by subject and meter, as an administrator would set them.
 */
final readonly class FixedLimits implements LimitProvider
{
    /**
     * @param array<string, array<string, Limit>> $limits subject → meter → limit
     */
    public function __construct(
        private array $limits,
    ) {
    }

    #[Override]
    public function limitFor(string $subject, Meter $meter): ?Limit
    {
        return $this->limits[$subject][$meter->name] ?? null;
    }
}
