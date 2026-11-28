<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;

/**
 * When the responsible people of an event may read the safety information of its participants: from a few days
 * before it starts until it ends. The information is kept until the window closes, then purged.
 */
final readonly class AccessWindow
{
    public function __construct(
        public DateTimeImmutable $opensAt,
        public DateTimeImmutable $closesAt,
    ) {
        if ($closesAt <= $opensAt) {
            throw InvalidSafetyInfo::because('An access window closes after it opens.');
        }
    }

    /**
     * Whether the window is open at `$at`: from its opening, included, to its closing, excluded.
     */
    public function isOpenAt(DateTimeImmutable $at): bool
    {
        return $at >= $this->opensAt && $at < $this->closesAt;
    }

    /**
     * Whether the window is closed for good at `$at`.
     */
    public function hasClosedAt(DateTimeImmutable $at): bool
    {
        return $at >= $this->closesAt;
    }
}
