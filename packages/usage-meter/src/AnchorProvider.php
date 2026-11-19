<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use DateTimeImmutable;

/**
 * Where a subject's own anchor comes from, for the meters whose period has one — a billing cycle that starts on the
 * day the subject subscribed.
 */
interface AnchorProvider
{
    /**
     * @return DateTimeImmutable|null null when the subject has no anchor of its own for this meter
     */
    public function anchorFor(string $subject, Meter $meter): ?DateTimeImmutable;
}
