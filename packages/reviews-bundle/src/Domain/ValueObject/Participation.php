<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Status\Verification;

/**
 * What the application knows of a member's part in a subject: they bought it or took part in it, and when — the
 * delivery, the end of the game or the event —, the moment the window of the participants opens.
 */
final readonly class Participation
{
    public function __construct(
        public Verification $kind,
        public DateTimeImmutable $at,
    ) {
    }
}
