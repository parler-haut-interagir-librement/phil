<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Period;

use DateTimeImmutable;

/**
 * How a meter cuts time: which window holds a moment, and so when its count starts again.
 */
interface PeriodPolicy
{
    /**
     * @param DateTimeImmutable      $at     the moment counted or read
     * @param DateTimeImmutable|null $anchor the subject's own anchor, for a policy that has one; others ignore it
     */
    public function windowAt(DateTimeImmutable $at, ?DateTimeImmutable $anchor = null): Window;
}
