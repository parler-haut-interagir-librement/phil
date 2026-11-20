<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

/**
 * Where a subject's limit of a meter comes from: its subscription, its plan, an override an administrator set.
 */
interface LimitProvider
{
    /**
     * @return Limit|null null when the provider has nothing to say about this subject and meter
     */
    public function limitFor(string $subject, Meter $meter): ?Limit;
}
